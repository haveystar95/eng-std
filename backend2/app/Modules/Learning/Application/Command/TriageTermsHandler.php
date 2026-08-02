<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Dto\TriageBatchResult;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Domain\Entity\Triage;
use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Repository\TermProgressRepository;
use App\Modules\Learning\Domain\Repository\TriageRepository;
use App\Modules\Learning\Domain\Service\TriageVerificationPlanner;
use App\Modules\Learning\Domain\ValueObject\CefrLevel;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Learning\Domain\ValueObject\TriageVerdict;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermDifficultyView;
use App\Modules\Vocabulary\Application\Query\TermDifficultyReader;
use App\Modules\Vocabulary\Application\Query\TermExistenceReader;
use DateInterval;
use DateTimeImmutable;

/**
 * Applies a triage batch: append each swipe to its own log (idempotent by client ULID),
 * then re-project the GOVERNING verdict per touched term onto (user, term) progress. The
 * governing verdict is the row with the greatest client_seq across the whole log — never the
 * latest by decided_at (a device clock that can lag) — so an out-of-order or clock-skewed
 * swipe cannot revive a superseded verdict. Only terms this batch touched are re-projected.
 *
 * Projection (a term is "new" iff it has no non-`new` progress row):
 *   known   → a `known` row whose due_at is a verification check (timing from the planner:
 *             risky verdict soon, obvious one far out); skip if a row already exists
 *   unsure  → a `learning` row, due now (skip if a row already exists)
 *   unknown → leave it new; the one exception is returning a `known` term to learning, which
 *             resets its state but keeps reps/lapses
 * Triage never writes to `reviews` and never touches daily stats.
 */
final readonly class TriageTermsHandler
{
    public function __construct(
        private TriageRepository $triages,
        private TermProgressRepository $progress,
        private TermExistenceReader $terms,
        private TriageVerificationPlanner $planner,
        private LearnerProfileReader $learnerProfile,
        private TermDifficultyReader $termDifficulty,
        private TransactionManager $tx,
        private Clock $clock,
    ) {}

    public function __invoke(TriageTerms $command): TriageBatchResult
    {
        $known = $this->knownTermIds($command);

        return $this->tx->run(function () use ($command, $known): TriageBatchResult {
            /** @var list<Triage> $accepted */
            $accepted = [];
            $duplicates = 0;
            $unknown = 0;

            foreach ($command->triages as $input) {
                if (! isset($known[$input->termId->value])) {
                    $unknown++;

                    continue;
                }

                $triage = new Triage(
                    id: $input->triageId,
                    userId: $command->actorId,
                    termId: $input->termId,
                    verdict: $input->verdict,
                    decidedAt: $input->decidedAt,
                    clientSeq: $input->clientSeq,
                    collectionId: $input->collectionId,
                    latencyMs: $input->latencyMs,
                );

                if (! $this->triages->insertIgnore($triage)) {
                    $duplicates++;

                    continue;
                }

                $accepted[] = $triage;
            }

            $this->project($command, $accepted);

            return new TriageBatchResult(
                accepted: count($accepted),
                duplicates: $duplicates,
                unknown: $unknown,
            );
        });
    }

    /**
     * Re-project the governing verdict for every term this batch touched. The governing verdict
     * is read from the log by client_seq (see currentByTerm), so a lower-seq swipe arriving in a
     * later chunk, or a verdict stamped with a lagging clock, never overwrites the real latest.
     *
     * @param  list<Triage>  $accepted
     */
    private function project(TriageTerms $command, array $accepted): void
    {
        /** @var array<string, TermId> $affected */
        $affected = [];
        foreach ($accepted as $triage) {
            $affected[$triage->termId->value] = $triage->termId;
        }
        if ($affected === []) {
            return;
        }

        $governing = $this->triages->currentByTerm($command->actorId, array_values($affected));
        if ($governing === []) {
            return;
        }

        $now = $this->clock->now();
        $userLevel = $this->learnerProfile->cefrLevelFor($command->actorId);
        $difficulty = $this->termDifficulty->byIds($this->knownVerdictTermIds($governing));

        foreach ($governing as $termIdValue => $triage) {
            $termId = TermId::fromString($termIdValue);
            $existing = $this->progress->findForUpdate($command->actorId, $termId);

            if ($triage->verdict === TriageVerdict::Known) {
                if ($existing === null) {
                    $this->progress->save($this->planKnown($command, $termId, $triage, $difficulty[$termIdValue] ?? null, $userLevel, $now));
                }
            } elseif ($triage->verdict === TriageVerdict::Unsure) {
                if ($existing === null) {
                    $this->progress->save(TermProgress::learningFromTriage($command->actorId, $termId, $now));
                }
            } elseif ($existing !== null && $existing->state() === LearningState::Known) {
                $this->progress->save($existing->returnToNew());
            }
        }
    }

    private function planKnown(
        TriageTerms $command,
        TermId $termId,
        Triage $triage,
        ?TermDifficultyView $difficulty,
        CefrLevel $userLevel,
        DateTimeImmutable $now,
    ): TermProgress {
        $plan = $this->planner->plan(
            CefrLevel::tryFromLabel($difficulty?->cefr),
            $userLevel,
            $triage->latencyMs,
            $difficulty !== null && $difficulty->isPhrase,
        );

        $dueAt = $now->add(new DateInterval('P' . $plan->dueInDays . 'D'));

        return TermProgress::knownFromTriage($command->actorId, $termId, $dueAt);
    }

    /**
     * @param  array<string, Triage>  $governing
     * @return list<TermId>
     */
    private function knownVerdictTermIds(array $governing): array
    {
        $ids = [];
        foreach ($governing as $triage) {
            if ($triage->verdict === TriageVerdict::Known) {
                $ids[] = $triage->termId;
            }
        }

        return $ids;
    }

    /** @return array<string, true> */
    private function knownTermIds(TriageTerms $command): array
    {
        $set = [];
        foreach ($this->terms->existing($command->termIds()) as $termId) {
            $set[$termId->value] = true;
        }

        return $set;
    }
}

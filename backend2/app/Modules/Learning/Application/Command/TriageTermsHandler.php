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
 *             risky verdict soon, obvious one far out); skip if a row already exists. NOT enrolled.
 *   unsure  → a `learning` row on the first recognition rung, ENROLLED
 *   unknown → a rung-0 row, ENROLLED; a `known` term is returned to new first (its state resets,
 *             reps/lapses survive)
 * Triage never writes to `reviews` and never touches daily stats.
 *
 * ENROLMENT is the point of two of those three lines. The swipe deck is where a learner decides,
 * word by word, what they want worked on: «не знаю» and «не уверен» both mean «учи это», and both
 * therefore put the pair in the pool. «Знаю» means the opposite and leaves it in the catalogue.
 * Enrolment is idempotent (see TermProgress::enroll) — a re-swiped word keeps the day it entered.
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
                    revealed: $input->revealed,
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

            // Has the app ever taught this pair, or is the row merely the learner's decision to
            // study it? Before the pool the two were the same question — a row existed only once a
            // word had been met — and the guard below was written as «no row yet». Enrolment breaks
            // that equivalence, so the guard asks what it always meant. See TermProgress.
            $taught = $existing !== null && $existing->hasBeenTaught();

            if ($triage->verdict === TriageVerdict::Known) {
                // «Знаю» writes the verification row and stops. It does not enrol — that is the
                // whole meaning of the verdict — and on an untaught word that was enrolled earlier
                // («Учить это слово», then a swipe pass over the collection) it supersedes that
                // enrolment: the later deliberate statement about the word wins, and it says the
                // opposite of «учи это». A pair with real history is left entirely alone.
                if (! $taught) {
                    $this->progress->save($this->planKnown($command, $termId, $triage, $difficulty[$termIdValue] ?? null, $userLevel, $now));
                }
            } elseif ($triage->verdict === TriageVerdict::Unsure) {
                $this->progress->save(
                    $taught
                        // Real history: enrol it and touch nothing else. A swipe is not allowed to
                        // move a word that has been studied back down to a recognition rung.
                        ? $existing->enroll($now)
                        : TermProgress::learningFromTriage($command->actorId, $termId, $now),
                );
            } else {
                // «Не знаю». Until this chapter the verdict left no row at all and the word was
                // found again by "has a triage marker but no progress row" — a definition that
                // could only ever be read backwards. It now writes what it actually means: a pair
                // at rung 0, IN THE POOL. Nothing about the ladder or the schedule changes; the row
                // is the same one the first intro card would have created a moment later.
                $progress = $existing ?? TermProgress::start($command->actorId, $termId);
                if ($existing !== null && $existing->state() === LearningState::Known) {
                    // A `known` mark undone: reset the claim, keep reps/lapses — then enrol, because
                    // the learner has just said they do not know it after all.
                    $progress = $existing->returnToNew();
                }
                $this->progress->save($progress->enroll($now));
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
            $triage->revealed,
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

<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Dto\TriageBatchResult;
use App\Modules\Learning\Domain\Entity\Triage;
use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Repository\TermProgressRepository;
use App\Modules\Learning\Domain\Repository\TriageRepository;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use App\Modules\Learning\Domain\ValueObject\TriageVerdict;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\TermExistenceReader;

/**
 * Applies a triage batch: append each swipe to its own log (idempotent by client ULID),
 * then project the latest verdict per term onto (user, term) progress. Only newly-accepted
 * swipes drive the projection, so a re-uploaded batch changes nothing — the same guarantee
 * SubmitReviews gives.
 *
 * Projection (a term is "new" iff it has no progress row):
 *   known   → create a `known` row (skip if a row already exists)
 *   unsure  → create a `learning` row, due now (skip if a row already exists)
 *   unknown → leave it new; the one exception is returning a `known` term to learning,
 *             which drops its row so it becomes new again.
 * Triage never writes to `reviews` and never touches daily stats.
 */
final readonly class TriageTermsHandler
{
    public function __construct(
        private TriageRepository $triages,
        private TermProgressRepository $progress,
        private TermExistenceReader $terms,
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
     * Apply the latest accepted verdict per term to its progress row.
     *
     * @param  list<Triage>  $accepted
     */
    private function project(TriageTerms $command, array $accepted): void
    {
        foreach ($this->latestVerdictPerTerm($accepted) as $termIdValue => $verdict) {
            $termId = TermId::fromString($termIdValue);
            $existing = $this->progress->findForUpdate($command->actorId, $termId);

            // Triage only ever acts on a still-new term (create a row) — except the return
            // path, where an unknown swipe sends a self-marked `known` row back to new. That
            // return keeps reps/lapses (never erases a term's history) and never touches real
            // study progress (learning/review) on a stray unknown swipe.
            if ($verdict === TriageVerdict::Known) {
                if ($existing === null) {
                    $this->progress->save(TermProgress::knownFromTriage($command->actorId, $termId));
                }
            } elseif ($verdict === TriageVerdict::Unsure) {
                if ($existing === null) {
                    $this->progress->save(TermProgress::learningFromTriage($command->actorId, $termId, $this->clock->now()));
                }
            } elseif ($existing !== null && $existing->state() === LearningState::Known) {
                $this->progress->save($existing->returnToNew());
            }
        }
    }

    /**
     * @param  list<Triage>  $accepted
     * @return array<string, TriageVerdict>  term id → verdict of its latest swipe
     */
    private function latestVerdictPerTerm(array $accepted): array
    {
        usort($accepted, static fn (Triage $a, Triage $b): int => $a->decidedAt <=> $b->decidedAt);

        $latest = [];
        foreach ($accepted as $triage) {
            $latest[$triage->termId->value] = $triage->verdict; // later decided_at overwrites
        }

        return $latest;
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

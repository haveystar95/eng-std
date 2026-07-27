<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Dto\ReviewBatchResult;
use App\Modules\Learning\Application\Port\StatsProjector;
use App\Modules\Learning\Domain\Entity\Review;
use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Event\ReviewsSubmitted;
use App\Modules\Learning\Domain\Repository\ReviewRepository;
use App\Modules\Learning\Domain\Repository\TermProgressRepository;
use App\Modules\Learning\Domain\Service\Scheduler;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Vocabulary\Application\Query\TermExistenceReader;

/**
 * Applies a review batch: append each answer to the log (idempotent by client ULID),
 * then fold the accepted answers into (user, term) progress in answered_at order — so a
 * replayed offline batch yields exactly the progress the user would have had online.
 */
final readonly class SubmitReviewsHandler
{
    public function __construct(
        private ReviewRepository $reviews,
        private TermProgressRepository $progress,
        private Scheduler $scheduler,
        private TermExistenceReader $terms,
        private StatsProjector $stats,
        private TransactionManager $tx,
        private Clock $clock,
    ) {}

    public function __invoke(SubmitReviews $command): ReviewBatchResult
    {
        $known = $this->knownTermIds($command);

        return $this->tx->run(function () use ($command, $known): ReviewBatchResult {
            /** @var list<Review> $accepted */
            $accepted = [];
            $duplicates = 0;
            $unknown = 0;

            foreach ($command->reviews as $input) {
                if (! isset($known[$input->termId->value])) {
                    $unknown++;

                    continue;
                }

                $review = new Review(
                    id: $input->reviewId,
                    userId: $command->actorId,
                    termId: $input->termId,
                    grade: $input->grade,
                    answeredAt: $input->answeredAt,
                    sessionId: $input->sessionId,
                    latencyMs: $input->latencyMs,
                );

                if (! $this->reviews->insertIgnore($review)) {
                    $duplicates++;

                    continue;
                }

                $accepted[] = $review;
            }

            $introduced = $this->foldIntoProgress($command, $accepted);

            if ($accepted !== []) {
                $this->stats->project(new ReviewsSubmitted($this->clock->now(), $accepted, $introduced));
            }

            return new ReviewBatchResult(
                accepted: count($accepted),
                duplicates: $duplicates,
                unknown: $unknown,
            );
        });
    }

    /**
     * Fold accepted reviews into progress, in global answered_at order, one term at a time.
     *
     * @param  list<Review>  $accepted
     * @return list<string>  ids of terms answered for the first time (fresh progress rows)
     */
    private function foldIntoProgress(SubmitReviews $command, array $accepted): array
    {
        usort($accepted, static fn (Review $a, Review $b): int => $a->answeredAt <=> $b->answeredAt);

        /** @var array<string, list<Review>> $byTerm */
        $byTerm = [];
        foreach ($accepted as $review) {
            $byTerm[$review->termId->value][] = $review;
        }

        $introduced = [];
        foreach ($byTerm as $termReviews) {
            $termId = $termReviews[0]->termId;

            $termProgress = $this->progress->findForUpdate($command->actorId, $termId);
            if ($termProgress === null) {
                $termProgress = TermProgress::start($command->actorId, $termId);
                $introduced[] = $termId->value;
            }

            foreach ($termReviews as $review) {
                $termProgress = $this->scheduler->schedule($termProgress, $review->grade, $review->answeredAt);
            }

            $this->progress->save($termProgress);
        }

        return $introduced;
    }

    /** @return array<string, true> */
    private function knownTermIds(SubmitReviews $command): array
    {
        $set = [];
        foreach ($this->terms->existing($command->termIds()) as $termId) {
            $set[$termId->value] = true;
        }

        return $set;
    }
}

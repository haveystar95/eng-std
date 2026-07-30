<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Dto\ReviewBatchResult;
use App\Modules\Learning\Application\Port\LatencyMedianReader;
use App\Modules\Learning\Application\Port\StatsProjector;
use App\Modules\Learning\Domain\Entity\Review;
use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Event\ReviewsSubmitted;
use App\Modules\Learning\Domain\Repository\ReviewRepository;
use App\Modules\Learning\Domain\Repository\TermProgressRepository;
use App\Modules\Learning\Domain\Service\AnswerGrader;
use App\Modules\Learning\Domain\Service\Scheduler;
use App\Modules\Learning\Domain\ValueObject\Answer;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\ExpectedAnswer;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Vocabulary\Application\Query\TermAnswerKeyReader;
use App\Modules\Vocabulary\Application\Query\TermExistenceReader;

/**
 * Applies an answer batch. The SERVER grades each raw answer (leniency + per-mode latency
 * median), so the grading rule lives in one runtime, then folds the accepted answers into
 * (user, term) progress in answered_at order — a replayed offline batch yields the same
 * progress it would have online. Practice answers are appended to the log (they keep the
 * streak) but never schedule. The per-mode median cache is invalidated after the fold.
 */
final readonly class SubmitReviewsHandler
{
    public function __construct(
        private ReviewRepository $reviews,
        private TermProgressRepository $progress,
        private Scheduler $scheduler,
        private TermExistenceReader $terms,
        private TermAnswerKeyReader $answerKeys,
        private AnswerGrader $grader,
        private LatencyMedianReader $median,
        private StatsProjector $stats,
        private TransactionManager $tx,
        private Clock $clock,
    ) {}

    public function __invoke(SubmitReviews $command): ReviewBatchResult
    {
        $known = $this->knownTermIds($command);
        $keys = $this->answerKeys->byIds($command->termIds());

        return $this->tx->run(function () use ($command, $known, $keys): ReviewBatchResult {
            /** @var list<Review> $accepted */
            $accepted = [];
            /** @var array<string, ExerciseMode> $touchedModes */
            $touchedModes = [];
            $duplicates = 0;
            $unknown = 0;

            foreach ($command->reviews as $input) {
                if (! isset($known[$input->termId->value])) {
                    $unknown++;

                    continue;
                }
                $key = $keys[$input->termId->value] ?? null;
                if ($key === null) {
                    $unknown++;

                    continue;
                }

                $grade = $this->grader->grade(
                    new Answer($input->response, $input->usedHint, $input->latencyMs),
                    $input->exerciseMode,
                    new ExpectedAnswer($key->accepted, $key->isPhrase),
                    // Cached per (user, mode); frozen for this batch, invalidated below.
                    $this->median->medianFor($command->actorId, $input->exerciseMode),
                );

                $review = new Review(
                    id: $input->reviewId,
                    userId: $command->actorId,
                    termId: $input->termId,
                    grade: $grade,
                    answeredAt: $input->answeredAt,
                    exerciseMode: $input->exerciseMode,
                    isPractice: $input->isPractice,
                    response: $input->response,
                    sessionId: $input->sessionId,
                    latencyMs: $input->latencyMs,
                );

                if (! $this->reviews->insertIgnore($review)) {
                    $duplicates++;

                    continue;
                }

                $accepted[] = $review;
                $touchedModes[$input->exerciseMode->value] = $input->exerciseMode;
            }

            $introduced = $this->foldIntoProgress($command, $accepted);

            if ($accepted !== []) {
                $this->stats->project(new ReviewsSubmitted($this->clock->now(), $accepted, $introduced));
            }

            foreach ($touchedModes as $mode) {
                $this->median->forget($command->actorId, $mode);
            }

            return new ReviewBatchResult(
                accepted: count($accepted),
                duplicates: $duplicates,
                unknown: $unknown,
            );
        });
    }

    /**
     * Fold accepted, non-practice answers into progress, in global answered_at order, one term
     * at a time. Practice answers are skipped: they never affect intervals, state or lapses.
     *
     * @param  list<Review>  $accepted
     * @return list<string>  ids of terms answered (for real) for the first time
     */
    private function foldIntoProgress(SubmitReviews $command, array $accepted): array
    {
        $scheduled = array_values(array_filter($accepted, static fn (Review $r): bool => ! $r->isPractice));
        usort($scheduled, static fn (Review $a, Review $b): int => $a->answeredAt <=> $b->answeredAt);

        /** @var array<string, list<Review>> $byTerm */
        $byTerm = [];
        foreach ($scheduled as $review) {
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

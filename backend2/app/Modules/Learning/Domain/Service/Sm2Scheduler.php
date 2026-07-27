<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\ValueObject\Grade;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use DateInterval;
use DateTimeImmutable;

/**
 * SM-2 with sane guards, expressed as a pure function of the current state.
 *
 * All intervals are whole days (the schema stores `interval_days INT`). Intra-state steps
 * use a 0-day interval, meaning "due immediately, try again this session". Ease is only
 * touched in the `review` state; `learning`/`relearning` steps are fixed and ease-free.
 */
final readonly class Sm2Scheduler implements Scheduler
{
    private const EASE_MIN = 1.30;
    private const EASE_MAX = 3.00;
    private const MAX_INTERVAL_DAYS = 365;

    // Fixed step/graduation intervals (days).
    private const NEW_STEP = 1;                 // new + good/hard → learning
    private const NEW_EASY_STEP = 3;            // new + easy → learning (a touch ahead)
    private const LEARNING_STEP = 1;            // learning + hard → stay learning
    private const GRADUATING_INTERVAL = 4;      // learning + good → review
    private const EASY_GRADUATING_INTERVAL = 7; // learning + easy → review
    private const RELEARN_GRADUATING_INTERVAL = 1; // relearning + good → review (reduced)
    private const RELEARN_EASY_INTERVAL = 4;    // relearning + easy → review

    // Review-state multipliers/deltas.
    private const HARD_INTERVAL_FACTOR = 1.2;
    private const EASY_INTERVAL_BONUS = 1.3;
    private const EASE_DELTA_AGAIN = -0.20;
    private const EASE_DELTA_HARD = -0.15;
    private const EASE_DELTA_EASY = 0.15;

    public function __construct(private Fuzz $fuzz) {}

    public function schedule(TermProgress $progress, Grade $grade, DateTimeImmutable $now): TermProgress
    {
        return match ($progress->state()) {
            LearningState::New => $this->fromNew($progress, $grade, $now),
            LearningState::Learning => $this->fromLearning($progress, $grade, $now),
            LearningState::Review => $this->fromReview($progress, $grade, $now),
            LearningState::Relearning => $this->fromRelearning($progress, $grade, $now),
        };
    }

    /** First-ever review: always enters `learning`, regardless of grade. */
    private function fromNew(TermProgress $p, Grade $grade, DateTimeImmutable $now): TermProgress
    {
        $interval = match ($grade) {
            Grade::Again => 0,
            Grade::Hard, Grade::Good => self::NEW_STEP,
            Grade::Easy => self::NEW_EASY_STEP,
        };

        return $this->next($p, LearningState::Learning, $p->easeFactor(), $interval, $now, $p->lapses());
    }

    /** Short intra-day steps until `good`/`easy` graduates the term to `review`. */
    private function fromLearning(TermProgress $p, Grade $grade, DateTimeImmutable $now): TermProgress
    {
        [$state, $interval] = match ($grade) {
            Grade::Again => [LearningState::Learning, 0],
            Grade::Hard => [LearningState::Learning, self::LEARNING_STEP],
            Grade::Good => [LearningState::Review, self::GRADUATING_INTERVAL],
            Grade::Easy => [LearningState::Review, self::EASY_GRADUATING_INTERVAL],
        };

        return $this->next($p, $state, $p->easeFactor(), $interval, $now, $p->lapses());
    }

    /** Long, ease-driven intervals. A lapse drops the term into `relearning`. */
    private function fromReview(TermProgress $p, Grade $grade, DateTimeImmutable $now): TermProgress
    {
        $interval = $p->intervalDays();

        return match ($grade) {
            Grade::Again => $this->next(
                $p, LearningState::Relearning, $p->easeFactor() + self::EASE_DELTA_AGAIN, 0, $now, $p->lapses() + 1,
            ),
            Grade::Hard => $this->next(
                $p, LearningState::Review, $p->easeFactor() + self::EASE_DELTA_HARD,
                (int) round($interval * self::HARD_INTERVAL_FACTOR), $now, $p->lapses(),
            ),
            Grade::Good => $this->next(
                $p, LearningState::Review, $p->easeFactor(),
                (int) round($interval * $p->easeFactor()), $now, $p->lapses(),
            ),
            Grade::Easy => $this->reviewEasy($p, $interval, $now),
        };
    }

    private function reviewEasy(TermProgress $p, int $interval, DateTimeImmutable $now): TermProgress
    {
        $ease = $this->clampEase($p->easeFactor() + self::EASE_DELTA_EASY);

        return $this->next(
            $p, LearningState::Review, $ease,
            (int) round($interval * $ease * self::EASY_INTERVAL_BONUS), $now, $p->lapses(),
        );
    }

    /** Lapsed term working its way back; `good`/`easy` returns it to `review`. */
    private function fromRelearning(TermProgress $p, Grade $grade, DateTimeImmutable $now): TermProgress
    {
        [$state, $interval] = match ($grade) {
            Grade::Again => [LearningState::Relearning, 0],
            Grade::Hard => [LearningState::Relearning, self::LEARNING_STEP],
            Grade::Good => [LearningState::Review, self::RELEARN_GRADUATING_INTERVAL],
            Grade::Easy => [LearningState::Review, self::RELEARN_EASY_INTERVAL],
        };

        return $this->next($p, $state, $p->easeFactor(), $interval, $now, $p->lapses());
    }

    /**
     * Assemble the next progress state: clamp ease and interval, fuzz multi-day intervals,
     * compute `due_at`, and advance the review counter. A 0-day interval is due immediately.
     */
    private function next(
        TermProgress $p,
        LearningState $state,
        float $easeFactor,
        int $intervalDays,
        DateTimeImmutable $now,
        int $lapses,
    ): TermProgress {
        $interval = $this->clampInterval($intervalDays);
        $interval = $interval === 0 ? 0 : $this->fuzz->apply($interval);

        $dueAt = $interval === 0 ? $now : $now->add(new DateInterval('P' . $interval . 'D'));

        return TermProgress::reconstitute(
            userId: $p->userId(),
            termId: $p->termId(),
            state: $state,
            easeFactor: $this->clampEase($easeFactor),
            intervalDays: $interval,
            dueAt: $dueAt,
            reps: $p->reps() + 1,
            lapses: $lapses,
            lastReviewedAt: $now,
        );
    }

    private function clampEase(float $ease): float
    {
        return max(self::EASE_MIN, min(self::EASE_MAX, $ease));
    }

    private function clampInterval(int $days): int
    {
        if ($days <= 0) {
            return 0;
        }

        return max(1, min(self::MAX_INTERVAL_DAYS, $days));
    }
}

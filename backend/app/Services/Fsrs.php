<?php

namespace App\Services;

use App\Models\ReviewState;
use Carbon\CarbonImmutable;

/**
 * FSRS (Free Spaced Repetition Scheduler) — v4.5 formulas with default weights.
 *
 * Ratings: 1 = again, 2 = hard, 3 = good, 4 = easy.
 * Given a card's memory state (stability S, difficulty D) and the rating, it
 * produces the new S, D and the next due date so that expected retrievability
 * at review time is ~`requestRetention`.
 */
class Fsrs
{
    /** Default FSRS-4.5 parameters. */
    private const W = [
        0.4072, 1.1829, 3.1262, 15.4722, 7.2102, 0.5316, 1.0651, 0.0234,
        1.616, 0.1544, 1.0824, 1.9813, 0.0953, 0.2975, 2.2042, 0.2407,
        2.9466, 0.5034, 0.6567,
    ];

    private const DECAY = -0.5;
    private const FACTOR = 19 / 81; // (0.9 ** (1/DECAY)) - 1

    public function __construct(
        private readonly float $requestRetention = 0.9,
        private readonly int $maximumInterval = 3650,
    ) {}

    /**
     * Apply a rating to a review state and return it (unsaved) updated in place.
     */
    public function review(ReviewState $s, int $rating, ?CarbonImmutable $now = null): ReviewState
    {
        $now ??= CarbonImmutable::now();
        $isNew = $s->reps === 0 || $s->stability <= 0;

        if ($isNew) {
            $difficulty = $this->initDifficulty($rating);
            $stability = $this->initStability($rating);
        } else {
            $elapsed = $s->last_reviewed_at
                ? max(0, $s->last_reviewed_at->diffInDays($now))
                : 0;
            $retrievability = $this->retrievability($elapsed, $s->stability);
            $difficulty = $this->nextDifficulty($s->difficulty, $rating);
            $stability = $rating === 1
                ? $this->lapseStability($s->difficulty, $s->stability, $retrievability)
                : $this->recallStability($difficulty, $s->stability, $retrievability, $rating);
        }

        $interval = $this->nextInterval($stability);

        $s->stability = $stability;
        $s->difficulty = $difficulty;
        $s->reps = $s->reps + 1;
        $s->lapses = $s->lapses + ($rating === 1 ? 1 : 0);
        $s->state = $rating === 1 ? 'relearning' : 'review';
        $s->last_rating = $rating;
        $s->last_reviewed_at = $now;
        $s->due_at = $now->addDays($interval);

        return $s;
    }

    /** Days until the card should next be shown. */
    public function nextInterval(float $stability): int
    {
        $interval = $stability / self::FACTOR * (($this->requestRetention ** (1 / self::DECAY)) - 1);

        return (int) max(1, min(round($interval), $this->maximumInterval));
    }

    private function retrievability(int $elapsedDays, float $stability): float
    {
        if ($stability <= 0) {
            return 0.0;
        }

        return (1 + self::FACTOR * $elapsedDays / $stability) ** self::DECAY;
    }

    private function initStability(int $rating): float
    {
        return max(0.1, self::W[$rating - 1]);
    }

    private function initDifficulty(int $rating): float
    {
        $d = self::W[4] - exp(self::W[5] * ($rating - 1)) + 1;

        return $this->clampDifficulty($d);
    }

    private function nextDifficulty(float $difficulty, int $rating): float
    {
        $delta = $difficulty - self::W[6] * ($rating - 3);
        // Mean reversion toward the "easy" baseline difficulty.
        $reverted = self::W[7] * $this->initDifficulty(4) + (1 - self::W[7]) * $delta;

        return $this->clampDifficulty($reverted);
    }

    private function recallStability(float $difficulty, float $stability, float $r, int $rating): float
    {
        $hardPenalty = $rating === 2 ? self::W[15] : 1.0;
        $easyBonus = $rating === 4 ? self::W[16] : 1.0;

        $growth = exp(self::W[8])
            * (11 - $difficulty)
            * ($stability ** -self::W[9])
            * (exp(self::W[10] * (1 - $r)) - 1)
            * $hardPenalty
            * $easyBonus;

        return $stability * (1 + $growth);
    }

    private function lapseStability(float $difficulty, float $stability, float $r): float
    {
        return self::W[11]
            * ($difficulty ** -self::W[12])
            * ((($stability + 1) ** self::W[13]) - 1)
            * exp(self::W[14] * (1 - $r));
    }

    private function clampDifficulty(float $d): float
    {
        return max(1.0, min(10.0, $d));
    }
}

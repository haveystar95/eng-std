<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\LearningState;

/**
 * The single definition of "mastered" (усвоено) for the whole project — stats,
 * per-collection progress and the results screen all ask here. A second definition
 * living somewhere else guarantees two figures that disagree on two screens.
 *
 * A term is mastered when it is either confirmed by exercises (review state with a long
 * interval) OR self-marked as `known` during triage. Note this is deliberately broader
 * than "confirmed": screens that need to distinguish the two show `known` as its own
 * sub-segment ("marked as familiar" vs "proven by exercises") — a breakdown of this one
 * definition, never a second definition.
 */
final class Mastery
{
    /** A review-state term counts as mastered once its interval reaches this many days. */
    public const MASTERED_INTERVAL_DAYS = 21;

    public static function isMastered(LearningState $state, int $intervalDays): bool
    {
        return $state === LearningState::Known
            || ($state === LearningState::Review && $intervalDays >= self::MASTERED_INTERVAL_DAYS);
    }
}

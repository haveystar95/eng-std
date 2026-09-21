<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Port;

use App\Modules\Plan\Domain\Assembly\PhrasesDeal;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;

/**
 * THE BUILD LOG OF A DAY (наряд BACK-TAILS-2 §1) — where a dealing says what it could not help. A day is dealt once, when it
 * is opened, and whatever it had to give up is written here with its numbers, for the architect to read: it is a signal,
 * not an error, and the learner sees nothing of it.
 */
interface DayBuildLog
{
    /**
     * THE STOP SIGNAL OF «ФРАЗЫ»: the ladder spent every rung and the stage is still over its ceiling
     * ({@see PhrasesDeal::overCeiling()}). The day was dealt anyway.
     */
    public function phrasesOverCeiling(PlanId $plan, PlanDayId $day, int $dayNumber, PlanSceneId $scene, PhrasesDeal $deal): void;
}

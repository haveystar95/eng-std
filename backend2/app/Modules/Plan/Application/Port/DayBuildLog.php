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
 * not an error, and the learner sees nothing of it. The lesson's build writes here too (наряд LANG-1b §3): a construction
 * whose talk hint is put together because the lesson has no line of it.
 */
interface DayBuildLog
{
    /**
     * THE STOP SIGNAL OF «ФРАЗЫ»: the ladder spent every rung and the stage is still over its ceiling
     * ({@see PhrasesDeal::overCeiling()}). The day was dealt anyway.
     */
    public function phrasesOverCeiling(PlanId $plan, PlanDayId $day, int $dayNumber, PlanSceneId $scene, PhrasesDeal $deal): void;

    /**
     * THE TALK'S HINT PUT TOGETHER (наряд LANG-1b §3): the lesson written for the scene has frames no learner line of it stands
     * on, so for these constructions the talk has no line of the lesson to offer and offers the native frame said with its
     * value — which may not agree. Written once, when the lesson is accepted.
     *
     * @param  list<string>  $refs  the phrases' refs (`p3`)
     */
    public function hintsAssembled(PlanId $plan, PlanSceneId $scene, array $refs): void;
}

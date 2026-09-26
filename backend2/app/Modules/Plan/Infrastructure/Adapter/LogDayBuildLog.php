<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Adapter;

use App\Modules\Plan\Application\Port\DayBuildLog;
use App\Modules\Plan\Domain\Assembly\PhrasesDeal;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use Illuminate\Support\Facades\Log;

/**
 * The day's build log is the application log, one warning per signal (`plan.phrases_over_ceiling`) with every number the
 * ladder cut by — the stage after each rung and what each frame with a window kept — so the line alone answers «на
 * сколько и почему» without dealing the day again; `plan.hint_assembled` names the phrases of a lesson whose talk hint is
 * the frame put together with its value (наряд LANG-1b §3).
 */
final class LogDayBuildLog implements DayBuildLog
{
    public function phrasesOverCeiling(PlanId $plan, PlanDayId $day, int $dayNumber, PlanSceneId $scene, PhrasesDeal $deal): void
    {
        Log::warning('plan.phrases_over_ceiling', [
            'plan_id' => $plan->value,
            'day_id' => $day->value,
            'day' => $dayNumber,
            'scene_id' => $scene->value,
            'seconds' => $deal->seconds,
            'budget' => $deal->budget,
            'over' => $deal->seconds - $deal->budget,
            'cards' => count($deal->drafts),
            'rungs' => $deal->rungs,
            'frames' => $deal->frames,
        ]);
    }

    public function hintsAssembled(PlanId $plan, PlanSceneId $scene, array $refs): void
    {
        Log::warning('plan.hint_assembled', [
            'plan_id' => $plan->value,
            'scene_id' => $scene->value,
            'phrases' => $refs,
        ]);
    }
}

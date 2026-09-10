<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Blueprint;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Blueprint\SceneBrief;
use App\Modules\Plan\Domain\Check\BlueprintCheck;
use App\Modules\Plan\Domain\Check\BlueprintContext;

/** 3–4 goals per scene. `drop` cuts the extras; a shortage is counted and kept (nothing to cut). */
final class GoalsCountCheck implements BlueprintCheck
{
    public const MIN = 3;

    public const MAX = 4;

    public function name(): string
    {
        return 'goals_count';
    }

    public function switchable(): bool
    {
        return true;
    }

    public function violations(Blueprint $blueprint, BlueprintContext $context): array
    {
        $out = [];
        foreach ($blueprint->scenes as $scene) {
            $n = count($scene->goalsNative);
            if ($n < self::MIN || $n > self::MAX) {
                $out[] = "scene {$scene->order}: {$n} goals";
            }
        }

        return $out;
    }

    public function drop(Blueprint $blueprint, BlueprintContext $context): Blueprint
    {
        return $blueprint->withScenes(array_map(
            static fn (SceneBrief $s): SceneBrief => count($s->goalsNative) > self::MAX
                ? $s->withGoals(array_slice($s->goalsNative, 0, self::MAX))
                : $s,
            $blueprint->scenes,
        ));
    }
}

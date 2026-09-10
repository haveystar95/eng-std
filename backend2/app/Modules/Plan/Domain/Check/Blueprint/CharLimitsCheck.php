<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Blueprint;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Check\BlueprintCheck;
use App\Modules\Plan\Domain\Check\BlueprintContext;

/** The character limits of the screen strings — counted, never acted on; the client ellipsises. */
final class CharLimitsCheck implements BlueprintCheck
{
    public const SCENE_TITLE = 18;

    public const TEACHES = 34;

    public const GOAL = 30;

    public const PLAN_TITLE = 24;

    public function name(): string
    {
        return 'char_limits';
    }

    public function switchable(): bool
    {
        return false;
    }

    public function violations(Blueprint $blueprint, BlueprintContext $context): array
    {
        $out = [];
        if ($blueprint->titles !== null && mb_strlen($blueprint->titles->titleNative) > self::PLAN_TITLE) {
            $out[] = 'plan title_native over '.self::PLAN_TITLE;
        }
        foreach ($blueprint->scenes as $scene) {
            if (mb_strlen($scene->titleNative) > self::SCENE_TITLE) {
                $out[] = "scene {$scene->order}: title_native over ".self::SCENE_TITLE;
            }
            if (mb_strlen($scene->teachesNative) > self::TEACHES) {
                $out[] = "scene {$scene->order}: teaches_native over ".self::TEACHES;
            }
            foreach ($scene->goalsNative as $i => $goal) {
                if (mb_strlen($goal) > self::GOAL) {
                    $out[] = "scene {$scene->order}: goal {$i} over ".self::GOAL;
                }
            }
        }

        return $out;
    }

    public function drop(Blueprint $blueprint, BlueprintContext $context): Blueprint
    {
        return $blueprint;
    }
}

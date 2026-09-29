<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Blueprint;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Blueprint\SurvivalSet;
use App\Modules\Plan\Domain\Check\BlueprintCheck;
use App\Modules\Plan\Domain\Check\BlueprintContext;

/**
 * THE SHAPE OF A SCENE'S SURVIVAL SET (`plan-builder-v2.1`, STEP 4; наряд GEN-4) — what the day is built from: `must_say` has
 * 6 to 8 items, `must_understand` 4 to 5, and every `must_say` item carries its slot after «— slot:». A set out of this shape
 * gives the skeleton no set to turn into frames and lines — the check ships as `gate` (`plan.checks.plan.survival_set`):
 * the plan is asked once more with the finding quoted. Everything else about the set is a warning of its own check.
 */
final class SurvivalSetCheck implements BlueprintCheck
{
    public const SAY_MIN = 6;

    public const SAY_MAX = 8;

    public const UNDERSTAND_MIN = 4;

    public const UNDERSTAND_MAX = 5;

    public function name(): string
    {
        return 'survival_set';
    }

    public function switchable(): bool
    {
        return true;
    }

    public function violations(Blueprint $blueprint, BlueprintContext $context): array
    {
        $out = [];
        foreach ($blueprint->scenes as $scene) {
            $set = $scene->survival;
            $say = count($set->mustSay);
            if ($say < self::SAY_MIN || $say > self::SAY_MAX) {
                $out[] = "scene {$scene->order}: must_say has {$say} items (".self::SAY_MIN.'–'.self::SAY_MAX.')';
            }
            $understand = count($set->mustUnderstand);
            if ($understand < self::UNDERSTAND_MIN || $understand > self::UNDERSTAND_MAX) {
                $out[] = "scene {$scene->order}: must_understand has {$understand} items (".self::UNDERSTAND_MIN.'–'.self::UNDERSTAND_MAX.')';
            }
            foreach ($set->mustSay as $index => $item) {
                if (! $item['marked']) {
                    $out[] = "scene {$scene->order}: must_say ".($index + 1)." «{$item['text']}» has no «".SurvivalSet::SLOT_MARK.'»';
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

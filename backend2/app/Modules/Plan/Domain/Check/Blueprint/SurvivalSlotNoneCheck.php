<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Blueprint;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Check\BlueprintCheck;
use App\Modules\Plan\Domain\Check\BlueprintContext;

/**
 * At most two `must_say` items of a scene end with «— slot: none» (`plan-builder-v2.1`, STEP 4): a frame with nothing to
 * swap teaches one sentence, not a pattern. A warning (наряд GEN-4).
 */
final class SurvivalSlotNoneCheck implements BlueprintCheck
{
    public const MAX = 2;

    public function name(): string
    {
        return 'survival_slot_none';
    }

    public function switchable(): bool
    {
        return false;
    }

    public function violations(Blueprint $blueprint, BlueprintContext $context): array
    {
        $out = [];
        foreach ($blueprint->scenes as $scene) {
            $none = count(array_filter($scene->survival->mustSay, static fn (array $item): bool => $item['marked'] && $item['slot'] === null));
            if ($none > self::MAX) {
                $out[] = "scene {$scene->order}: {$none} must_say items end with «— slot: none» (at most ".self::MAX.')';
            }
        }

        return $out;
    }

    public function drop(Blueprint $blueprint, BlueprintContext $context): Blueprint
    {
        return $blueprint;
    }
}

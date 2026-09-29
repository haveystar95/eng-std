<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Blueprint;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Check\BlueprintCheck;
use App\Modules\Plan\Domain\Check\BlueprintContext;

/**
 * At least two `must_say` items are questions the learner asks — they start with «ask» (`plan-builder-v2.1`, STEP 4: «a
 * person who only answers is not having a conversation»). A warning (наряд GEN-4).
 */
final class SurvivalAsksCheck implements BlueprintCheck
{
    public const MIN = 2;

    public function name(): string
    {
        return 'survival_asks';
    }

    public function switchable(): bool
    {
        return false;
    }

    public function violations(Blueprint $blueprint, BlueprintContext $context): array
    {
        $out = [];
        foreach ($blueprint->scenes as $scene) {
            $asks = count(array_filter($scene->survival->mustSay, static fn (array $item): bool => SurvivalWords::verb($item['text']) === 'ask'));
            if ($asks < self::MIN) {
                $out[] = "scene {$scene->order}: {$asks} must_say items start with ask (at least ".self::MIN.')';
            }
        }

        return $out;
    }

    public function drop(Blueprint $blueprint, BlueprintContext $context): Blueprint
    {
        return $blueprint;
    }
}

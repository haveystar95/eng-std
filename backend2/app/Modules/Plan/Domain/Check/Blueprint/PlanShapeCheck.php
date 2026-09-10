<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Blueprint;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Blueprint\SceneBrief;
use App\Modules\Plan\Domain\Check\BlueprintCheck;
use App\Modules\Plan\Domain\Check\BlueprintContext;

/** Exactly SCENES_COUNT scenes, ordered 1..N (continuing after the existing ones on an extension). */
final class PlanShapeCheck implements BlueprintCheck
{
    public function name(): string
    {
        return 'plan_shape';
    }

    public function switchable(): bool
    {
        return true;
    }

    public function violations(Blueprint $blueprint, BlueprintContext $context): array
    {
        $out = [];
        $count = count($blueprint->scenes);
        if ($count !== $context->scenesCount) {
            $out[] = "{$count} scenes instead of {$context->scenesCount}";
        }
        $orders = array_map(static fn (SceneBrief $s): int => $s->order, $blueprint->scenes);
        $expected = range($context->existingScenes + 1, $context->existingScenes + $count);
        if ($count > 0 && $orders !== $expected) {
            $out[] = 'order is '.implode(',', $orders).' instead of '.implode(',', $expected);
        }

        return $out;
    }

    public function drop(Blueprint $blueprint, BlueprintContext $context): Blueprint
    {
        return $blueprint;
    }
}

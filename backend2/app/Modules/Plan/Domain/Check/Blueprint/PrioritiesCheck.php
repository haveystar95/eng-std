<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Blueprint;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Blueprint\SceneBrief;
use App\Modules\Plan\Domain\Check\BlueprintCheck;
use App\Modules\Plan\Domain\Check\BlueprintContext;
use App\Modules\Plan\Domain\ValueObject\SceneKind;

/**
 * Priorities are unique and exactly one scene is priority 1. `drop` renumbers by order, with the
 * core — the first scene of kind `situation` — as 1: the model's ranking is not trusted anyway,
 * the shortening rule (`docs/plan-v2.md` §7) is.
 */
final class PrioritiesCheck implements BlueprintCheck
{
    public function name(): string
    {
        return 'priorities';
    }

    public function switchable(): bool
    {
        return true;
    }

    public function violations(Blueprint $blueprint, BlueprintContext $context): array
    {
        $priorities = array_map(static fn (SceneBrief $s): int => $s->priority, $blueprint->scenes);
        $out = [];
        if (count(array_unique($priorities)) !== count($priorities)) {
            $out[] = 'priorities are not unique: '.implode(',', $priorities);
        }
        $ones = count(array_filter($priorities, static fn (int $p): bool => $p === 1));
        if ($priorities !== [] && $ones !== 1) {
            $out[] = "{$ones} scenes with priority 1";
        }

        return $out;
    }

    public function drop(Blueprint $blueprint, BlueprintContext $context): Blueprint
    {
        return $blueprint->withScenes(self::renumbered($blueprint->scenes));
    }

    /**
     * @param  list<SceneBrief>  $scenes
     * @return list<SceneBrief>
     */
    public static function renumbered(array $scenes): array
    {
        $core = null;
        foreach ($scenes as $index => $scene) {
            if ($scene->kind === SceneKind::Situation) {
                $core = $index;
                break;
            }
        }
        $core ??= 0;

        $next = 2;
        $out = [];
        foreach ($scenes as $index => $scene) {
            $out[] = $scene->withPriority($index === $core ? 1 : $next++);
        }

        return $out;
    }
}

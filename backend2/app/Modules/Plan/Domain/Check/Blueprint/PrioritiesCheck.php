<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Blueprint;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Blueprint\SceneBrief;
use App\Modules\Plan\Domain\Check\BlueprintCheck;
use App\Modules\Plan\Domain\Check\BlueprintContext;
use App\Modules\Plan\Domain\ValueObject\SceneKind;

/**
 * Priorities are unique and exactly one scene is priority 1 — on a plan built from the start. With EXISTING_SCENES
 * (`plan-builder-v2.1`, STEP 5: «with EXISTING_SCENES the priorities continue after the existing scenes») the new scenes
 * take the priorities after the existing ones, in any order — three existing scenes: 4, 5 — and none of them is the core.
 * `drop` renumbers by order: from the start with the core — the first scene of kind `situation` — as 1; after the existing
 * scenes, in order after them. The model's ranking is not trusted anyway, the shortening rule (`docs/plan-v2.md` §7) is.
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
        if ($priorities === []) {
            return [];
        }
        if ($context->existingScenes > 0) {
            $ranked = $priorities;
            sort($ranked);
            $wanted = range($context->existingScenes + 1, $context->existingScenes + count($priorities));

            return $ranked === $wanted
                ? []
                : ['priorities are '.implode(',', $priorities).' instead of '.implode(',', $wanted).' (in any order) after '.$context->existingScenes.' existing scenes'];
        }

        $out = [];
        if (count(array_unique($priorities)) !== count($priorities)) {
            $out[] = 'priorities are not unique: '.implode(',', $priorities);
        }
        $ones = count(array_filter($priorities, static fn (int $p): bool => $p === 1));
        if ($ones !== 1) {
            $out[] = "{$ones} scenes with priority 1";
        }

        return $out;
    }

    public function drop(Blueprint $blueprint, BlueprintContext $context): Blueprint
    {
        return $blueprint->withScenes(self::renumbered($blueprint->scenes, $context->existingScenes));
    }

    /**
     * @param  list<SceneBrief>  $scenes
     * @return list<SceneBrief>
     */
    public static function renumbered(array $scenes, int $existing = 0): array
    {
        if ($existing > 0) {
            return array_map(
                static fn (SceneBrief $scene, int $index): SceneBrief => $scene->withPriority($existing + $index + 1),
                $scenes,
                array_keys($scenes),
            );
        }

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

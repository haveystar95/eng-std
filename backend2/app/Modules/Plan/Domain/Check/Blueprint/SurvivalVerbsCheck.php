<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Blueprint;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Check\BlueprintCheck;
use App\Modules\Plan\Domain\Check\BlueprintContext;

/**
 * Every `must_say` item starts with say, ask, answer, confirm, explain or give (`plan-builder-v2.1`, STEP 4) — the verb says
 * what kind of frame the item becomes. A warning (наряд GEN-4): the skeleton repairs an item it cannot say silently.
 */
final class SurvivalVerbsCheck implements BlueprintCheck
{
    public function name(): string
    {
        return 'survival_verbs';
    }

    public function switchable(): bool
    {
        return false;
    }

    public function violations(Blueprint $blueprint, BlueprintContext $context): array
    {
        $out = [];
        foreach ($blueprint->scenes as $scene) {
            foreach ($scene->survival->mustSay as $index => $item) {
                if (! in_array(SurvivalWords::verb($item['text']), SurvivalWords::SAY_VERBS, true)) {
                    $out[] = "scene {$scene->order}: must_say ".($index + 1)." «{$item['text']}» starts with no verb of the list";
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

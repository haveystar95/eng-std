<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Blueprint;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Check\BlueprintCheck;
use App\Modules\Plan\Domain\Check\BlueprintContext;

/**
 * THE CHARACTER LIMITS OF THE SCREEN STRINGS (`plan-builder-v2.1`: «character limits are hard») — the day's name in both
 * languages, the line under it, each goal, the plan's name. Counted here over the model's answer; a line over its limit is
 * not a new plan call but ONE line shortened by a cheap model (наряд GEN-4, {@see \App\Modules\Plan\Application\Service\PlanLineRepairer}):
 * the same limits, read by {@see over()}.
 */
final class CharLimitsCheck implements BlueprintCheck
{
    public const SCENE_TITLE = 18;

    public const SCENE_TITLE_TARGET = 24;

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
        return array_map(
            static fn (array $line): string => ($line['scene'] === null ? 'plan' : "scene {$line['scene']}").': '
                .$line['field'].($line['field'] === 'goals_native' ? " {$line['index']}" : '').' over '.$line['limit']
                .' («'.$line['line'].'», '.mb_strlen($line['line']).')',
            self::over($blueprint),
        );
    }

    public function drop(Blueprint $blueprint, BlueprintContext $context): Blueprint
    {
        return $blueprint;
    }

    /**
     * Every screen line of the answer longer than its limit, in the order of the answer: the plan's name, then scene by
     * scene its name in both languages, the line under it and its goals. `scene` — the scene's order, null for the plan;
     * `index` — the goal's place in `goals_native`; `native` — the line is in the learner's language (else the target's).
     *
     * @return list<array{scene: int|null, field: string, index: int, line: string, limit: int, native: bool}>
     */
    public static function over(Blueprint $blueprint): array
    {
        $out = [];
        if ($blueprint->titles !== null && mb_strlen($blueprint->titles->titleNative) > self::PLAN_TITLE) {
            $out[] = ['scene' => null, 'field' => 'title_native', 'index' => 0, 'line' => $blueprint->titles->titleNative, 'limit' => self::PLAN_TITLE, 'native' => true];
        }
        foreach ($blueprint->scenes as $scene) {
            foreach ([
                ['title_native', $scene->titleNative, self::SCENE_TITLE, true],
                ['title_target', $scene->titleTarget, self::SCENE_TITLE_TARGET, false],
                ['teaches_native', $scene->teachesNative, self::TEACHES, true],
            ] as [$field, $line, $limit, $native]) {
                if (mb_strlen($line) > $limit) {
                    $out[] = ['scene' => $scene->order, 'field' => $field, 'index' => 0, 'line' => $line, 'limit' => $limit, 'native' => $native];
                }
            }
            foreach ($scene->goalsNative as $index => $goal) {
                if (mb_strlen($goal) > self::GOAL) {
                    $out[] = ['scene' => $scene->order, 'field' => 'goals_native', 'index' => $index, 'line' => $goal, 'limit' => self::GOAL, 'native' => true];
                }
            }
        }

        return $out;
    }
}

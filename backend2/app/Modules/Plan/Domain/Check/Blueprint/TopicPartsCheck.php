<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Blueprint;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Check\BlueprintCheck;
use App\Modules\Plan\Domain\Check\BlueprintContext;

/**
 * Every scene's brief is the three labelled lines of `plan-builder-v2.1` (STEP 5, `topic_description`), in its order, each
 * on a line of its own: «Situation: …», «Learner: … Partner: …», «Not in this scene: …» — the lines the day's two stages
 * read as TOPIC_DESCRIPTION. What the learner and the partner say is no part of it any more: that is the survival set.
 */
final class TopicPartsCheck implements BlueprintCheck
{
    public const SITUATION = 'Situation:';

    public const LEARNER = 'Learner:';

    public const PARTNER = 'Partner:';

    public const NOT_HERE = 'Not in this scene:';

    public function name(): string
    {
        return 'topic_parts';
    }

    public function switchable(): bool
    {
        return true;
    }

    public function violations(Blueprint $blueprint, BlueprintContext $context): array
    {
        $out = [];
        foreach ($blueprint->scenes as $scene) {
            $lines = array_values(array_filter(
                array_map('trim', explode("\n", $scene->topicDescription)),
                static fn (string $line): bool => $line !== '',
            ));
            $shaped = count($lines) === 3
                && str_starts_with($lines[0], self::SITUATION)
                && str_starts_with($lines[1], self::LEARNER) && str_contains($lines[1], self::PARTNER)
                && str_starts_with($lines[2], self::NOT_HERE);
            if (! $shaped) {
                $out[] = "scene {$scene->order}: brief is not the three lines «Situation:», «Learner: … Partner: …», «Not in this scene:» (".count($lines).' lines)';
            }
        }

        return $out;
    }

    public function drop(Blueprint $blueprint, BlueprintContext $context): Blueprint
    {
        return $blueprint;
    }
}

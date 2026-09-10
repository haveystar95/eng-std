<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Blueprint;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Check\BlueprintCheck;
use App\Modules\Plan\Domain\Check\BlueprintContext;

/** Every scene's brief carries the five labelled parts the lesson generator reads. */
final class TopicPartsCheck implements BlueprintCheck
{
    public const LABELS = ['Situation:', 'Learner:', 'Learner must be able to:', 'Partner will:', 'Not in this scene:'];

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
            foreach (self::LABELS as $label) {
                if (mb_stripos($scene->topicDescription, $label) === false) {
                    $out[] = "scene {$scene->order}: brief has no «{$label}» part";
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

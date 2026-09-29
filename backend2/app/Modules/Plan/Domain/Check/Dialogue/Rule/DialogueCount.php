<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;
use App\Modules\Plan\Domain\Lesson\DialogueExchange;

/**
 * `dialogue.count` — FATAL. The dialogue has exactly DIALOGUE_COUNT exchanges — the number the server counted off the skeleton
 * — and their steps run 1…N in order, without gaps.
 */
final class DialogueCount implements DialogueRule
{
    public const CODE = 'dialogue.count';

    public function code(): string
    {
        return self::CODE;
    }

    public function fatal(): bool
    {
        return true;
    }

    public function findings(Dialogue $dialogue, DialogueContext $context): array
    {
        $out = [];
        $count = count($dialogue->exchanges);
        if ($count !== $context->dialogueCount) {
            $out[] = new LessonViolation(self::CODE, 'dialogue', "{$count} exchanges instead of {$context->dialogueCount}");
        }
        $steps = array_map(static fn (DialogueExchange $e): int => $e->step(), $dialogue->exchanges);
        if ($steps !== [] && $steps !== range(1, $count)) {
            $out[] = new LessonViolation(self::CODE, 'dialogue', 'the steps are '.implode(',', $steps).', not 1…'.$count);
        }

        return $out;
    }
}

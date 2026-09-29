<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;

/**
 * `listening.shape` — FATAL (a shape guard beside the order's list, наряд GEN-4: a listening card is dealt from its right
 * option). A question has its text, exactly three options, none empty, and `correct_option_index` names one of them.
 */
final class ListeningShape implements DialogueRule
{
    public const CODE = 'listening.shape';

    public const OPTIONS = 3;

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
        foreach ($dialogue->listening as $index => $question) {
            $empty = array_filter($question->optionsNative, static fn (string $o): bool => trim($o) === '');
            if (trim($question->textNative) === '' || count($question->optionsNative) !== self::OPTIONS || $empty !== [] || $question->correctOption() === null) {
                $out[] = new LessonViolation(self::CODE, 'L'.($index + 1), count($question->optionsNative).' options, '.count($empty)." empty, the right one at {$question->correctOptionIndex}");
            }
        }

        return $out;
    }
}

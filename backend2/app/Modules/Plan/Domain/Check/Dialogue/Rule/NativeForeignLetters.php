<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;

/**
 * `native.foreign_letters` — a warning (TEXT QUALITY; наряд GEN-4: «латиница в родных полях»). The native fields the dialogue
 * writes itself — a check's question, options and explanation, a listening question, its options and explanation — are in
 * the letters of the learner's alphabet (the native pack's `script_letters`): a Russian explanation with a Romanian word in it
 * is the target language in a field the learner reads as their own.
 */
final class NativeForeignLetters implements DialogueRule
{
    public const CODE = 'native.foreign_letters';

    public function code(): string
    {
        return self::CODE;
    }

    public function fatal(): bool
    {
        return false;
    }

    public function findings(Dialogue $dialogue, DialogueContext $context): array
    {
        $words = $context->nativeReading('script_letters');
        if ($words === null) {
            return [];
        }
        $fields = [];
        foreach ($dialogue->exchanges as $e) {
            $check = $e->exchange->check;
            $fields[] = ['x'.$e->step().'.check', [$check->textNative, $check->explanationNative, ...array_map(static fn ($o): string => $o->textNative, $check->options)]];
        }
        foreach ($dialogue->listening as $index => $question) {
            $fields[] = ['L'.($index + 1), [$question->textNative, $question->explanationNative, ...$question->optionsNative]];
        }

        $out = [];
        foreach ($fields as [$address, $texts]) {
            foreach ($texts as $text) {
                $foreign = $words->foreignLetters($text);
                if ($foreign !== []) {
                    $out[] = new LessonViolation(self::CODE, $address, "«{$text}» has letters of another alphabet: ".implode(' ', $foreign));
                    break;
                }
            }
        }

        return $out;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;

/**
 * `check.shape` — FATAL (a shape guard beside the order's list, наряд GEN-4: a check card is dealt from its right option).
 * A check that is there has exactly three options, each written in both languages, and `correct_option_index` names one of
 * them.
 */
final class CheckShape implements DialogueRule
{
    public const CODE = 'check.shape';

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
        foreach ($dialogue->exchanges as $e) {
            $check = $e->exchange->check;
            if ($check->options === []) {
                continue;
            }
            $empty = array_filter($check->options, static fn ($o): bool => trim($o->textTarget) === '' || trim($o->textNative) === '');
            if (count($check->options) !== self::OPTIONS || $empty !== [] || $check->correctOption() === null) {
                $out[] = new LessonViolation(self::CODE, 'x'.$e->step().'.check', count($check->options).' options, '.count($empty)." empty, the right one at {$check->correctOptionIndex}");
            }
        }

        return $out;
    }
}

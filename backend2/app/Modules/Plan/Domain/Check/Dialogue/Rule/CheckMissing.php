<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;

/** `check.missing` — FATAL. Every exchange has its check: a question, in either language, and options to answer it with. */
final class CheckMissing implements DialogueRule
{
    public const CODE = 'check.missing';

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
            if ((trim($check->textTarget) === '' && trim($check->textNative) === '') || $check->options === []) {
                $out[] = new LessonViolation(self::CODE, 'x'.$e->step().'.check', 'the exchange has no check');
            }
        }

        return $out;
    }
}

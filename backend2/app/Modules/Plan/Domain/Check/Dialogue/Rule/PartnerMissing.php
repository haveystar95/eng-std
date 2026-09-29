<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;

/** `partner.missing` — FATAL. Every partner line of the skeleton is said: some exchange carries it (`partner_line`). */
final class PartnerMissing implements DialogueRule
{
    public const CODE = 'partner.missing';

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
        foreach ($context->skeleton->partnerLines as $line) {
            if ($dialogue->carrying($line->id) === []) {
                $out[] = new LessonViolation(self::CODE, 'dialogue', "the partner line {$line->id} «{$line->textTarget}» is in no exchange");
            }
        }

        return $out;
    }
}

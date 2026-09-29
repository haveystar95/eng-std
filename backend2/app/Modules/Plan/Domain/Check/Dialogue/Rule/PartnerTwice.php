<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;
use App\Modules\Plan\Domain\Lesson\DialogueExchange;

/** `partner.twice` — FATAL. A partner line of the skeleton is said once: no two exchanges carry it. */
final class PartnerTwice implements DialogueRule
{
    public const CODE = 'partner.twice';

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
            $carrying = $dialogue->carrying($line->id);
            foreach (array_slice($carrying, 1) as $again) {
                $steps = implode(', ', array_map(static fn (DialogueExchange $e): string => 'x'.$e->step(), $carrying));
                $out[] = new LessonViolation(self::CODE, 'x'.$again->step(), "the partner line {$line->id} is said in {$steps}");
            }
        }

        return $out;
    }
}

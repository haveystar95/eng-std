<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;

/**
 * `partner.changed` — FATAL. An exchange that carries a partner line (`partner_line`) says it as the skeleton spells it:
 * A's message is the line's text in both languages, character for character (the spaces around aside); a `partner_line`
 * that names no line of the skeleton is no line at all.
 */
final class PartnerChanged implements DialogueRule
{
    public const CODE = 'partner.changed';

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
            if ($e->partnerLine === null) {
                continue;
            }
            $line = $context->skeleton->partnerLine($e->partnerLine);
            $partner = $e->exchange->partner();
            if ($line === null) {
                $out[] = new LessonViolation(self::CODE, 'A'.$e->step(), "partner_line «{$e->partnerLine}» is no line of the skeleton");
            } elseif ($partner === null || trim($partner->textTarget) !== trim($line->textTarget) || trim($partner->textNative) !== trim($line->textNative)) {
                $said = $partner === null ? 'nothing' : "«{$partner->textTarget}» / «{$partner->textNative}»";
                $out[] = new LessonViolation(self::CODE, 'A'.$e->step(), "{$line->id} is «{$line->textTarget}» / «{$line->textNative}», A says {$said}");
            }
        }

        return $out;
    }
}

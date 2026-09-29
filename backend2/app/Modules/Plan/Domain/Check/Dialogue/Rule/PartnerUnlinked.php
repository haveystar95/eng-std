<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;

/**
 * `partner.unlinked` — FATAL. The dialogue writes an A line of its own in two places only: the rescue's repeat, and the one A
 * line of a frame no partner line pairs with. Anywhere else an A message without `partner_line` is a line the skeleton does
 * not have.
 */
final class PartnerUnlinked implements DialogueRule
{
    public const CODE = 'partner.unlinked';

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
            $partner = $e->exchange->partner();
            if ($partner === null || $e->partnerLine !== null || $e->exchange->kind === ExchangeKind::Rescue) {
                continue;
            }
            $phraseId = $e->exchange->learner()?->phraseId;
            $frame = $phraseId === null ? null : $context->skeleton->frame($phraseId);
            if ($frame !== null && ! $context->skeleton->isPaired($frame)) {
                continue;
            }
            $out[] = new LessonViolation(self::CODE, 'A'.$e->step(), "«{$partner->textTarget}» is no partner line of the skeleton, and the exchange is neither the rescue nor a frame without a partner line");
        }

        return $out;
    }
}

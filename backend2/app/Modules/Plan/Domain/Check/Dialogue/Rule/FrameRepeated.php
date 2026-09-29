<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;
use App\Modules\Plan\Domain\Lesson\DialogueExchange;

/**
 * `frame.repeated` — FATAL (наряд GEN-4b §2: a frame is said again only where the skeleton asks for it). A learner line says a
 * frame an earlier learner line already said, in an exchange that is neither
 *
 *  - the exchange of a REMAINDER line — a partner line with an empty `pairs_with`, which the learner answers with a frame
 *    already said, with another filler (`lesson_dialogue.v1.1`), nor
 *  - the exchange of a partner line that pairs with this very frame (two questions the skeleton answers with one frame).
 *
 * Anywhere else a repeat is a line the skeleton does not ask for — the dialogue padding the count, or answering a partner line
 * with a frame that is not its pair. The gate run of GEN-4 (gpt-5.4, day 02) said «It gets worse with ___» and «Can my child
 * ___?» a second time to two A lines of its own; `partner.unlinked` caught the A lines, this catches the repeat itself.
 */
final class FrameRepeated implements DialogueRule
{
    public const CODE = 'frame.repeated';

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
        $first = [];
        $out = [];
        foreach ($dialogue->exchanges as $e) {
            $phraseId = $e->exchange->learner()?->phraseId;
            if ($phraseId === null) {
                continue;
            }
            if (isset($first[$phraseId]) && ! self::askedFor($e, $phraseId, $context)) {
                $out[] = new LessonViolation(self::CODE, 'x'.$e->step(), "the frame {$phraseId} is said again (first in x{$first[$phraseId]}): a frame is said twice only to a line left over with no pair, or to a line paired with it");
            }
            $first[$phraseId] ??= $e->step();
        }

        return $out;
    }

    /** Does the exchange's partner line ask for this frame — a remainder line, or a line paired with it? */
    private static function askedFor(DialogueExchange $e, string $phraseId, DialogueContext $context): bool
    {
        $line = $e->partnerLine === null ? null : $context->skeleton->partnerLine($e->partnerLine);
        if ($line === null) {
            return false;
        }
        if ($line->pairsWith === []) {
            return true;
        }
        $frame = $context->skeleton->frame($phraseId);

        return $frame !== null && array_intersect($line->pairsWith, $frame->mustSay) !== [];
    }
}

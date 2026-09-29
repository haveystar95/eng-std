<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Dialogue\Rule;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueContext;
use App\Modules\Plan\Domain\Check\Dialogue\DialogueRule;
use App\Modules\Plan\Domain\Check\Dialogue\LearnerLine;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Dialogue;

/**
 * `line.ne_frame` — FATAL. The learner line of an answer or an ask exchange IS its frame said with the filler it names, in
 * both languages, character for character — a glue before it that ends in a comma, the case of the frame's first letter and
 * the closing mark aside ({@see LearnerLine}). A line on no frame of the skeleton, or with a filler not of its frame, is found
 * by the rules of its own.
 */
final class LineNeFrame implements DialogueRule
{
    public const CODE = 'line.ne_frame';

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
            $line = $e->exchange->learner();
            if ($line === null || ! $e->exchange->kind->takesFrame() || $line->phraseId === null) {
                continue;
            }
            $frame = $context->skeleton->frame($line->phraseId)?->phrase;
            if ($frame === null) {
                continue;
            }
            $filler = $frame->filler($line->filler);
            if ($frame->slot !== null && $filler === null) {
                continue;
            }
            foreach (['target' => $line->textTarget, 'native' => $line->textNative] as $side => $text) {
                $core = LearnerLine::core($frame, $filler, $side);
                if ($core !== null && ! LearnerLine::says($text, $core)) {
                    $out[] = new LessonViolation(self::CODE, 'B'.$e->step(), "the {$side} line «{$text}» is not «{$core}» ({$frame->id} with «".($line->filler ?? '—').'»)');
                    break;
                }
            }
        }

        return $out;
    }
}

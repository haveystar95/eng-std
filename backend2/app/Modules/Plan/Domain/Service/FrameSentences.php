<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Service;

use App\Modules\Plan\Domain\Check\Language\SentenceEnds;
use App\Modules\Plan\Domain\Lesson\Lesson;

/**
 * EVERY SENTENCE OF A LESSON THAT IS A FRAME SAID WITH ONE OF ITS FILLERS (наряд FIX-4 §6), put together by the one rule
 * of each language's sentence ends ({@see FrameText::fill()}): the learner's lines that stand on a frame, their glue kept
 * («Okay, I can come at 3 p.m.»), each frame said with each of its fillers, and the native sentence of each filler.
 *
 * What a card of a day dealt before the rule may hold with an abbreviation's dot doubled («…3 p.m..») is one of these
 * with a dot too many — and one of these is what it is rebuilt to (`plan:rebuild-card-texts`).
 */
final class FrameSentences
{
    /** @return list<string> */
    public static function of(Lesson $lesson, ?SentenceEnds $target, ?SentenceEnds $native): array
    {
        $out = [];
        foreach ($lesson->phrases as $phrase) {
            if (! FrameText::hasSlot($phrase->frameTarget)) {
                continue;
            }
            foreach ($phrase->fillers() as $filler) {
                $out[] = FrameText::fill($phrase->frameTarget, $filler->target, $target);
                if (FrameText::hasSlot($phrase->frameNative) && trim($filler->native) !== '') {
                    $out[] = FrameText::fill($phrase->frameNative, $filler->native, $native);
                }
            }
            foreach ($lesson->linesOf($phrase->id) as ['message' => $message]) {
                $said = FrameText::line($phrase, $message->textTarget);
                if (! $said['matches'] || $said['filler'] === null) {
                    continue;
                }
                $core = FrameText::fill($phrase->frameTarget, $said['filler']->target, $target);
                // After the glue the frame goes on in the line's own case: «Okay, he will rest …».
                $letter = mb_substr(trim($message->textTarget), mb_strlen($said['glue']), 1);
                if ($said['glue'] !== '' && $letter !== '' && mb_strtolower($letter) === $letter) {
                    $core = mb_strtolower(mb_substr($core, 0, 1)).mb_substr($core, 1);
                }
                $out[] = FrameText::withEndMarkOf($said['glue'].$core, $message->textTarget);
            }
        }

        return array_values(array_unique($out));
    }
}

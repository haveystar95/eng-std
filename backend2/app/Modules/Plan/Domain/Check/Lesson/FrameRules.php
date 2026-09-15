<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\Language\LanguageSide;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\Service\Words;

/**
 * THE FRAMES (`lesson_day.v4.5`, FRAMES): as many as half to all of the answer/ask exchanges, each said at least
 * once, at most seven words outside the slot, at most a third without a slot, a native rendering with no
 * «в/на»-style alternatives that ends the way the frame ends; a frame that stands alone — no pronoun it leans on
 * without a thing it stands for; a native frame with no word that agrees with its slot («___ разрешён?»).
 *
 * The pronoun is read by the target's pack, the agreement by the learner's language's, the closing marks by both.
 */
final class FrameRules implements LessonRule
{
    public const MAX_WORDS = 7;

    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $out = [];
        $frames = count($answer->phrases);
        $framed = count(array_filter($answer->exchanges, static fn (Exchange $e): bool => $e->kind->takesFrame()));
        if ($frames > $framed) {
            $out[] = new LessonViolation(LessonCodes::FRAME_COUNT, 'lesson', "{$frames} frames for {$framed} answer/ask exchanges (at most all of them)");
        } elseif ($frames * 2 < $framed) {
            $out[] = new LessonViolation(LessonCodes::FRAME_COUNT, 'lesson', "{$frames} frames for {$framed} answer/ask exchanges (at least half)");
        }

        // Every side a check reads is asked before any frame is read, so a lesson skips what its languages lack
        // whatever it says.
        $targetMarks = $context->reads(LessonCodes::FRAME_NATIVE_PUNCT, LanguageSide::Target, 'sentence_ends');
        $nativeMarks = $context->reads(LessonCodes::FRAME_NATIVE_PUNCT, LanguageSide::Native, 'sentence_ends');
        $pronouns = $context->reads(LessonCodes::FRAME_UNRESOLVED_PRONOUN, LanguageSide::Target, 'unresolved_pronouns', 'function_words');
        $agreement = $context->reads(LessonCodes::FRAME_NATIVE_AGREEMENT, LanguageSide::Native, 'agreement');

        $withoutSlot = 0;
        foreach ($answer->phrases as $phrase) {
            if ($answer->linesOf($phrase->id) === []) {
                $out[] = new LessonViolation(LessonCodes::FRAME_UNUSED, $phrase->id, "no learner line stands on «{$phrase->frameTarget}»");
            }

            $words = Words::count((string) preg_replace(FrameText::SLOT_PATTERN, ' ', $phrase->frameTarget));
            if ($words > self::MAX_WORDS) {
                $out[] = new LessonViolation(LessonCodes::FRAME_TOO_LONG, $phrase->id, "«{$phrase->frameTarget}» has {$words} words outside the slot (max ".self::MAX_WORDS.')');
            }

            if ($phrase->slot === null || ! FrameText::hasSlot($phrase->frameTarget)) {
                $withoutSlot++;
            }

            if (preg_match('/\p{L}\s*\/\s*\p{L}|\(\p{L}{1,3}\)/u', $phrase->frameNative) === 1) {
                $out[] = new LessonViolation(LessonCodes::FRAME_NATIVE_ALTERNATIVES, $phrase->id, "«{$phrase->frameNative}» writes alternatives inside the frame");
            }

            if ($targetMarks && $nativeMarks) {
                $target = $context->targetWords();
                $native = $context->nativeWords();
                if ($target->terminalKind($phrase->frameTarget) !== $native->terminalKind($phrase->frameNative)) {
                    $out[] = new LessonViolation(
                        LessonCodes::FRAME_NATIVE_PUNCT,
                        $phrase->id,
                        '«'.$phrase->frameTarget.'» ends with '.self::named($target->terminal($phrase->frameTarget)).', «'.$phrase->frameNative.'» with '.self::named($native->terminal($phrase->frameNative)),
                    );
                }
            }

            if ($pronouns && ($pronoun = $context->targetWords()->unresolvedPronoun($phrase->frameTarget)) !== null) {
                $out[] = new LessonViolation(LessonCodes::FRAME_UNRESOLVED_PRONOUN, $phrase->id, "«{$phrase->frameTarget}» leans on «{$pronoun}», and nothing in the frame is what it stands for");
            }

            if ($agreement && ($agreeing = $context->nativeWords()->agreeingWithSlot($phrase->frameNative)) !== []) {
                $out[] = new LessonViolation(LessonCodes::FRAME_NATIVE_AGREEMENT, $phrase->id, "«{$phrase->frameNative}»: «".implode('», «', $agreeing).'» agrees with the slot — it changes with the filler');
            }
        }

        if ($frames > 0 && $withoutSlot * 3 > $frames) {
            $out[] = new LessonViolation(LessonCodes::FRAME_NO_SLOT_SHARE, 'lesson', "{$withoutSlot} of {$frames} frames have no slot (at most a third)");
        }

        return $out;
    }

    private static function named(string $mark): string
    {
        return $mark === '' ? 'no mark' : "«{$mark}»";
    }
}

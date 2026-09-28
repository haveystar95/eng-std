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
 * THE FRAMES (`lesson_day`, FRAMES): as many as half to all of the answer/ask exchanges, each said at least
 * once, at most seven words outside the slot, at most a third without a slot, a native rendering with no
 * «в/на»-style alternatives that ends the way the frame ends; a frame that stands alone — no pronoun it leans on
 * without a thing it stands for; a native frame with no word that agrees with its slot («___ разрешён?»).
 *
 * «One pattern = one frame» (наряд GEN-3): two frames of the day with the same target pattern or the same native pattern
 * ({@see FrameText::identity()}) are `frame.twin` — a warning at the later frame; the lines stand on one frame with two
 * fillers.
 *
 * A frame written without a mark at its end, in either language, is `frame.no_end_punct` (доработка GEN-2b): the
 * assembly reads the line past it all the same, and the phrase of the day borrows its line's mark. Whether the two
 * renderings end the same way (`frame.native_punct`) is asked only of a frame that has a mark on both sides.
 *
 * The pronoun is read by the target's pack, the agreement by the learner's language's, the closing marks each by its
 * own side's.
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
        $targetEnds = $context->reads(LessonCodes::FRAME_NO_END_PUNCT, LanguageSide::Target, 'sentence_ends');
        $nativeEnds = $context->reads(LessonCodes::FRAME_NO_END_PUNCT, LanguageSide::Native, 'sentence_ends');
        $targetMarks = $context->reads(LessonCodes::FRAME_NATIVE_PUNCT, LanguageSide::Target, 'sentence_ends');
        $nativeMarks = $context->reads(LessonCodes::FRAME_NATIVE_PUNCT, LanguageSide::Native, 'sentence_ends');
        $pronouns = $context->reads(LessonCodes::FRAME_UNRESOLVED_PRONOUN, LanguageSide::Target, 'unresolved_pronouns', 'function_words');
        $agreement = $context->reads(LessonCodes::FRAME_NATIVE_AGREEMENT, LanguageSide::Native, 'agreement');

        $withoutSlot = 0;
        $patterns = [];
        foreach ($answer->phrases as $phrase) {
            $twin = self::twin($patterns, $phrase->frameTarget, $phrase->frameNative);
            if ($twin !== null) {
                $out[] = new LessonViolation(LessonCodes::FRAME_TWIN, $phrase->id, "«{$phrase->frameTarget}» / «{$phrase->frameNative}» is the {$twin[1]} pattern of {$twin[0]}");
            }
            $patterns[] = [$phrase->id, FrameText::identity($phrase->frameTarget), FrameText::identity($phrase->frameNative)];

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

            $unmarked = [];
            if ($targetEnds && $context->targetWords()->terminal($phrase->frameTarget) === '') {
                $unmarked[] = "«{$phrase->frameTarget}»";
            }
            if ($nativeEnds && $context->nativeWords()->terminal($phrase->frameNative) === '') {
                $unmarked[] = "the native «{$phrase->frameNative}»";
            }
            if ($unmarked !== []) {
                $out[] = new LessonViolation(LessonCodes::FRAME_NO_END_PUNCT, $phrase->id, implode(' and ', $unmarked).(count($unmarked) === 1 ? ' ends' : ' end').' with no mark');
            }

            if ($targetMarks && $nativeMarks) {
                $target = $context->targetWords();
                $native = $context->nativeWords();
                $bothMarked = $target->terminal($phrase->frameTarget) !== '' && $native->terminal($phrase->frameNative) !== '';
                if ($bothMarked && $target->terminalKind($phrase->frameTarget) !== $native->terminalKind($phrase->frameNative)) {
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

    /**
     * The earlier frame of the day that has this frame's pattern in either language, and which side matched.
     *
     * @param  list<array{0: string, 1: string, 2: string}>  $patterns  id, target identity, native identity
     * @return array{0: string, 1: string}|null
     */
    private static function twin(array $patterns, string $target, string $native): ?array
    {
        $target = FrameText::identity($target);
        $native = FrameText::identity($native);
        foreach ($patterns as [$id, $theirTarget, $theirNative]) {
            if ($target === $theirTarget) {
                return [$id, 'target'];
            }
            if ($native !== '' && $native === $theirNative) {
                return [$id, 'native'];
            }
        }

        return null;
    }

    private static function named(string $mark): string
    {
        return $mark === '' ? 'no mark' : "«{$mark}»";
    }
}

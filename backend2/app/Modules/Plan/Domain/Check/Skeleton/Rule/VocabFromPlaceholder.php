<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Check\Skeleton\TermForms;
use App\Modules\Plan\Domain\Check\StageText;
use App\Modules\Plan\Domain\Lesson\Filler;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\Service\FrameText;

/**
 * `vocab.from_placeholder` — a WARNING the repairs take right after a letter of another writing, FATAL only beyond them (наряд
 * GEN-4c, {@see LessonCodes::BUDGETED}). VOCABULARY (`lesson_skeleton.v1.1`): «Never: a word of a placeholder filler» — the
 * e2e of GEN-4b taught «depozit», said only in «Am lucrat la un depozit», a workplace the skeleton chose for a learner who
 * had named none.
 *
 * A word of the day is said in no frame (the frame's own words) and in no partner line — only in a frame said with a filler,
 * by its form as `vocab.not_found` reads it ({@see TermForms}) — and none of those fillers is a detail of the learner's. The
 * learner's details are the words the skeleton is given as the learner's own ({@see SkeletonContext::$learnerWords}, the
 * plan's goal): a filler is one when its native or its target stands among their content words — by form, case, articles,
 * apostrophes and hyphens aside ({@see TermForms::among()}: the same word, one stem, a listed form; never three letters in
 * common, never a preposition of the goal), and diacritics aside too ({@see StageText::plain()}). No such words: every word
 * said only through a filler is a finding. A word said nowhere is `vocab.not_found`'s.
 */
final class VocabFromPlaceholder implements SkeletonRule
{
    public const CODE = 'vocab.from_placeholder';

    public function code(): string
    {
        return self::CODE;
    }

    public function fatal(): bool
    {
        return false;
    }

    public function findings(Skeleton $skeleton, SkeletonContext $context): array
    {
        $target = $context->targetReading('function_words', 'word_forms');
        $native = $context->nativeReading('function_words', 'word_forms');
        $out = [];
        foreach ($skeleton->vocabulary as $item) {
            $says = static fn (string $text): bool => TermForms::in($item->termTarget, $text, $target);
            if (array_filter($skeleton->frames, static fn ($f): bool => $says($f->phrase->frameTarget)) !== []
                || array_filter($skeleton->partnerLines, static fn ($l): bool => $says($l->textTarget)) !== []) {
                continue;
            }
            $through = [];
            foreach ($skeleton->frames as $frame) {
                foreach ($frame->phrase->fillers() as $index => $filler) {
                    if ($says(FrameText::fill($frame->phrase->frameTarget, $filler->target))) {
                        $through[$frame->id().'.f'.($index + 1)] = $filler;
                    }
                }
            }
            if ($through === [] || array_filter($through, static fn (Filler $f): bool => self::learners($f, $context->learnerWords, $target, $native)) !== []) {
                continue;
            }
            $fillers = implode(', ', array_map(static fn (string $at, Filler $f): string => "{$at} «{$f->target}»", array_keys($through), $through));
            $out[] = new LessonViolation(
                self::CODE,
                $item->id,
                "a placeholder word: replace with a word from a frame or a partner line of this day («{$item->termTarget}» is said only through the filler {$fillers}, no detail of the learner's)",
            );
        }

        return $out;
    }

    /** Is the filler a detail the learner gave — its native or its target in the learner's own words? */
    private static function learners(Filler $filler, string $learnerWords, ?LanguageWords $target, ?LanguageWords $native): bool
    {
        if (trim($learnerWords) === '') {
            return false;
        }
        foreach ([[$filler->native, $native], [$filler->target, $target]] as [$value, $words]) {
            if (trim($value) !== '' && (TermForms::among($value, $learnerWords, $words)
                || TermForms::among(StageText::plain($value), StageText::plain($learnerWords), $words))) {
                return true;
            }
        }

        return false;
    }
}

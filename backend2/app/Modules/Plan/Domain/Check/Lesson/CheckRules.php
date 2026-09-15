<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\Language\LanguageSide;
use App\Modules\Plan\Domain\Check\Language\LanguageWords;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Service\Words;

/**
 * THE CHECK OF EVERY EXCHANGE (`lesson_day.v4.5`, CHECK PER EXCHANGE): always about the partner's
 * line, never about the learner's; the right option a paraphrase — no two consecutive words of the
 * partner's line; no alternative the partner named offered as a wrong option.
 *
 * A repeated pair that has no paraphrase is not a copy (решение архитектора после GEN-2a): a pair of two
 * function words («in the»); a pair with a number, a name or a counted thing in it («forty pounds», «2 400»,
 * «the first», «take Nurofen», «designer and» after «one designer»); a pair whose content words are all the
 * lesson's own items — words of its vocabulary or of a frame's fillers («the pasta»): the check names the item
 * the partner stated. A time is not exempt — the prompt's own paraphrase of «twice a day after meals» is
 * «morning and evening, after eating».
 *
 * «About the learner» is read two ways: the question names the learner's role as the one who said
 * something, or the right option shares two content words with the learner's line and none with the
 * partner's. The checks are in the target language, and every word is read by the target's pack.
 */
final class CheckRules implements LessonRule
{
    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $verbatim = $context->reads(LessonCodes::CHECK_VERBATIM, LanguageSide::Target, 'function_words', 'word_forms', 'number_pattern', 'sentence_ends');
        $aboutLearner = $context->reads(LessonCodes::CHECK_ABOUT_LEARNER, LanguageSide::Target, 'saying_verbs', 'function_words', 'word_forms');
        $alternatives = $context->reads(LessonCodes::CHECK_LISTED_ALTERNATIVE_AS_WRONG, LanguageSide::Target, 'alternative_words', 'function_words', 'word_forms');
        if (! $verbatim && ! $aboutLearner && ! $alternatives) {
            return [];
        }
        $words = $context->targetWords();

        $out = [];
        $items = $verbatim ? self::itemWords($answer, $words) : [];
        foreach ($answer->exchanges as $exchange) {
            $partner = $exchange->partner();
            $right = $exchange->check->correctOption();
            if ($partner === null || $right === null) {
                continue;
            }
            $address = LessonViolation::check($exchange->step);

            if ($verbatim) {
                $fixed = [...$words->names($partner->textTarget), ...self::counted($partner->textTarget, $words)];
                $repeated = array_values(array_filter(
                    array_intersect(self::pairs($right->textTarget, $words), self::pairs($partner->textTarget, $words)),
                    static fn (string $pair): bool => ! self::withoutParaphrase($pair, $fixed, $items, $words),
                ));
                if ($repeated !== []) {
                    $out[] = new LessonViolation(LessonCodes::CHECK_VERBATIM, $address, "the right option «{$right->textTarget}» repeats «{$repeated[0]}» of the partner's line");
                }
            }

            if ($aboutLearner && self::aboutLearner($answer, $exchange, $words)) {
                $out[] = new LessonViolation(LessonCodes::CHECK_ABOUT_LEARNER, $address, "«{$exchange->check->textTarget}» → «{$right->textTarget}» is about the learner's line, not the partner's");
            }

            if ($alternatives && array_filter(Words::tokens($partner->textTarget), $words->isAlternative(...)) !== []) {
                foreach ($exchange->check->options as $index => $option) {
                    if ($index === $exchange->check->correctOptionIndex) {
                        continue;
                    }
                    if ($words->content($option->textTarget) !== [] && $words->notIn($option->textTarget, $partner->textTarget) === []) {
                        $out[] = new LessonViolation(LessonCodes::CHECK_LISTED_ALTERNATIVE_AS_WRONG, $address, "the wrong option «{$option->textTarget}» is an alternative the partner named");
                    }
                }
            }
        }

        return $out;
    }

    private static function aboutLearner(Lesson $answer, Exchange $exchange, LanguageWords $words): bool
    {
        $learner = $exchange->learner();
        $partner = $exchange->partner();
        $right = $exchange->check->correctOption();
        if ($partner === null || $right === null) {
            return false;
        }

        $role = Words::tokens($learner->roleTarget ?? $answer->learnerRoleTarget);
        $question = Words::tokens($exchange->check->textTarget);
        if ($role !== [] && self::contains($question, $role) && array_filter($question, $words->isSaying(...)) !== []) {
            return true;
        }

        return $learner !== null
            && $words->shared($right->textTarget, $learner->textTarget) >= 2
            && $words->shared($right->textTarget, $partner->textTarget) === 0;
    }

    /**
     * A repeated pair no paraphrase can avoid: a number, a name or a counted thing in it, or nothing but the
     * lesson's items.
     *
     * @param  list<string>  $fixed  the partner line's names and counted things, lower-cased
     * @param  list<string>  $items  the words of the lesson's vocabulary and fillers
     */
    private static function withoutParaphrase(string $pair, array $fixed, array $items, LanguageWords $words): bool
    {
        // «one week»: a number word may be a function word of the lists, and still the number.
        foreach (Words::tokens($pair) as $word) {
            if ($words->isNumber($word) || in_array($word, $fixed, true)) {
                return true;
            }
        }
        $content = $words->content($pair);
        foreach ($content as $word) {
            $item = false;
            foreach ($items as $other) {
                if ($words->sameStem($word, $other)) {
                    $item = true;
                    break;
                }
            }
            if (! $item) {
                return false;
            }
        }

        return $content !== [];
    }

    /**
     * The things a line counts: the word right after a number («six developers, one designer» — the amount is
     * the number with its thing).
     *
     * @return list<string>
     */
    private static function counted(string $line, LanguageWords $words): array
    {
        $tokens = Words::tokens($line);
        $out = [];
        for ($i = 0; $i + 1 < count($tokens); $i++) {
            if ($words->isNumber($tokens[$i]) && ! $words->isFunction($tokens[$i + 1])) {
                $out[] = $tokens[$i + 1];
            }
        }

        return $out;
    }

    /**
     * The content words of the lesson's own items — its vocabulary terms and every frame's fillers.
     *
     * @return list<string>
     */
    private static function itemWords(Lesson $answer, LanguageWords $words): array
    {
        $out = [];
        foreach ($answer->vocabulary as $item) {
            $out = [...$out, ...$words->content($item->termTarget)];
        }
        foreach ($answer->phrases as $phrase) {
            foreach ($phrase->fillers() as $filler) {
                $out = [...$out, ...$words->content($filler->target)];
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Two consecutive words of a text, as «w1 w2» — a pair of two function words («in the», «it will»)
     * left out: repeating it copies no content, and a paraphrase cannot avoid it.
     *
     * @return list<string>
     */
    private static function pairs(string $text, LanguageWords $words): array
    {
        $tokens = Words::tokens($text);
        $out = [];
        for ($i = 0; $i + 1 < count($tokens); $i++) {
            if ($words->isFunction($tokens[$i]) && $words->isFunction($tokens[$i + 1])) {
                continue;
            }
            $out[] = $tokens[$i].' '.$tokens[$i + 1];
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  list<string>  $haystack
     * @param  list<string>  $needle
     */
    private static function contains(array $haystack, array $needle): bool
    {
        $n = count($needle);
        for ($i = 0; $i + $n <= count($haystack); $i++) {
            if (array_slice($haystack, $i, $n) === $needle) {
                return true;
            }
        }

        return false;
    }
}

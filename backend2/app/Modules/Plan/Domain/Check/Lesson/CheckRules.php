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
 *
 * THE OPTIONS OF ONE CHECK ARE OF ONE FORM WITH THE RIGHT ONE (наряд FIX-3 §5), read on the card's side — the learner's
 * language, which is what the card shows: each option is 0.5–2× the right one's length in letters, none starts with a
 * lower-case letter (a fragment is odd by its form, not by its meaning), and none is a piece of the partner's line in the
 * learner's language word for word (an option copied out of the translation is found by reading, not by hearing).
 * Otherwise `options.form_mismatch` at the check — fatal: the card a learner would get tests the form, not the meaning.
 */
final class CheckRules implements LessonRule
{
    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $verbatim = $context->reads(LessonCodes::CHECK_VERBATIM, LanguageSide::Target, 'function_words', 'word_forms', 'number_pattern', 'sentence_ends');
        $aboutLearner = $context->reads(LessonCodes::CHECK_ABOUT_LEARNER, LanguageSide::Target, 'saying_verbs', 'function_words', 'word_forms');
        $alternatives = $context->reads(LessonCodes::CHECK_LISTED_ALTERNATIVE_AS_WRONG, LanguageSide::Target, 'alternative_words', 'function_words', 'word_forms');
        $form = $context->reads(LessonCodes::OPTIONS_FORM_MISMATCH, LanguageSide::Native);
        if (! $verbatim && ! $aboutLearner && ! $alternatives && ! $form) {
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

            $mismatch = $form ? self::formMismatch($exchange) : null;
            if ($mismatch !== null) {
                $out[] = new LessonViolation(LessonCodes::OPTIONS_FORM_MISMATCH, $address, $mismatch);
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

    /** Shortest and longest an option may be against the right one, in letters (наряд FIX-3 §5). */
    public const OPTION_LENGTH = [0.5, 2.0];

    /**
     * What makes the options of an exchange's check not of one form with its right one, in the learner's language — or
     * null when they are: the first option too short or too long against the right one, starting lower-case, or said
     * word for word in the partner's line.
     */
    private static function formMismatch(Exchange $exchange): ?string
    {
        $right = $exchange->check->correctOption();
        $partner = $exchange->partner();
        if ($right === null || $partner === null) {
            return null;
        }
        $rightLength = self::letters($right->textNative);
        $partnerWords = Words::tokens($partner->textNative);
        foreach ($exchange->check->options as $index => $option) {
            $text = trim($option->textNative);
            $length = self::letters($text);
            if ($index !== $exchange->check->correctOptionIndex && $rightLength > 0
                && ($length < self::OPTION_LENGTH[0] * $rightLength || $length > self::OPTION_LENGTH[1] * $rightLength)) {
                return "the option «{$text}» is {$length} letters against {$rightLength} of the right «{$right->textNative}»";
            }
            if (preg_match('/^[^\p{L}]*\p{Ll}/u', $text) === 1) {
                return "the option «{$text}» starts lower-case";
            }
            $optionWords = Words::tokens($text);
            if ($optionWords !== [] && self::contains($partnerWords, $optionWords)) {
                return "the option «{$text}» is a piece of the partner's line «{$partner->textNative}»";
            }
        }

        return null;
    }

    /** The letters and digits of a text — what «as long as» is measured in, marks and spaces aside. */
    private static function letters(string $text): int
    {
        return mb_strlen((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $text));
    }

    private static function aboutLearner(Lesson $answer, Exchange $exchange, LanguageWords $words): bool
    {
        $learner = $exchange->learner();
        $partner = $exchange->partner();
        $right = $exchange->check->correctOption();
        if ($partner === null || $right === null) {
            return false;
        }

        // The learner's role is the plan's, written over every line by the server (наряд GEN-3).
        $role = Words::tokens($answer->learnerRoleTarget);
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

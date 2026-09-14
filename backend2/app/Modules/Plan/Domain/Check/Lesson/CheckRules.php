<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Service\EnglishWords;
use App\Modules\Plan\Domain\Service\Words;

/**
 * THE CHECK OF EVERY EXCHANGE (`lesson_day.v4.4`, CHECK PER EXCHANGE): always about the partner's
 * line, never about the learner's; the right option a paraphrase — no two consecutive words of the
 * partner's line (a pair of two function words aside); no alternative the partner named offered as a
 * wrong option.
 *
 * «About the learner» is read two ways: the question names the learner's role as the one who said
 * something, or the right option shares two content words with the learner's line and none with the
 * partner's. English target only — the words are read in English.
 */
final class CheckRules implements LessonRule
{
    private const SAYING = ['say', 'says', 'said', 'tell', 'tells', 'told', 'answer', 'answers', 'answered', 'mention', 'mentions', 'mentioned', 'reply', 'replies', 'replied', 'want', 'wants', 'wanted'];

    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        if ($context->targetLang !== 'en') {
            return [];
        }
        $out = [];
        foreach ($answer->exchanges as $exchange) {
            $partner = $exchange->partner();
            $right = $exchange->check->correctOption();
            if ($partner === null || $right === null) {
                continue;
            }
            $address = LessonViolation::check($exchange->step);

            $repeated = array_values(array_intersect(self::pairs($right->textTarget), self::pairs($partner->textTarget)));
            if ($repeated !== []) {
                $out[] = new LessonViolation(LessonCodes::CHECK_VERBATIM, $address, "the right option «{$right->textTarget}» repeats «{$repeated[0]}» of the partner's line");
            }

            if (self::aboutLearner($answer, $exchange)) {
                $out[] = new LessonViolation(LessonCodes::CHECK_ABOUT_LEARNER, $address, "«{$exchange->check->textTarget}» → «{$right->textTarget}» is about the learner's line, not the partner's");
            }

            if (in_array('or', Words::tokens($partner->textTarget), true)) {
                foreach ($exchange->check->options as $index => $option) {
                    if ($index === $exchange->check->correctOptionIndex) {
                        continue;
                    }
                    $content = EnglishWords::content($option->textTarget);
                    if ($content !== [] && EnglishWords::notIn($option->textTarget, $partner->textTarget) === []) {
                        $out[] = new LessonViolation(LessonCodes::CHECK_LISTED_ALTERNATIVE_AS_WRONG, $address, "the wrong option «{$option->textTarget}» is an alternative the partner named");
                    }
                }
            }
        }

        return $out;
    }

    private static function aboutLearner(Lesson $answer, Exchange $exchange): bool
    {
        $learner = $exchange->learner();
        $partner = $exchange->partner();
        $right = $exchange->check->correctOption();
        if ($partner === null || $right === null) {
            return false;
        }

        $role = Words::tokens($learner->roleTarget ?? $answer->learnerRoleTarget);
        $question = Words::tokens($exchange->check->textTarget);
        if ($role !== [] && self::contains($question, $role) && array_intersect($question, self::SAYING) !== []) {
            return true;
        }

        return $learner !== null
            && EnglishWords::shared($right->textTarget, $learner->textTarget) >= 2
            && EnglishWords::shared($right->textTarget, $partner->textTarget) === 0;
    }

    /**
     * Two consecutive words of a text, as «w1 w2» — a pair of two function words («in the», «it will»)
     * left out: repeating it copies no content, and a paraphrase cannot avoid it.
     *
     * @return list<string>
     */
    private static function pairs(string $text): array
    {
        $tokens = Words::tokens($text);
        $out = [];
        for ($i = 0; $i + 1 < count($tokens); $i++) {
            if (EnglishWords::isFunction($tokens[$i]) && EnglishWords::isFunction($tokens[$i + 1])) {
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

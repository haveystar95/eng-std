<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\Language\LanguageSide;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Service\Words;

/**
 * THE PARTNER (`lesson_day.v4.5`, CONVERSATION PARTNER RULE, MOBILE LENGTH): one question per bubble; two sentences
 * and eighteen words at most; never an empty closer («Anything else?», «Great!»).
 *
 * Two questions in one bubble are two question marks, or one sentence that asks again (the target's pack spells
 * how: in English, after a comma with «and / or» and a new auxiliary — «When did it start, and did you lift
 * anything heavy?»). Words are counted without a language; sentences, questions and closers are the target's.
 */
final class PartnerRules implements LessonRule
{
    public const MAX_WORDS = 18;

    public const MAX_SENTENCES = 2;

    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $twoQuestions = $context->reads(LessonCodes::PARTNER_TWO_QUESTIONS, LanguageSide::Target, 'sentence_ends', 'second_question_pattern');
        $sentences = $context->reads(LessonCodes::PARTNER_TOO_LONG, LanguageSide::Target, 'sentence_ends');
        $closers = $context->reads(LessonCodes::PARTNER_CLOSER, LanguageSide::Target, 'closers');
        $target = $context->targetWords();

        $out = [];
        foreach ($answer->exchanges as $exchange) {
            $partner = $exchange->partner();
            if ($partner === null) {
                continue;
            }
            $address = LessonViolation::partner($exchange->step);
            $text = $partner->textTarget;

            if ($twoQuestions && $target->asksTwice($text)) {
                $out[] = new LessonViolation(LessonCodes::PARTNER_TWO_QUESTIONS, $address, "«{$text}» asks two things at once");
            }

            $words = Words::count($text);
            if ($words > self::MAX_WORDS) {
                $out[] = new LessonViolation(LessonCodes::PARTNER_TOO_LONG, $address, "«{$text}» has {$words} words (max ".self::MAX_WORDS.')');
            }
            if ($sentences && ($count = $target->sentences($text)) > self::MAX_SENTENCES) {
                $out[] = new LessonViolation(LessonCodes::PARTNER_TOO_LONG, $address, "«{$text}» has {$count} sentences (max ".self::MAX_SENTENCES.')');
            }

            if ($closers && $target->isCloser($text)) {
                $out[] = new LessonViolation(LessonCodes::PARTNER_CLOSER, $address, "«{$text}» is an empty closer");
            }
        }

        return $out;
    }
}

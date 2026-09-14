<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Service\EnglishWords;
use App\Modules\Plan\Domain\Service\Words;

/**
 * THE PARTNER (`lesson_day.v4.4`, CONVERSATION PARTNER RULE, MOBILE LENGTH): one question per
 * bubble; two sentences and eighteen words at most; never an empty closer («Anything else?»,
 * «Great!»).
 *
 * Two questions in one bubble are two question marks, or one sentence that goes on after a comma
 * with «and / or» and a new auxiliary («When did it start, and did you lift anything heavy?»).
 */
final class PartnerRules implements LessonRule
{
    public const MAX_WORDS = 18;

    public const MAX_SENTENCES = 2;

    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $out = [];
        foreach ($answer->exchanges as $exchange) {
            $partner = $exchange->partner();
            if ($partner === null) {
                continue;
            }
            $address = LessonViolation::partner($exchange->step);
            $text = $partner->textTarget;

            if (substr_count($text, '?') >= 2
                || preg_match('/,\s*(?:and|or)\s+(?:do|does|did|is|are|was|were|have|has|had|can|could|will|would|should)\b[^?]*\?/iu', $text) === 1) {
                $out[] = new LessonViolation(LessonCodes::PARTNER_TWO_QUESTIONS, $address, "«{$text}» asks two things at once");
            }

            $words = Words::count($text);
            if ($words > self::MAX_WORDS) {
                $out[] = new LessonViolation(LessonCodes::PARTNER_TOO_LONG, $address, "«{$text}» has {$words} words (max ".self::MAX_WORDS.')');
            }
            $sentences = self::sentences($text);
            if ($sentences > self::MAX_SENTENCES) {
                $out[] = new LessonViolation(LessonCodes::PARTNER_TOO_LONG, $address, "«{$text}» has {$sentences} sentences (max ".self::MAX_SENTENCES.')');
            }

            if ($context->targetLang === 'en' && EnglishWords::isCloser($text)) {
                $out[] = new LessonViolation(LessonCodes::PARTNER_CLOSER, $address, "«{$text}» is an empty closer");
            }
        }

        return $out;
    }

    private static function sentences(string $text): int
    {
        $parts = preg_split('/[.?!…]+(?:\s+|$)/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        return $parts === false ? 0 : count(array_filter($parts, static fn (string $p): bool => Words::count($p) > 0));
    }
}

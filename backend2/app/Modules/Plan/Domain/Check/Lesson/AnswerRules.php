<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\ListeningQuestion;

/**
 * TWO HABITS OF A MODEL THAT NO LEARNER SHOULD FEEL: the image prompt that repeats the prompt's rules
 * («realistic photo of…», «no text or logos») instead of naming what is in the picture, and the right
 * answer parked at one place — more than 60 % of the day's checks and listening questions answered by
 * the same index, before the server shuffles them.
 */
final class AnswerRules implements LessonRule
{
    public const SKEW_SHARE = 0.6;

    private const RULE_TEXT = '/\b(?:realistic|photo(?:graph)?\s+of|image\s+of|picture\s+of|no\s+text|no\s+logos?|without\s+(?:any\s+)?(?:text|logos?)|high[-\s]quality|stock\s+photo|4k|hd)\b/iu';

    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $out = [];
        foreach ($answer->vocabulary as $item) {
            if ($item->imagePrompt !== null && preg_match(self::RULE_TEXT, $item->imagePrompt, $m) === 1) {
                $out[] = new LessonViolation(LessonCodes::IMAGE_PROMPT_RULE_TEXT, $item->id, "the image prompt «{$item->imagePrompt}» carries rule text «{$m[0]}»");
            }
        }

        $indices = [
            ...array_map(static fn (Exchange $e): int => $e->check->correctOptionIndex, $answer->exchanges),
            ...array_map(static fn (ListeningQuestion $q): int => $q->correctOptionIndex, $answer->listening),
        ];
        $total = count($indices);
        if ($total > 0) {
            $counts = array_count_values($indices);
            arsort($counts);
            $index = (int) array_key_first($counts);
            $share = $counts[$index] / $total;
            if ($share > self::SKEW_SHARE) {
                $out[] = new LessonViolation(
                    LessonCodes::ANSWER_INDEX_SKEW,
                    'lesson',
                    "the right answer stands at index {$index} in {$counts[$index]} of {$total} questions (".(int) round($share * 100).' %)',
                );
            }
        }

        return $out;
    }
}

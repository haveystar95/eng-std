<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Lesson;

/**
 * THE IMAGE PROMPT NAMES WHAT IS IN THE PICTURE (`lesson_day.v4.4`, VOCABULARY): never the prompt's own rules
 * repeated inside it («realistic photo of…», «no text or logos») — the photo search reads it as words.
 */
final class ImagePromptRules implements LessonRule
{
    private const RULE_TEXT = '/\b(?:realistic|photo(?:graph)?\s+of|image\s+of|picture\s+of|no\s+text|no\s+logos?|without\s+(?:any\s+)?(?:text|logos?)|high[-\s]quality|stock\s+photo|4k|hd)\b/iu';

    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $out = [];
        foreach ($answer->vocabulary as $item) {
            if ($item->imagePrompt !== null && preg_match(self::RULE_TEXT, $item->imagePrompt, $m) === 1) {
                $out[] = new LessonViolation(LessonCodes::IMAGE_PROMPT_RULE_TEXT, $item->id, "the image prompt «{$item->imagePrompt}» carries rule text «{$m[0]}»");
            }
        }

        return $out;
    }
}

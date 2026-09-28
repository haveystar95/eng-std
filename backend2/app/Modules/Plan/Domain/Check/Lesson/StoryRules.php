<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonRule;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Service\FrameText;

/**
 * THE STORY SO FAR (`lesson_day`, THE STORY SO FAR; наряд GEN-3): a day of a plan is the next day of one story, and
 * what the learner learned on the earlier days is learned.
 *
 *  - a word of an earlier day listed again — `vocab.known_repeat` (fatal: the learner would be taught it twice);
 *  - a frame whose target pattern an earlier day taught — `frame.known_repeat` (fatal);
 *  - a frame whose native pattern alone an earlier day taught — `frame.known_native_repeat` (a warning for now: the
 *    architect decides after the numbers of GEN-3);
 *  - a partner of the same role as on an earlier day, imagined with another gender — `role_gender.changed` (a warning:
 *    the same person would speak with another voice).
 *
 * «The same» is {@see FrameText::identity()}: the case, the run of spaces and the closing mark do not count. Facts of the
 * plot (a price, an agreement) are not the code's — the architect reads them.
 */
final class StoryRules implements LessonRule
{
    public function violations(Lesson $answer, LessonValidationContext $context): array
    {
        $earlier = $context->earlierDays;
        if ($earlier->isEmpty()) {
            return [];
        }

        $out = [];
        $words = [];
        foreach ($earlier->words() as $word) {
            $words[FrameText::identity($word['term'])] ??= $word['day'];
        }
        foreach ($answer->vocabulary as $item) {
            $day = $words[FrameText::identity($item->termTarget)] ?? null;
            if ($day !== null) {
                $out[] = new LessonViolation(LessonCodes::VOCAB_KNOWN_REPEAT, $item->id, "«{$item->termTarget}» is a word the learner already learned on day {$day}");
            }
        }

        $targets = [];
        $natives = [];
        foreach ($earlier->frames() as $frame) {
            $targets[FrameText::identity($frame['target'])] ??= $frame;
            $natives[FrameText::identity($frame['native'])] ??= $frame;
        }
        foreach ($answer->phrases as $phrase) {
            $known = $targets[FrameText::identity($phrase->frameTarget)] ?? null;
            if ($known !== null) {
                $out[] = new LessonViolation(LessonCodes::FRAME_KNOWN_REPEAT, $phrase->id, "«{$phrase->frameTarget}» is the frame «{$known['target']}» the learner already learned on day {$known['day']}");

                continue;
            }
            $known = $natives[FrameText::identity($phrase->frameNative)] ?? null;
            if ($known !== null) {
                $out[] = new LessonViolation(LessonCodes::FRAME_KNOWN_NATIVE_REPEAT, $phrase->id, "the native «{$phrase->frameNative}» is the native pattern of «{$known['target']}», learned on day {$known['day']}");
            }
        }

        return [...$out, ...self::gender($answer, $context)];
    }

    /** @return list<LessonViolation> */
    private static function gender(Lesson $answer, LessonValidationContext $context): array
    {
        $role = FrameText::identity($context->partnerRoleTarget);
        if ($answer->roleGender === null || $role === '') {
            return [];
        }
        $days = [];
        foreach ($context->earlierDays->days as $day) {
            if (FrameText::identity($day->partnerRoleTarget) === $role && $day->partnerGender !== $answer->roleGender) {
                $days[] = "day {$day->number} ({$day->partnerGender->value})";
            }
        }

        return $days === [] ? [] : [new LessonViolation(
            LessonCodes::ROLE_GENDER_CHANGED,
            'lesson',
            "the {$context->partnerRoleTarget} is {$answer->roleGender->value}, the same role was ".implode(', ', $days),
        )];
    }
}

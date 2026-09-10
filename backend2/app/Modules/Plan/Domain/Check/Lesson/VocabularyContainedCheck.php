<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Lesson;

use App\Modules\Plan\Domain\Check\LessonCheck;
use App\Modules\Plan\Domain\Check\LessonContext;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\VocabularyItem;
use App\Modules\Plan\Domain\Service\Words;

/** No vocabulary item is contained in another («back» inside «lower back»); `drop` removes the shorter. */
final class VocabularyContainedCheck implements LessonCheck
{
    public function name(): string
    {
        return 'vocabulary_contained';
    }

    public function switchable(): bool
    {
        return true;
    }

    public function violations(Lesson $lesson, LessonContext $context): array
    {
        $out = [];
        foreach (self::containedIds($lesson) as $id => $inside) {
            $out[] = "vocabulary {$id} is contained in {$inside}";
        }

        return $out;
    }

    public function drop(Lesson $lesson, LessonContext $context): Lesson
    {
        $contained = self::containedIds($lesson);
        $kept = array_values(array_filter(
            $lesson->vocabulary,
            static fn (VocabularyItem $v): bool => ! isset($contained[$v->id]),
        ));

        return $lesson->withVocabulary($kept);
    }

    /** @return array<string, string> shorter item id → the id it is contained in */
    private static function containedIds(Lesson $lesson): array
    {
        $out = [];
        foreach ($lesson->vocabulary as $short) {
            foreach ($lesson->vocabulary as $long) {
                if ($short->id === $long->id) {
                    continue;
                }
                if (Words::count($short->termTarget) < Words::count($long->termTarget)
                    && Words::containsTerm($short->termTarget, $long->termTarget)) {
                    $out[$short->id] = $long->id;
                    break;
                }
            }
        }

        return $out;
    }
}

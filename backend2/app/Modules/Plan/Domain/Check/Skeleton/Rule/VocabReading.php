<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Check\Skeleton\TargetSound;
use App\Modules\Plan\Domain\Check\StageText;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/**
 * `vocab.reading` — a warning: the reading of a WORD close to its translation ({@see PronunciationNearNative::NEAR}), yet not
 * the same (that is fatal); a name is read as it is written ({@see PronunciationPairs::isName()}), a word the two languages
 * share by its sound ({@see TargetSound}). The frames' and fillers' readings are {@see PronunciationNearNative}'s; a word's has its own code
 * because its repair differs (`lesson_card_repair.v1.5`: a finding about the reading keeps the word and fixes the reading —
 * any other finding at a word replaces it).
 */
final class VocabReading implements SkeletonRule
{
    public const CODE = 'vocab.reading';

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
        $out = [];
        foreach ($skeleton->vocabulary as $item) {
            if (PronunciationPairs::isName($item->termTarget, $item->translationNative)) {
                continue;
            }
            $same = StageText::normal($item->pronunciationNative) === StageText::normal($item->translationNative);
            $alike = StageText::similarity($item->pronunciationNative, $item->translationNative);
            if (! $same && $alike >= PronunciationNearNative::NEAR
                && ! TargetSound::is($item->pronunciationNative, $item->termTarget, $item->translationNative, $context->target->code)) {
                $out[] = new LessonViolation(self::CODE, $item->id, sprintf('the reading «%s» of «%s» is close to its translation «%s» (%.2f)', $item->pronunciationNative, $item->termTarget, $item->translationNative, $alike));
            }
        }

        return $out;
    }
}

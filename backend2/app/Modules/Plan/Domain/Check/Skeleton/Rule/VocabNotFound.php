<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Check\Skeleton\TermForms;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\Service\FrameText;

/**
 * `vocab.not_found` — FATAL. Every word of the day occurs in the skeleton — in a frame (the frame with each of its fillers:
 * the learner's own details enter as fillers) or in a partner line, in some form of it ({@see TermForms}: the dictionary
 * form against the inflected one). A word no frame and no line says is no word of this day.
 */
final class VocabNotFound implements SkeletonRule
{
    public const CODE = 'vocab.not_found';

    public function code(): string
    {
        return self::CODE;
    }

    public function fatal(): bool
    {
        return true;
    }

    public function findings(Skeleton $skeleton, SkeletonContext $context): array
    {
        $words = $context->targetReading('function_words', 'word_forms');
        $texts = [];
        foreach ($skeleton->frames as $frame) {
            $texts[] = $frame->phrase->frameTarget;
            foreach ($frame->phrase->fillers() as $filler) {
                $texts[] = FrameText::fill($frame->phrase->frameTarget, $filler->target);
            }
        }
        foreach ($skeleton->partnerLines as $line) {
            $texts[] = $line->textTarget;
        }

        $out = [];
        foreach ($skeleton->vocabulary as $item) {
            if (array_filter($texts, static fn (string $text): bool => TermForms::in($item->termTarget, $text, $words)) === []) {
                $out[] = new LessonViolation(self::CODE, $item->id, "«{$item->termTarget}» occurs in no frame and no partner line");
            }
        }

        return $out;
    }
}

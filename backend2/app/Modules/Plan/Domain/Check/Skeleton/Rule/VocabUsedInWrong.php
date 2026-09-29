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
 * `vocab.used_in_wrong` — a warning. `used_in` names where the word stands — frames (`p3`: the frame with any of its fillers)
 * and partner lines (`a4`) — and the word is there, in some form of it ({@see TermForms}). An empty list, a place the skeleton
 * does not have, or a place without the word are findings.
 */
final class VocabUsedInWrong implements SkeletonRule
{
    public const CODE = 'vocab.used_in_wrong';

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
        $words = $context->targetReading('function_words', 'word_forms');
        $out = [];
        foreach ($skeleton->vocabulary as $item) {
            if ($item->usedIn === []) {
                $out[] = new LessonViolation(self::CODE, $item->id, 'used_in is empty');

                continue;
            }
            foreach ($item->usedIn as $ref) {
                $frame = $skeleton->frame($ref);
                $line = $skeleton->partnerLine($ref);
                $texts = match (true) {
                    $frame !== null => [$frame->phrase->frameTarget, ...array_map(static fn ($f): string => FrameText::fill($frame->phrase->frameTarget, $f->target), $frame->phrase->fillers())],
                    $line !== null => [$line->textTarget],
                    default => null,
                };
                if ($texts === null) {
                    $out[] = new LessonViolation(self::CODE, $item->id, "used_in names «{$ref}», which is neither a frame nor a partner line");
                } elseif (array_filter($texts, static fn (string $t): bool => TermForms::in($item->termTarget, $t, $words)) === []) {
                    $out[] = new LessonViolation(self::CODE, $item->id, "«{$item->termTarget}» is not in {$ref}");
                }
            }
        }

        return $out;
    }
}

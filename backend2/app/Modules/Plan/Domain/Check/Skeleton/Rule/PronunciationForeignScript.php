<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Check\StageText;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\Service\ReadingLetters;

/**
 * `pronunciation.foreign_script` — FATAL (the guard of LANG-1b, наряд GEN-4). The learner reads the skeleton's native fields
 * and readings in the letters of their own alphabet:
 *
 *  - a READING (of a frame, a filler, a word) has no letter outside the native pack's alphabet (`script_letters`) — a Latin
 *    letter in a Cyrillic reading, an Armenian «ֆ» — after the parser has put back what it can ({@see ReadingLetters});
 *  - a NATIVE FIELD (the frame, a filler, a partner line, a word's translation, the hint, the title, the role) has no word
 *    written in two alphabets at once («Kак») and, in a Cyrillic language, no letter of another Cyrillic alphabet inside a
 *    word (a Ukrainian «і» in a Russian word). A word wholly in another alphabet — a name, a brand — is let be.
 */
final class PronunciationForeignScript implements SkeletonRule
{
    public const CODE = 'pronunciation.foreign_script';

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
        $words = $context->nativeReading('script_letters');
        $readings = [];
        $natives = [['skeleton', $skeleton->titleNative], ['skeleton', $skeleton->descriptionNative], ['skeleton', $skeleton->learnerRoleNative]];
        foreach ($skeleton->frames as $frame) {
            $readings[] = [$frame->id(), $frame->phrase->pronunciationNative];
            $natives[] = [$frame->id(), $frame->phrase->frameNative];
            $natives[] = [$frame->id(), $frame->phrase->slot->hintNative ?? ''];
            foreach ($frame->phrase->fillers() as $index => $filler) {
                $readings[] = [$frame->id().'.f'.($index + 1), $filler->pronunciationNative];
                $natives[] = [$frame->id().'.f'.($index + 1), $filler->native];
            }
        }
        foreach ($skeleton->partnerLines as $line) {
            $natives[] = [$line->id, $line->textNative];
        }
        foreach ($skeleton->vocabulary as $item) {
            $readings[] = [$item->id, $item->pronunciationNative];
            $natives[] = [$item->id, $item->translationNative];
        }

        $out = [];
        foreach ($readings as [$address, $reading]) {
            $foreign = $words?->foreignLetters($reading) ?? [];
            if ($foreign !== []) {
                $out[] = new LessonViolation(self::CODE, $address, "the reading «{$reading}» has letters of another alphabet: ".implode(' ', $foreign));
            }
        }
        foreach ($natives as [$address, $text]) {
            $mixed = StageText::mixedWords($text);
            $other = StageText::otherCyrillic($text, $context->native);
            if ($mixed !== [] || $other !== []) {
                $out[] = new LessonViolation(self::CODE, $address, "«{$text}» has letters of another alphabet inside a word: ".implode(' ', [...$mixed, ...$other]));
            }
        }

        return $out;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;

/**
 * `learner.gender` — a warning. In Russian, Ukrainian and Belarusian the learner's own words say the learner's gender in the
 * past tense after «я»: the native frame — said with each native filler — follows LEARNER_GENDER (TEXT QUALITY; a male
 * learner never says «я работала»). The past form is found by the native pack's `gendered_past_pattern`; its gender is read
 * by its ending: «-ла» (and «-лась», «-лася») feminine, «-ло» neuter — no learner's, anything else masculine («-л», uk «-в»,
 * be «-ў», «міг», «нёс»). An unknown gender takes no gendered form at all.
 */
final class LearnerGender implements SkeletonRule
{
    public const CODE = 'learner.gender';

    /** The learner's languages with a past tense by gender — and a pattern for it in their pack. */
    public const LANGUAGES = ['ru', 'uk', 'be'];

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
        if (! in_array($context->native->code, self::LANGUAGES, true) || ! $context->native->has('gendered_past_pattern')) {
            return [];
        }
        $words = $context->nativeWords();
        $out = [];
        foreach ($skeleton->frames as $frame) {
            $texts = $frame->phrase->fillers() === []
                ? [$frame->id() => $frame->phrase->frameNative]
                : [];
            foreach ($frame->phrase->fillers() as $index => $filler) {
                $texts[$frame->id().'.f'.($index + 1)] = FrameText::fill($frame->phrase->frameNative, $filler->native);
            }
            foreach ($texts as $address => $text) {
                foreach ($words->genderedPast($text) as $form) {
                    $gender = self::genderOf($form);
                    if ($gender === 'neuter' || $context->learnerGender === null || $gender !== $context->learnerGender->value) {
                        $out[] = new LessonViolation(self::CODE, (string) $address, "«{$text}» says «{$form}» ({$gender}) of a learner who is ".($context->learnerGender->value ?? 'of unknown gender'));
                        break;
                    }
                }
            }
        }

        return $out;
    }

    /** The gender a past form says: by its ending. */
    public static function genderOf(string $form): string
    {
        return match (true) {
            preg_match('/ла(?:сь|ся)?$/u', $form) === 1 => VoiceGender::Female->value,
            preg_match('/ло(?:сь|ся)?$/u', $form) === 1 => 'neuter',
            default => VoiceGender::Male->value,
        };
    }
}

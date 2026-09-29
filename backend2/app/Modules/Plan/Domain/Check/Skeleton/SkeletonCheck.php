<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\FillerCommonPrefix;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\FillerRepeatsFrame;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\FrameCount;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\FrameKnownRepeat;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\FrameMissingItem;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\FrameMustSay;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\FrameNativeTwin;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\FrameTooLong;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\LearnerGender;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\PartnerItemMissing;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\PartnerItemUnknown;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\PartnerNamesFiller;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\PartnerPairsMany;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\PartnerTooLong;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\PronunciationEqualsNative;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\PronunciationForeignScript;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\PronunciationNearNative;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\SkeletonIds;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\VocabCount;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\VocabDefinitionLanguage;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\VocabNotFound;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\VocabReading;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\VocabStopWord;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\VocabUsedInWrong;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/**
 * THE CHECK OF THE SKELETON (наряд GEN-4, 3.3) — code only, no meaning: every rule reads what the skeleton writes against what
 * it was ordered with ({@see SkeletonContext}). A FATAL finding asks the skeleton once more; a warning sends its card — a frame,
 * a partner line, a word — to a repair. The rules, fatal first, in the order of the order; four beside it — the shape guards
 * `skeleton.ids` and `partner.pairs_many` (the gate run's: a line paired with two frames leaves the dialogue no exchange for
 * one of them), and `vocab.reading`, `vocab.definition_language`, the codes the repair prompt names for a word it keeps.
 */
final readonly class SkeletonCheck
{
    /** @var list<SkeletonRule> */
    private array $rules;

    /** @param list<SkeletonRule>|null $rules */
    public function __construct(?array $rules = null)
    {
        $this->rules = $rules ?? self::rules();
    }

    /** @return list<SkeletonRule> */
    public static function rules(): array
    {
        return [
            new FrameCount,
            new FrameMustSay,
            new FrameKnownRepeat,
            new PartnerItemMissing,
            new PartnerItemUnknown,
            new VocabNotFound,
            new VocabCount,
            new PronunciationForeignScript,
            new PronunciationEqualsNative,
            new SkeletonIds,
            new PartnerPairsMany,
            new PronunciationNearNative,
            new FrameNativeTwin,
            new FillerCommonPrefix,
            new PartnerNamesFiller,
            new VocabStopWord,
            new VocabUsedInWrong,
            new VocabReading,
            new VocabDefinitionLanguage,
            new LearnerGender,
            new FillerRepeatsFrame,
            new FrameTooLong,
            new PartnerTooLong,
            new FrameMissingItem,
        ];
    }

    /** @return list<LessonViolation> */
    public function run(Skeleton $skeleton, SkeletonContext $context): array
    {
        $out = [];
        foreach ($this->rules as $rule) {
            $out = [...$out, ...$rule->findings($skeleton, $context)];
        }

        return $out;
    }
}

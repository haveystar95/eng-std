<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/**
 * `partner.names_filler` — a warning (PARTNER LINES: «A never names the learner's placeholder values»; a reply to an ask frame
 * «names none of them»). A partner line holds, as a string, a filler of a frame it pairs with — in either language.
 */
final class PartnerNamesFiller implements SkeletonRule
{
    public const CODE = 'partner.names_filler';

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
        foreach ($skeleton->partnerLines as $line) {
            foreach ($line->pairsWith as $number) {
                $frame = $skeleton->frameOfItem($number);
                if ($frame === null) {
                    continue;
                }
                foreach ($frame->phrase->fillers() as $filler) {
                    foreach ([[$line->textTarget, $filler->target], [$line->textNative, $filler->native]] as [$text, $value]) {
                        if (trim($value) !== '' && mb_stripos($text, trim($value)) !== false) {
                            $out[] = new LessonViolation(self::CODE, $line->id, "the line names «{$value}», a filler of {$frame->id()}");

                            continue 4;
                        }
                    }
                }
            }
        }

        return $out;
    }
}

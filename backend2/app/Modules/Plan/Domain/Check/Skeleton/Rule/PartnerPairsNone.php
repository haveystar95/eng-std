<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use App\Modules\Plan\Domain\Lesson\SkeletonFrame;

/**
 * `partner.pairs_none` — FATAL (наряд GEN-4b §2c). A partner line with an empty `pairs_with` is the REMAINDER — the line left
 * over when every frame already has its line — never a choice: while a frame is left with no line of its own, a line that
 * pairs with nothing is the line that frame was owed. The gate run of GEN-4 (gpt-5.4, day 02) paired «It is probably a muscle
 * strain.» and «Come back in five days…» with nothing beside «What does ___ mean?» and «I understand: ___» with no line; the
 * dialogue answered the two lines with the two frames anyway, and DIALOGUE_COUNT, which counted them apart, left two
 * exchanges it filled with A lines of its own — `partner.unlinked`, twice.
 */
final class PartnerPairsNone implements SkeletonRule
{
    public const CODE = 'partner.pairs_none';

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
        $alone = array_map(
            static fn (SkeletonFrame $f): string => $f->id(),
            array_values(array_filter($skeleton->frames, static fn (SkeletonFrame $f): bool => ! $skeleton->isPaired($f))),
        );
        if ($alone === []) {
            return [];
        }

        $out = [];
        foreach ($skeleton->partnerLines as $line) {
            if ($line->pairsWith === []) {
                $out[] = new LessonViolation(self::CODE, $line->id, "«{$line->textTarget}» pairs with no frame while ".implode(', ', $alone).' have no line of their own: an empty pairs_with is only the line left over when every frame has its line');
            }
        }

        return $out;
    }
}

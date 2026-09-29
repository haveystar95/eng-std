<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/**
 * `partner.pairs_many` — FATAL (a shape guard beside the order's list, наряд GEN-4, found by its gate run). A partner line
 * pairs with the items of ONE frame at most. The dialogue says a partner line once, in one exchange, and a learner line
 * stands on one frame; DIALOGUE_COUNT gives a line one exchange. A line paired with two frames («For you the basic account
 * fits. It costs four euros a month» for «Which account suits ___?» and «What does ___ cost a month?») leaves one of them
 * with no exchange it may stand in — the dialogue cannot say it again (`partner.twice`) nor answer it with a line of its
 * own (`partner.unlinked`), and the day fails on `frame.unused` after two paid dialogues. Two items of one frame (the same
 * pattern asked twice) are one frame: no finding.
 */
final class PartnerPairsMany implements SkeletonRule
{
    public const CODE = 'partner.pairs_many';

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
        $out = [];
        foreach ($skeleton->partnerLines as $line) {
            $frames = [];
            foreach ($line->pairsWith as $number) {
                $frame = $skeleton->frameOfItem($number);
                if ($frame !== null) {
                    $frames[$frame->id()] = true;
                }
            }
            if (count($frames) > 1) {
                $out[] = new LessonViolation(self::CODE, $line->id, 'pairs_with '.implode(', ', $line->pairsWith).' names the frames '.implode(', ', array_keys($frames)).' — a line is said once, in one exchange, with one frame');
            }
        }

        return $out;
    }
}

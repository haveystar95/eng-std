<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check\Skeleton\Rule;

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\AskedFor;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonContext;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonRule;
use App\Modules\Plan\Domain\Lesson\Skeleton;

/**
 * `partner.yes_no_missing` — a WARNING the repairs take right after a placeholder word, FATAL only beyond them (наряд GEN-4c,
 * {@see LessonCodes::BUDGETED}). PARTNER LINES (`lesson_skeleton.v1.1`): «To a yes-or-no question it has TWO parts, the answer
 * ("Da." / "Nu.") and the fact». A statement of the partner paired with an `ask` frame that asks yes or no ({@see AskedFor})
 * opens with no word of the target's `yes_no`: the e2e of GEN-4b answered «Postul include ___?» with «Postul include lucru cu
 * clienții și pregătirea documentelor.». The reply's first word is read, case and marks aside — «No test is needed now.»
 * opens with its «no».
 */
final class PartnerYesNoMissing implements SkeletonRule
{
    public const CODE = 'partner.yes_no_missing';

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
        $words = $context->targetReading(...AskedFor::keys());
        if ($words === null) {
            return [];
        }
        [$yes, $no] = [...array_map(static fn (string $w): string => mb_convert_case($w, MB_CASE_TITLE), $words->yesNoWords()), '', ''];
        $out = [];
        foreach ($skeleton->repliesToAsks() as [$line, $frame]) {
            if (isset($out[$line->id]) || AskedFor::of($frame->phrase->frameTarget, $words)->kind !== AskedFor::YES_NO || $words->yesNoOpening($line->textTarget) !== null) {
                continue;
            }
            $out[$line->id] = new LessonViolation(
                self::CODE,
                $line->id,
                "a yes-or-no question: start with {$yes}. or {$no}., then one general fact (the reply to {$frame->id()} «{$frame->phrase->frameTarget}»)",
            );
        }

        return array_values($out);
    }
}

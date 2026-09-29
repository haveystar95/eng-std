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
 * `partner.yes_no_extra` — a WARNING the repairs take right after a placeholder word, FATAL only beyond them (наряд GEN-4c,
 * {@see LessonCodes::BUDGETED}). PARTNER LINES (`lesson_skeleton.v1.1`): «To a question of which, what, how many or when it is
 * the fact itself, with no "Da." / "Nu."». A statement of the partner paired with an `ask` frame that asks for a fact
 * ({@see AskedFor}) opens with a word of the target's `yes_no`: the e2e of GEN-4 answered «Care este programul de lucru?» with
 * «Da. Programul este de luni până vineri…», the Luna day 14 «Care sunt atribuțiile pentru ___?» with «Da, postul include…».
 */
final class PartnerYesNoExtra implements SkeletonRule
{
    public const CODE = 'partner.yes_no_extra';

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
            $asked = AskedFor::of($frame->phrase->frameTarget, $words);
            $opening = $words->yesNoOpening($line->textTarget);
            if (isset($out[$line->id]) || $asked->kind !== AskedFor::FACT || $opening === null) {
                continue;
            }
            $out[$line->id] = new LessonViolation(
                self::CODE,
                $line->id,
                "not a yes-or-no question: the fact itself, no {$yes}./{$no}. (the reply to {$frame->id()} «{$frame->phrase->frameTarget}», which asks with «{$asked->word}», opens with «{$opening}»)",
            );
        }

        return array_values($out);
    }
}

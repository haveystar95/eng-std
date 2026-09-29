<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueCheck;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonCheck;

/**
 * EVERY CODE A DAY'S BUILD COUNTS (наряд GEN-4): the codes of the two stages' rules — {@see SkeletonCheck}, {@see DialogueCheck},
 * each rule's code the name of its finding and FATAL or not by its rule — and the one code a model finds, not the code: a
 * native frame said with a filler that does not read, by the seam judge ({@see JUDGED}). One counter is no finding at all: a
 * judge that did not answer ({@see JUDGE_UNAVAILABLE}).
 *
 * Canon with the rule of every code — `docs/plan-v2.md` §4.
 */
final class LessonCodes
{
    /** A native frame said with one of its fillers does not read — the seam judge's, once a day, before the dialogue. */
    public const FILLER_NATIVE_SEAM = 'filler.native_seam';

    /** A judge was asked and gave no usable answer: the day's native seams, or a learner's slot, went unread. */
    public const JUDGE_UNAVAILABLE = 'judge.unavailable';

    /** The codes a model finds, not the code: the native seams, read by the seam judge. */
    public const JUDGED = [self::FILLER_NATIVE_SEAM];

    /** @return list<string> every code of a finding, in the order the report lists them: the skeleton's, the dialogue's, the judge's */
    public static function all(): array
    {
        return [
            ...array_map(static fn (StageRule $r): string => $r->code(), SkeletonCheck::rules()),
            ...array_map(static fn (StageRule $r): string => $r->code(), DialogueCheck::rules()),
            ...self::JUDGED,
        ];
    }

    /** @return list<string> the codes whose finding asks its stage once more */
    public static function fatal(): array
    {
        return array_values(array_map(
            static fn (StageRule $r): string => $r->code(),
            array_filter([...SkeletonCheck::rules(), ...DialogueCheck::rules()], static fn (StageRule $r): bool => $r->fatal()),
        ));
    }

    public static function isFatal(string $code): bool
    {
        return in_array($code, self::fatal(), true);
    }

    /**
     * The fatal findings among `$findings`.
     *
     * @param  list<LessonViolation>  $findings
     * @return list<LessonViolation>
     */
    public static function fatalOf(array $findings): array
    {
        $fatal = self::fatal();

        return array_values(array_filter($findings, static fn (LessonViolation $v): bool => in_array($v->code, $fatal, true)));
    }

    /**
     * Why a stage failed: the fatal codes left, each once, in the order found — `fatal: frame.count, vocab.not_found`.
     *
     * @param  list<LessonViolation>  $fatal
     */
    public static function failReason(array $fatal): string
    {
        return 'fatal: '.implode(', ', array_values(array_unique(array_map(static fn (LessonViolation $v): string => $v->code, $fatal))));
    }
}

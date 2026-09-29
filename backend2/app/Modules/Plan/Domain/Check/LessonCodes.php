<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Check;

use App\Modules\Plan\Domain\Check\Dialogue\DialogueCheck;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\PartnerYesNoExtra;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\PartnerYesNoMissing;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\PronunciationForeignScript;
use App\Modules\Plan\Domain\Check\Skeleton\Rule\VocabFromPlaceholder;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonCheck;
use App\Modules\Plan\Domain\Lesson\LessonCard;

/**
 * EVERY CODE A DAY'S BUILD COUNTS (наряд GEN-4): the codes of the two stages' rules — {@see SkeletonCheck}, {@see DialogueCheck},
 * each rule's code the name of its finding and FATAL or not by its rule — and the codes a model finds, not the code, by the
 * seam judge ({@see JUDGED}): a native frame said with a filler that does not read, and (наряд GEN-4c) a reply to a question of
 * the learner's that names a filler of it by meaning. One counter is no finding at all: a judge that did not answer
 * ({@see JUDGE_UNAVAILABLE}).
 *
 * Canon with the rule of every code — `docs/plan-v2.md` §4.
 */
final class LessonCodes
{
    /** A native frame said with one of its fillers does not read — the seam judge's, once a day, before the dialogue. */
    public const FILLER_NATIVE_SEAM = 'filler.native_seam';

    /**
     * A partner's reply to a question of the learner's names a filler of that question — in another form or by its meaning, the
     * same things in other words — the seam judge's second question in the same call (наряд GEN-4c, `lesson_seam_judge.v1.2`).
     * `partner.names_filler` finds the filler said as it is written; this, what no code can find.
     */
    public const NAMES_FILLER_MEANING = 'partner.names_filler_meaning';

    /**
     * NOT A FINDING — A NOTE TO A REPAIR (наряд GEN-4c): the words of the day only the card sent to a repair says
     * ({@see \App\Modules\Plan\Domain\Check\Skeleton\CarriedWords}), listed beside its findings so that the repair keeps them —
     * a repair that drops one is thrown away as `vocab.not_found`. Never counted, never stored, in no list of codes.
     */
    public const REPAIR_NOTE_CARRIED = 'vocab.carried';

    /** A judge was asked and gave no usable answer: the day's native seams, or a learner's slot, went unread. */
    public const JUDGE_UNAVAILABLE = 'judge.unavailable';

    /** The codes a model finds, not the code: the native seams and the replies that name a filler, read by the seam judge. */
    public const JUDGED = [self::FILLER_NATIVE_SEAM, self::NAMES_FILLER_MEANING];

    /**
     * THE CODES THE REPAIRS TAKE FIRST, FATAL ONLY BEYOND THEM (наряд GEN-4b §3, GEN-4c): a warning whose cards go to the stage's
     * repairs ahead of every other card, and a finding the stage cannot keep when the cards of these codes are more than a
     * stage's repairs, or one of them stands at no card a repair takes (the title, the description, the role) — a letter of
     * another writing in a reading; a word of the day said only through a placeholder filler; a reply to a question of the
     * learner's that opens with no yes or no, or with one it is not asked for.
     */
    public const BUDGETED = [PronunciationForeignScript::CODE, VocabFromPlaceholder::CODE, PartnerYesNoMissing::CODE, PartnerYesNoExtra::CODE];

    /**
     * THE ORDER THE REPAIRS TAKE CARDS IN (наряд GEN-4c §4): which cards a stage's repairs go to when they are more than its
     * repairs — a card goes by the first of its findings in this list: a letter of another writing, then a placeholder word,
     * then a yes or no, then a reply the seam judge finds naming a filler; every other card after them, in its stage's order.
     * The cards taken are repaired in their stage's order (frames, lines, words — what the others stand on first). The judge's
     * code is here and not in {@see BUDGETED}: the judge reads a skeleton already taken, and a judge is never fatal.
     */
    public const REPAIR_ORDER = [
        PronunciationForeignScript::CODE => 0,
        VocabFromPlaceholder::CODE => 1,
        PartnerYesNoMissing::CODE => 2,
        PartnerYesNoExtra::CODE => 2,
        self::NAMES_FILLER_MEANING => 3,
    ];

    /** Where a finding of this code puts its card in the repairs' queue: its place in {@see REPAIR_ORDER}, or after them all. */
    public static function repairRank(string $code): int
    {
        return self::REPAIR_ORDER[$code] ?? count(array_unique(self::REPAIR_ORDER));
    }

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
     * The findings of a budgeted code a stage cannot repair ({@see BUDGETED}): every one of them when their cards are more than
     * `$repairs`, or when one of them stands at no card; none when the repairs can take them all.
     *
     * @param  list<LessonViolation>  $findings
     * @return list<LessonViolation>
     */
    public static function overBudget(array $findings, int $repairs): array
    {
        $budgeted = array_values(array_filter($findings, static fn (LessonViolation $v): bool => in_array($v->code, self::BUDGETED, true)));
        $cards = [];
        foreach ($budgeted as $finding) {
            $card = LessonCard::at($finding->address);
            if ($card === null) {
                return $budgeted;
            }
            $cards[$card->address] = true;
        }

        return count($cards) > $repairs ? $budgeted : [];
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

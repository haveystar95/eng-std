<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\ExerciseMode;

/**
 * WHICH OF THE SIX KNOBS ACTUALLY REACHES A TRAINER TODAY — stated in Domain, next to the knobs
 * themselves, and reported by the plan session rather than left to be discovered.
 *
 * The наряд that introduced the knobs said it plainly: «Если ручка не поддерживается существующим
 * режимом — выставляется, но игнорируется, с пометкой в отчёте какие именно (это задел для
 * тренажёров, не требование переделать их сейчас).» The dangerous version of that arrangement is
 * the silent one — a level configured with `tts_rate: slow` that produces exactly the same audio,
 * and nobody able to say whether the setting is wrong or unread. So the list is code, it is
 * exhaustive, and the session says out loud which knobs it applied.
 *
 * The two that land:
 *
 *   `distractor_closeness`  → {@see \App\Modules\Learning\Domain\ValueObject\OptionsPolicy}, which
 *                             the card assembler has always read. `far` is the session's own
 *                             neighbours; anything else is the ordinary distractor reader.
 *   `mc_options`            → how many options a choice card is dealt. The card has never had a
 *                             fixed shape on the wire — `pick_correct` deals three and
 *                             `multiple_choice` four — so a count is something the trainer already
 *                             understands, and no UI contract moves.
 *
 * The four that do not, and why each is a задел rather than an oversight:
 *
 *   `cloze_blanks`  the cloze card is not assembled on the server at all today — there is no gap to
 *                   cut, so there is no number of gaps to choose.
 *   `bank_extra`    {@see ChipShuffler} deals exactly the chips the answer needs. Dealing extras is
 *                   a change to what the word bank card IS, which is a trainer change.
 *   `typing_hint`   the answer carries `used_hint`, but nothing on the server ever ISSUES one.
 *   `tts_rate`      no audio parameter crosses this API in either direction.
 */
final class PlanKnobSupport
{
    public const MC_OPTIONS = 'mc_options';
    public const DISTRACTOR_CLOSENESS = 'distractor_closeness';
    public const CLOZE_BLANKS = 'cloze_blanks';
    public const BANK_EXTRA = 'bank_extra';
    public const TYPING_HINT = 'typing_hint';
    public const TTS_RATE = 'tts_rate';

    /** @var list<string> */
    public const APPLIED = [self::MC_OPTIONS, self::DISTRACTOR_CLOSENESS];

    /** @var list<string> */
    public const IGNORED = [self::CLOZE_BLANKS, self::BANK_EXTRA, self::TYPING_HINT, self::TTS_RATE];

    /**
     * The knobs THIS mode's card reads. A mode not named here reads none — `intro`, `scramble` and
     * `speaking` have nothing to tune, and that is a fact about the cards, not an omission.
     *
     * @return list<string>
     */
    public static function appliedTo(ExerciseMode $mode): array
    {
        return match ($mode) {
            ExerciseMode::MultipleChoice => [self::MC_OPTIONS, self::DISTRACTOR_CLOSENESS],
            ExerciseMode::PickCorrect => [self::DISTRACTOR_CLOSENESS],
            default => [],
        };
    }

    /**
     * The knobs this mode is CONFIGURED with but does not read — what the report calls «выставлено,
     * но игнорируется». Named per mode rather than as one global list because that is the question
     * a person actually has: «я включил медленный TTS, почему диктант звучит так же».
     *
     * @return list<string>
     */
    public static function ignoredBy(ExerciseMode $mode): array
    {
        return match ($mode) {
            ExerciseMode::Cloze => [self::CLOZE_BLANKS],
            ExerciseMode::WordBank => [self::BANK_EXTRA],
            ExerciseMode::Typing => [self::TYPING_HINT],
            ExerciseMode::Listening, ExerciseMode::Dictation => [self::TTS_RATE],
            default => [],
        };
    }
}

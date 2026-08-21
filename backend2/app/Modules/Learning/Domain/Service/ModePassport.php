<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;

/**
 * The CONSTRUCTIVE MINIMUM phase a trainer needs to make sense at all.
 *
 * {@see \App\Modules\Learning\Domain\ValueObject\ModeAdmission} lets an admin place a trainer
 * anywhere on the ladder — that is a product judgement, moved by evidence, not a deploy. This is
 * the floor UNDER that judgement: a trainer whose question a pair simply cannot answer yet,
 * whatever the admin screen is told. `speaking` asked before graduation has nothing to recall;
 * `multiple_choice` recognition asked before the first meeting has nothing to compare the term
 * against. A rung below the passport is not a stricter setting, it is a setting that silently
 * does nothing — the trainer would be admitted and would still have no honest card to deal.
 *
 * One constant map, next to {@see LearningLadder} for the same reason that function is pure and
 * table-tested: the client never reads this (the passport is an admin-side guard, not a session
 * rule), but the two must not drift apart, so the floor is stated in the SAME coordinates
 * ({@see Acquisition}) that {@see LearningLadder::stepFor()} already turns into a rung — see the
 * consistency test at `tests/Unit/Learning/ModePassportTest.php`.
 */
final class ModePassport
{
    /** The earliest acquisition phase this trainer's question can honestly be asked at. */
    public static function floorFor(ExerciseMode $mode): Acquisition
    {
        return match ($mode) {
            // Shown, not asked — the one trainer whose floor is the ladder's own floor.
            ExerciseMode::Intro => Acquisition::New,
            // Recognition compares the term against a translation the learner has not yet met.
            ExerciseMode::MultipleChoice => Acquisition::Learning,
            // Assembly, choice and production all need a graduated pair: dictation and typing
            // read from memory, word_bank/cloze/scramble/pick_correct build from or against
            // content that is only stable once the pair is off the recognition rungs, and
            // speaking has nothing to recall before then.
            ExerciseMode::WordBank,
            ExerciseMode::Cloze,
            ExerciseMode::Scramble,
            ExerciseMode::PickCorrect,
            ExerciseMode::Typing,
            ExerciseMode::Listening,
            ExerciseMode::Dictation,
            ExerciseMode::Speaking,
            // `description_match` is recognition, like multiple_choice — but its question is a
            // SENTENCE in the language being learned, about a word met minutes ago. Reading it is
            // itself the exercise, and a pair that has not yet been through the recognition rungs
            // is being asked to parse an unknown definition to find an unknown word. Graduated,
            // beside the other trainers that assume the pair is stable.
            ExerciseMode::DescriptionMatch => Acquisition::Graduated,
        };
    }

    /** Is a configured threshold at or above this trainer's constructive minimum? */
    public static function meetsFloor(ExerciseMode $mode, Acquisition $configured): bool
    {
        return self::rank($configured) >= self::rank(self::floorFor($mode));
    }

    /**
     * The floor read as a rung, through the SAME function a stored rule's own threshold is —
     * {@see \App\Modules\Learning\Domain\ValueObject\ModeRule::minStep()} — so a future change to
     * the ladder cannot silently move the passport out of step with it.
     */
    public static function floorStepFor(ExerciseMode $mode): int
    {
        return LearningLadder::stepFor(self::floorFor($mode), 0, LearningLadder::FIRST_LADDER_STEP)
            ?? LearningLadder::STEP_ASSEMBLY;
    }

    /** A short, mode-specific reason a threshold below the floor is refused — not just "too low". */
    public static function reasonFor(ExerciseMode $mode): string
    {
        return match ($mode) {
            ExerciseMode::Intro => 'intro показывает слово при первом знакомстве — рубеж не может быть строже «новое».',
            ExerciseMode::MultipleChoice => 'узнавание сравнивает термин с переводом — до первого предъявления слова сравнивать нечего.',
            ExerciseMode::Speaking => 'speaking — режим воспоминания, до выпуска слову нечего вспоминать.',
            ExerciseMode::WordBank,
            ExerciseMode::Cloze,
            ExerciseMode::Scramble,
            ExerciseMode::DescriptionMatch => 'description_match спрашивает определением на изучаемом языке — до выпуска это чтение незнакомого текста ради незнакомого слова.',
            ExerciseMode::PickCorrect,
            ExerciseMode::Typing,
            ExerciseMode::Listening,
            ExerciseMode::Dictation => "{$mode->value} — тренажёр для выпущенных слов; до выпуска слову нечего показывать в этом формате.",
        };
    }

    private static function rank(Acquisition $acquisition): int
    {
        return match ($acquisition) {
            Acquisition::New => 0,
            Acquisition::Learning => 1,
            Acquisition::Graduated => 2,
        };
    }
}

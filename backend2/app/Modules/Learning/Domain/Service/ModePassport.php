<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Shared\Domain\Service\LanguageModeSupport;

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

    /**
     * Is this trainer closed for this LANGUAGE — a different question from every other one on this
     * class, and deliberately kept apart from it.
     *
     * The passport above and the admission matrix are both about the LEARNER: has this pair earned
     * the trainer yet. This is about the CARD's language: can the trainer be honest in it at all
     * (DECISIONS п. 130). The two produce the same visible outcome — the mode is not dealt — and
     * have opposite cures: one is a threshold on an admin screen, the other is a judge or a
     * recogniser that does not exist yet. Reporting them with one reason is how «включи pick_correct
     * для польского» becomes a support request nobody can answer.
     */
    public static function closedByLanguage(ExerciseMode $mode, string $lang): bool
    {
        return ! LanguageModeSupport::supports($lang, $mode->value);
    }

    /**
     * Why this trainer is closed FOR THIS LANGUAGE. Never mixed with {@see reasonFor()}, which
     * answers «closed by the matrix / below the passport floor».
     */
    public static function languageReasonFor(ExerciseMode $mode, string $lang): string
    {
        if (LanguageModeSupport::modesFor($lang) === []) {
            return "«{$lang}» в v1 не тренируется: коллекция на нём справочная — перевод и озвучка, "
                . 'без пула и без расписания, поэтому тренажёров у неё нет ни одного.';
        }

        return match ($mode) {
            ExerciseMode::PickCorrect => 'pick_correct открывается языку тогда, когда для него есть '
                . "контроль качества дистракторов; в v1 он есть только для английского, для «{$lang}» — нет.",
            default => "«{$mode->value}» закрыт для «{$lang}» языковым справочником — не матрицей и "
                . 'не паспортом ступени.',
        };
    }

    /**
     * Available, but only with a network — the honest middle answer between «есть» and «нет»
     * (DECISIONS п. 48). iOS has no on-device recognition for pl or ro, so the two listening
     * trainers work online and, offline, are Skipped free of charge.
     */
    public static function onlineOnlyReasonFor(ExerciseMode $mode, string $lang): string
    {
        return "«{$mode->value}» для «{$lang}» работает только с сетью: оффлайн-распознавания речи "
            . 'для этого языка в iOS нет, и без сети карточка пропускается без штрафа.';
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

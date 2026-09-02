<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Service;

use App\Modules\Learning\Domain\ValueObject\ExerciseMode;

/**
 * THE INTERLOCUTOR'S OWN LINE IS UNDERSTOOD, NEVER PRODUCED — the one statement of that rule.
 *
 * A line the other person says — «Hello. What seems to be the problem with your child?», «Does your
 * child have a fever?» — is in the day so the learner will RECOGNISE it when it is said to them. It
 * is the one card of a plan they will never say. The live run asked them to write it from dictation
 * and to assemble it word by word out of chips (Д-8, Д-33): the learner produced the doctor's
 * question and the log recorded it as their own vocabulary.
 *
 * So recognition stays whole — meeting it, choosing its meaning, hearing it — and everything that
 * asks for the SENTENCE BACK falls out.
 *
 * ## Two readers, and they must not drift
 *
 * {@see \App\Modules\Learning\Application\Service\PlanStandings} keeps a production step out of the
 * CHECKLIST, which is the important half: a step that is owed and cannot be answered is a stage
 * that never closes, a day that never passes, and a day n+1 that is never written.
 * {@see \App\Modules\Learning\Application\Service\StudyCardAssembler} refuses to BUILD one, which
 * catches every other way a mode reaches a card — a soft run of a day opened out of turn picks its
 * own trainer off the ordinary ladder and never sees the checklist at all.
 *
 * ## Whose line it is has TWO sources
 *
 * `terms.speaker = 'role'` is written by the day's generator for an item it marked as the other
 * side's. It is not the only place such a sentence comes from: the day's skeleton carries
 * `role_brief.role.opening_lines`, and when the model puts one of those in the day's cards as well
 * it arrives with no speaker at all. Matching the TEXT is what covers the second source, and it
 * covers days already written, which a fix at generation time never could.
 */
final class RoleLineModes
{
    /** `terms.speaker` for a line the INTERLOCUTOR says. A literal — Learning does not import Vocabulary Domain. */
    public const SPEAKER_ROLE = 'role';

    /**
     * The trainers that ask the learner to PRODUCE the sentence.
     *
     * `listening` is deliberately absent: it plays the line and asks what was heard, which is the
     * very skill a role line exists to teach. Deliberately NOT {@see ExerciseMode::isProduction()},
     * which answers a different question (can this mode earn `easy`) and puts `listening` on the
     * wrong side of this one.
     */
    public static function produces(ExerciseMode $mode): bool
    {
        return match ($mode) {
            ExerciseMode::WordBank,
            ExerciseMode::Scramble,
            ExerciseMode::Typing,
            ExerciseMode::Speaking,
            ExerciseMode::Cloze,
            ExerciseMode::Dictation => true,
            ExerciseMode::MultipleChoice,
            ExerciseMode::DescriptionMatch,
            ExerciseMode::Listening,
            ExerciseMode::PickCorrect,
            ExerciseMode::Intro => false,
        };
    }

    /**
     * Is this card the interlocutor's line — by its speaker, or by being one of the day's openings?
     *
     * @param  array<string, true>  $spokenByRole  {@see index()}
     */
    public static function isRoleLine(?string $speaker, string $text, array $spokenByRole = []): bool
    {
        return $speaker === self::SPEAKER_ROLE || isset($spokenByRole[self::key($text)]);
    }

    /**
     * The day's opening lines as a lookup, normalised the way option texts are compared everywhere
     * else here: trimmed and case-folded, and nothing cleverer. The model writes the card and the
     * opening line from the same sentence, so anything beyond a fold would be guessing.
     *
     * @param  list<string>  $lines
     * @return array<string, true>
     */
    public static function index(array $lines): array
    {
        $out = [];
        foreach ($lines as $line) {
            $key = self::key($line);
            if ($key !== '') {
                $out[$key] = true;
            }
        }

        return $out;
    }

    private static function key(string $text): string
    {
        return mb_strtolower(trim($text));
    }
}

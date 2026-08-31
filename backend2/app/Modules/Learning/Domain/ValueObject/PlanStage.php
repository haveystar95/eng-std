<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * THE THREE STAGES a word of a plan lives through, and the whole of the plan's «чем».
 *
 * A plan day owns an ordinary collection, so its words could have ridden the ordinary acquisition
 * ladder ({@see \App\Modules\Learning\Domain\Service\LearningLadder}) and been done with. They do
 * not, and the reason is the deadline: the ordinary ladder is a function of how many successful
 * reviews a pair has collected, which is a good rule for a word with no date on it and a useless
 * one for a word that has to be sayable on the twelfth. A plan word climbs on a schedule the plan
 * owns — three stages, a fixed set of trainers each, one stage per day.
 *
 * The stages are ordered by what they take AWAY:
 *
 *   A  the card is on the screen the whole time. Meet it, recognise it, put it together, read it
 *      aloud. Nothing here asks the learner to produce anything from memory.
 *   B  the card is in a sentence and the sentence is on the screen. Fill the gap, hear it, write it.
 *   C  nothing is on the screen. Take it down by ear, tell a right sentence from a wrong one.
 *
 * «Читать вслух рано, говорить без текста поздно.» Speaking is dealt on the FIRST day, which is the
 * one decision here that surprises people: the mode that looks hardest comes first. Reading aloud on
 * day one is how the mouth learns the word at all, and holding speaking back until the end is how a
 * learner arrives at the event having never said any of it out loud. The trainer does not change;
 * what is on the screen does.
 *
 * WHICH stages actually deal it depends on what the card is, and that lives in the ladder's table
 * ({@see \App\Modules\Learning\Domain\Service\PlanStageLadder}), not here: a spoken LINE is
 * said aloud in A with the text on the screen and again in B without it, because saying it is the
 * whole ability; a WORD is said once, in A, and its later stages are about producing it in writing.
 * A line has no stage C at all.
 *
 * ## This is the «чем», never the «когда»
 *
 * The project's standing split: the ladder says WHICH trainer, the repetition planner says WHEN a
 * word comes back. Nothing here touches `due_at`, SM-2 or any interval — a stage success is written
 * to the review log as an ordinary review and the planner reads it exactly as it reads every other
 * one. Mixing the two is what would let «сегодня ступень B» quietly reschedule a word.
 */
enum PlanStage: string
{
    case A = 'a';
    case B = 'b';
    case C = 'c';

    public static function first(): self
    {
        return self::A;
    }

    /** The stage after this one, or null at the top. A collection lives three stages, always. */
    public function next(): ?self
    {
        return match ($this) {
            self::A => self::B,
            self::B => self::C,
            self::C => null,
        };
    }

    /**
     * What the SPEAKING card shows at this stage.
     *
     * Not a mode and not a setting — a description of the card that rides on the plan session's
     * task so the client knows what to put on the screen. The trainer itself is one trainer with
     * one contract; this says which of its faces this stage wants.
     */
    public function speakingForm(string $kind = 'word'): string
    {
        // A LINE is the sentence itself, so its two speaking cards are «read this line» and «say
        // that line with nothing on the screen». There is no third face and no stage C: the
        // learner is never asked to recite a DIFFERENT sentence about a line they already say.
        if ($kind === 'line') {
            return $this === self::A ? 'word_on_screen' : 'example_from_memory';
        }

        return match ($this) {
            self::A => 'word_on_screen',        // translation + the word itself, read it aloud
            self::B => 'example_with_text',     // the example is on the screen, read it aloud
            self::C => 'example_from_memory',   // nothing on the screen, say it
        };
    }
}

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
 *   A  the word is on the screen the whole time. Meet it, recognise it twice, assemble it, read it
 *      aloud. Nothing here asks the learner to produce anything from memory.
 *   B  the word is in a sentence and the sentence is on the screen. Fill the gap, put the words in
 *      order, hear it and write it, read the example aloud.
 *   C  nothing is on the screen. Type it, take the whole sentence down by ear, tell a right
 *      sentence from a wrong one, say the example without looking.
 *
 * «Читать вслух рано, говорить без текста поздно.» Speaking appears in ALL THREE, which is the one
 * decision here that surprises people: the mode that looks hardest is dealt on the first day. It is
 * not the same card three times — in A the learner reads a word off the screen, in B a sentence off
 * the screen, in C the sentence from memory. Reading aloud on day one is how the mouth learns the
 * word at all, and holding speaking back until the end is how a learner arrives at the event having
 * never said any of it out loud. The trainer does not change; what is on the screen does.
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
    public function speakingForm(): string
    {
        return match ($this) {
            self::A => 'word_on_screen',        // translation + the word itself, read it aloud
            self::B => 'example_with_text',     // the example is on the screen, read it aloud
            self::C => 'example_from_memory',   // nothing on the screen, say it
        };
    }
}

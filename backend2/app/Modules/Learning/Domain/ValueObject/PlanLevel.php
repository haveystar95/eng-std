<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * How much {@see \App\Modules\Learning\Domain\ValueObject\PlanOutline}'s learner can already
 * produce. Deliberately NOT CEFR: a plan asks «what can you say out loud in this situation», and
 * A2 is an answer to a different question. The prompt reads these four words and nothing else.
 *
 * A level moves the DIFFICULTY, never the topic — a `zero` learner going to the doctor still goes
 * to the doctor. Its one mechanical consequence in this module is the day's card order
 * ({@see \App\Modules\Learning\Domain\Service\PlanDayOrder}).
 */
enum PlanLevel: string
{
    /** No target language at all. Single moves: name the thing, answer yes/no. */
    case Zero = 'zero';

    /** Some words, no fluency. One-clause utterances the learner assembles. */
    case Basic = 'basic';

    /** Holds a conversation, loses it under pressure. */
    case Conversational = 'conversational';

    /** Fluent; needs the register and the exact terms. */
    case Fluent = 'fluent';

    /**
     * Does this learner need the substitution WORDS before the replies?
     *
     * Below `conversational` a reply is a wall: the learner cannot assemble «Спина болит уже
     * неделю» out of words they have not met, so the day introduces the pieces first. From
     * `conversational` up the reply is the useful unit and the words inside it are recognised
     * on the way past, so the replies come first and the day reads as a conversation.
     */
    public function wordsBeforeLines(): bool
    {
        return $this === self::Zero || $this === self::Basic;
    }
}

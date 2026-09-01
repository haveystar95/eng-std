<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * How much {@see \App\Modules\Learning\Domain\ValueObject\PlanOutline}'s learner can already
 * produce. Deliberately NOT CEFR: a plan asks «what can you say out loud in this situation», and
 * A2 is an answer to a different question. The prompt reads these four words and nothing else.
 *
 * A level moves the DIFFICULTY, never the topic — a `zero` learner going to the doctor still goes
 * to the doctor. Mechanically it decides how many options a choice card carries and which trainers
 * are open ({@see \App\Modules\Learning\Domain\ValueObject\PlanKnobs}, `learning_mode_settings` at
 * `scope = plan`).
 *
 * It used to decide one more thing, and no longer does: `wordsBeforeLines()` inverted the day's
 * running order from `conversational` up, on the reading that «the reply is the useful unit and the
 * words inside it are recognised on the way past». No card does that recognising, so the inversion
 * described a lesson the machine does not run — see {@see \App\Modules\Learning\Domain\Service\PlanDayOrder}
 * for what replaced it (PLAN-FIX-4, 01.09).
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
}

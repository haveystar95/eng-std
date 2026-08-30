<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * Where a plan is in its life. Five states and only four transitions the learner can make, because
 * a plan is a promise with a date: it can be built, run, put down, finished, or given up on.
 *
 * `draft` is the only state with no obligations attached — nothing is generated, nothing is
 * enrolled, and deleting one costs the learner nothing. Everything from `active` onwards holds
 * words in the pool ({@see \App\Modules\Learning\Domain\Service\EnrollmentPolicy}).
 */
enum PlanStatus: string
{
    /** Created, maybe outlined, not started. No day generated, no term enrolled. */
    case Draft = 'draft';

    /** Running. Days generate, terms enrol, «убрать из изучения» is refused for its words. */
    case Active = 'active';

    /** Paused by the learner. Holds its words; generates nothing. */
    case Paused = 'paused';

    /** The event happened and the plan was seen through. */
    case Completed = 'completed';

    /** Given up on. Its hold on the pool is released; the words stay as ordinary words. */
    case Abandoned = 'abandoned';

    /** Does this state HOLD terms in the pool — i.e. does it refuse «убрать из изучения»? */
    public function holdsTerms(): bool
    {
        return $this === self::Active || $this === self::Paused;
    }

    /** Does this state generate days? Only a running plan does. */
    public function generates(): bool
    {
        return $this === self::Active;
    }

    /** Nothing follows a finished or abandoned plan. */
    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Abandoned;
    }
}

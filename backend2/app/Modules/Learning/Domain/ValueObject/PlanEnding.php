<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * The three ways a plan stops being the plan the learner is on.
 *
 * ## Why this is a type and not three string constants
 *
 * It used to be three `public const` strings on the command, and the handler matched them with a
 * `default` arm that meant «complete». Which means `'abandoned'` — the STATUS, one letter-group
 * away from the ACTION `'abandon'`, and the word anyone reaching for this from a console would
 * type first — compiled, passed PHPStan, passed the constructor, and silently COMPLETED the plan.
 * A plan marked `completed` is the learner's history saying they finished something they walked
 * away from, and nothing anywhere would have said otherwise. That very string went in by hand
 * during the PROMPT-v0.2 live run.
 *
 * As an enum the mistake does not survive the parse, the handler's `match` is exhaustive with no
 * `default` to fall into, and the compiler — not a reviewer — is what keeps the three apart.
 *
 * The values are the ACTIONS, deliberately not the statuses they land in: `abandon` → `abandoned`,
 * `complete` → `completed`, `pause` → `paused` ({@see PlanStatus}). Two vocabularies, because they
 * answer two questions — what the learner did, and where the plan now is.
 */
enum PlanEnding: string
{
    /** Keeps the plan's hold on the pool: «я вернусь». */
    case Pause = 'pause';

    /** Lets the words go, without pretending the plan was finished. */
    case Abandon = 'abandon';

    /** Lets the words go, and says the learner got to the end. */
    case Complete = 'complete';

    /** Does the plan keep its claim on the words it introduced? */
    public function keepsHold(): bool
    {
        return $this === self::Pause;
    }
}

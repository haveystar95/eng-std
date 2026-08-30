<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/**
 * A day's generation state. The reason `failed` exists as a state rather than as a retry loop:
 * a day is one paid model call plus at most one re-run, and a day that failed twice is a thing a
 * person has to look at — silently retrying it forever is how a plan spends money in the dark.
 */
enum PlanDayStatus: string
{
    /** Queued in the abstract: the day exists, nothing has been asked of the model. */
    case Pending = 'pending';

    /** A generation job holds it. Claimed atomically, so two workers cannot both pay for it. */
    case Generating = 'generating';

    /** Material written, collection filled, validator passed. */
    case Ready = 'ready';

    /** Two attempts spent; `fail_reason` says on what. */
    case Failed = 'failed';

    /** The learner has been through it. */
    case Done = 'done';

    public function isTerminal(): bool
    {
        return $this === self::Ready || $this === self::Failed || $this === self::Done;
    }
}

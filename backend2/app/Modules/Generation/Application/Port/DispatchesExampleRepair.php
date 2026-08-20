<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * Queues the echo-example repair for a freshly generated collection, out of band — the same shape,
 * and for the same reason, as {@see DispatchesImageAttachment}: it is one model call per bad example
 * and must never be able to slow down or fail the generation the user is waiting on.
 */
interface DispatchesExampleRepair
{
    /**
     * Repair this collection's examples and, only AFTER that, build the exercise machinery on top of
     * them.
     *
     * One method rather than two calls in a row, because the ORDER is the point and a caller cannot
     * enforce it: both halves are queue work, and two dispatches say nothing about which lands first.
     * They landed in the wrong order (audit A2) — the станок reached an echo term while its example
     * was still missing, had nothing to build distractors against, and marked the term done anyway;
     * the example arrived a minute later to a term that would never be looked at again. Four of the
     * five repaired terms in the store are exactly that, with zero distractors.
     *
     * So the sequencing lives with whoever owns the queue, and the Application layer states the rule
     * it needs: machinery is built on the example the learner will actually see.
     *
     * @param  string  $generatorVersion  the станок version the follow-up enrichment must run at
     */
    public function repairThenEnrich(CollectionId $collectionId, UserId $ownerId, string $generatorVersion): void;
}

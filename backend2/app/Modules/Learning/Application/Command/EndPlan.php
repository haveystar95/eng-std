<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\ValueObject\PlanEnding;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use InvalidArgumentException;

/**
 * Pause, abandon or complete — one command, because the three differ only in the state they land
 * in and in whether the plan's hold on the pool survives.
 *
 * The action is a {@see PlanEnding} and not a string, and that is the whole of what v0.2.1 changed
 * here: `'abandoned'` used to be a compilable way of saying `complete`. The enum's docblock has the
 * story.
 */
final readonly class EndPlan
{
    public function __construct(
        public PlanId $planId,
        public UserId $actorId,
        public PlanEnding $action,
        /**
         * WHY, when the ending was not the learner's own decision — a short machine tag that lands
         * in `learning_plans.abandon_reason` ({@see \App\Modules\Learning\Domain\Entity\LearningPlan}).
         *
         * Null on every ending a person taps, which is nearly all of them: «я передумал» needs no
         * column. It is filled when something else ends a plan on the owner's behalf — a prompt
         * version whose skeletons can no longer be read, a live run that left a plan broken — and
         * without it that plan says «abandoned» and nothing else to the person who opens it a month
         * later.
         */
        public ?string $reason = null,
    ) {
        // A reason on a pause or a completion would be stored nowhere and silently lost, and the
        // caller who wrote it believed otherwise. Only abandoning has a column for it.
        if ($reason !== null && $action !== PlanEnding::Abandon) {
            throw new InvalidArgumentException(
                'Причина есть только у отказа: ' . $action->value . ' её не хранит.',
            );
        }
    }
}

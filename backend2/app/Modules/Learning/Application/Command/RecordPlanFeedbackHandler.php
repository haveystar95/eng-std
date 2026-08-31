<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\PlanTermReleaser;
use App\Modules\Learning\Domain\Exception\PlanNotFound;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * THE LAST THING A PLAN DOES — record what the learner actually said at the event, and close.
 *
 * The evening notification asks «как прошло?», the learner ticks the abilities that came up, and
 * that answer is the only thing in the whole plan that could not have been derived: it happened in
 * a room the app was not in. What it produces is the finished plan's headline — «на приёме сказал
 * 5 из 6» (кадр 1c · 11).
 *
 * Closing here rather than in a second call is deliberate. A plan whose event has happened and
 * which is still `active` goes on holding its words out of the ordinary day
 * ({@see \App\Modules\Learning\Infrastructure\Eloquent\PlanHeldTerms}) — for an appointment that is
 * over. Completing releases them, which is «18 слов ушли в общее повторение» said as code.
 *
 * Idempotent: the second tap on the same notification, or an offline retry, replaces the report and
 * leaves the already-completed plan alone. The answer is a fact about the event, not a log of the
 * attempts to state it.
 */
final readonly class RecordPlanFeedbackHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanTermReleaser $releaser,
        private TransactionManager $tx,
        private Clock $clock,
    ) {}

    public function __invoke(RecordPlanFeedback $command): void
    {
        $this->tx->run(function () use ($command): void {
            $plan = $this->plans->findForUpdate($command->planId);
            if ($plan === null || ! $plan->userId()->equals($command->actorId)) {
                throw PlanNotFound::withId($command->planId->value);
            }

            $wasHolding = $plan->status()->holdsTerms();
            $plan->recordEventFeedback($command->checkpointIndexes, $this->clock->now());
            $this->plans->save($plan);

            // Only when the plan actually LET GO in this call. A paused plan that never became
            // active is still holding on purpose, and re-releasing an already-released plan would be
            // a write for nothing.
            if ($wasHolding && $plan->status() === PlanStatus::Completed) {
                // FIRST, and it reads the marker the release is about to remove: whatever this plan
                // put in the pool and the learner never once answered leaves with it. A plan
                // abandoned on day one otherwise leaves fourteen words from a conversation that
                // never happened, and they come back due for ever.
                $this->releaser->unenrolUntouched($plan->userId(), $plan->id()->value);
                $this->releaser->releasePlan($plan->userId(), $plan->id()->value);
            }
        });
    }
}

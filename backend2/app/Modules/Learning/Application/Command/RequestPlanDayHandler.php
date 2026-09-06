<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\DispatchesPlanDay;
use App\Modules\Learning\Application\Service\PlanProgress;
use App\Modules\Learning\Domain\Exception\PlanDayCapped;
use App\Modules\Learning\Domain\Exception\PlanNotFound;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Service\PlanGenerationPolicy;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;
use App\Modules\Learning\Domain\ValueObject\PlanId;

/**
 * BUILD A DAY THE LEARNER ASKED FOR — idempotent, capped, and cheap to call twice.
 *
 * «Idempotent» is not a nicety here: the screen that calls this is «собираю день n», and a learner
 * who taps it twice, or a client that retries a timed-out request, must not buy two days. The real
 * guard is one layer down — {@see ClaimPlanDayHandler} claims the day inside a locked read and
 * returns nothing to the second worker — so this handler's job is to be a POLITE front door: it
 * returns the day's status rather than throwing on «already generating» or «already written».
 *
 * What it does refuse is running ahead of the learner ({@see PlanGenerationPolicy}). Two days
 * already written ahead of the focus, or one already generating, and the answer is a 409 with a
 * sentence — not a silent no-op, because the screen has to say why the button did nothing.
 */
final readonly class RequestPlanDayHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
        private PlanProgress $progress,
        private DispatchesPlanDay $dispatcher,
        private \App\Modules\Learning\Application\Service\PlanDayStaleSweeper $stale,
    ) {}

    /** @return string the day's status after the call — `generating`, `ready` or `done` */
    public function __invoke(RequestPlanDay $command): string
    {
        $plan = $this->plans->findById(PlanId::fromString($command->planId));
        if ($plan === null || ! $plan->userId()->equals($command->actorId)) {
            throw PlanNotFound::withId($command->planId);
        }

        // A day whose worker died is taken back HERE, on the poll that would otherwise show
        // «собирается» for ever ({@see \App\Modules\Learning\Application\Service\PlanDayStaleSweeper}).
        $days = $this->stale->sweep($command->planId, $this->days->listForPlan($plan->id()));
        $day = null;
        foreach ($days as $candidate) {
            if ($candidate->dayIndex() === $command->dayIndex) {
                $day = $candidate;
            }
        }

        if ($day === null || $day->kind() === PlanDayKind::Final) {
            // The final day introduces nothing and owns no collection: there is no material to
            // build, so asking for it is a 404 rather than a refusal.
            throw PlanNotFound::withId($command->planId);
        }

        // Already written, already being written, or out of attempts: the answer is the state, not
        // an error. A client that polls this endpoint gets the truth on every call.
        if ($day->status() !== PlanDayStatus::Pending && $day->status() !== PlanDayStatus::Failed) {
            return $day->status()->value;
        }

        $focus = $this->progress->forPlan($plan, $days)->focusDayIndex;
        if ($command->dayIndex > $focus && ! PlanGenerationPolicy::hasRoomAhead($days, $focus)) {
            throw PlanDayCapped::make($command->planId, $command->dayIndex, PlanGenerationPolicy::MAX_READY_AHEAD);
        }

        $this->dispatcher->dispatchDay($command->planId, $command->dayIndex);

        // The queue may be `sync`, in which case the day is already written by the time we get
        // here. Re-read rather than assume: «собираю день n» on a day that is already finished is
        // a spinner nobody can dismiss.
        $after = $this->days->findByIndex($plan->id(), $command->dayIndex);

        return $after?->status()->value ?? PlanDayStatus::Generating->value;
    }
}

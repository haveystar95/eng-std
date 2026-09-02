<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\DispatchesPlanDay;
use App\Modules\Learning\Domain\Exception\PlanNotFound;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * GIVE ONE BURNED DAY ONE MORE ATTEMPT, and queue it. {@see RebuildPlanDay} for why it exists.
 *
 * Deliberately NOT folded into {@see RequestPlanDayHandler}. That handler is the polite front door
 * the screen polls — it is called on a timer, it is called on every open of a day, and it answers
 * with a status rather than an error precisely so that calling it twice is free. Reopening a spent
 * day from inside it would mean a poll could buy a model call, which is the cap gone. This is a
 * separate verb because it is a separate act: the learner pressed a button that says «собрать
 * заново», and it spends money.
 *
 * Idempotent in the direction that matters: a day that is not `failed` is not reopened, it is
 * answered with what it already is. Two taps therefore cost one rebuild — the second finds the day
 * `generating` and says so.
 */
final readonly class RebuildPlanDayHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
        private DispatchesPlanDay $dispatcher,
        private TransactionManager $tx,
    ) {}

    /** @return string the day's status after the call — `generating`, or whatever it already was */
    public function __invoke(RebuildPlanDay $command): string
    {
        $planId = PlanId::fromString($command->planId);
        $plan = $this->plans->findById($planId);
        if ($plan === null || ! $plan->userId()->equals($command->actorId)) {
            throw PlanNotFound::withId($command->planId);
        }

        // Locked, and re-read inside the lock: two taps a second apart are two requests, and the
        // attempt this returns must be given out once.
        $reopened = $this->tx->run(function () use ($planId, $command): ?PlanDayStatus {
            $day = $this->days->findByIndexForUpdate($planId, $command->dayIndex);
            if ($day === null || $day->kind() === PlanDayKind::Final) {
                // The final day writes no material and owns no collection — there is nothing to
                // rebuild, which is a 404 rather than a refusal.
                throw PlanNotFound::withId($command->planId);
            }

            if ($day->status() !== PlanDayStatus::Failed) {
                return $day->status();
            }

            $day->reopenForRetry();
            $this->days->save($day);

            return null;
        });

        if ($reopened !== null) {
            return $reopened->value;
        }

        $this->dispatcher->dispatchDay($command->planId, $command->dayIndex);

        // The queue may be `sync`, in which case the day is already written by the time we get
        // here — the same re-read {@see RequestPlanDayHandler} makes, and for the same reason.
        return $this->days->findByIndex($planId, $command->dayIndex)?->status()->value
            ?? PlanDayStatus::Generating->value;
    }
}

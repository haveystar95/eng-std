<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Service\Paywalls;
use App\Modules\Plan\Application\Service\PlanPaces;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;
use DateTimeImmutable;

/**
 * Creates the plan row with its calendar and queues the model call. The HTTP request never
 * waits on the model; the client polls the build.
 *
 * While the paywall is on (наряд ACC-1 §2) the learner may be refused first — a plan beyond the free one without a
 * subscription is 402 `plan_subscription_required`, a subscriber's fourth plan in work is 409 `plan_active_limit` —
 * asked in the transaction that writes the plan, the learner's plans held still, so two quick taps are asked one after
 * the other. The model is asked only once the plan is written.
 */
final readonly class CreatePlanHandler
{
    public function __construct(
        private PlanRepository $plans,
        private LearnerCalendar $calendar,
        private PlanDispatcher $dispatcher,
        private Clock $clock,
        private PlanPaces $paces,
        private Paywalls $paywalls,
        private TransactionManager $tx,
    ) {}

    public function __invoke(CreatePlan $command): PlanId
    {
        $now = $this->clock->now();
        $plan = $this->tx->run(function () use ($command, $now): Plan {
            $this->paywalls->assertMayCreate($command->actorId);
            $plan = $this->plan($command, $now);
            $this->plans->save($plan);

            return $plan;
        });
        $this->dispatcher->buildPlan($plan->id());

        return $plan->id();
    }

    private function plan(CreatePlan $command, DateTimeImmutable $now): Plan
    {
        return Plan::create(
            id: PlanId::generate(),
            userId: $command->actorId,
            goalText: $command->goalText,
            targetLang: $command->targetLang,
            nativeLang: $this->calendar->nativeLangFor($command->actorId),
            level: $command->level,
            daysRequested: $command->daysTotal,
            eventDate: $command->eventDate,
            today: $this->calendar->todayFor($command->actorId, $now),
            now: $now,
            dayIds: static fn (): PlanDayId => PlanDayId::generate(),
            // The price list of its days as it stands today (наряд FIX-3 §2).
            pace: $this->paces->current(),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Service\DayDealer;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\DayMetrics;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * Opens day N: the aggregate decides whether it may open, the dealer writes its cards once, and
 * the lesson of the NEXT scene day is queued the moment this one opens (`docs/plan-v2.md` §4).
 */
final readonly class OpenDayHandler
{
    public function __construct(
        private PlanAccess $access,
        private PlanRepository $plans,
        private DayCardRepository $cards,
        private DayDealer $dealer,
        private LearnerCalendar $calendar,
        private PlanDispatcher $dispatcher,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function __invoke(OpenDay $command): PlanDayId
    {
        $now = $this->clock->now();
        $today = $this->calendar->todayFor($command->actorId, $now);

        [$dayId, $nextSceneId] = $this->tx->run(function () use ($command, $today, $now): array {
            $plan = $this->access->ownedForUpdate($command->planId, $command->actorId);
            $day = $plan->openDay($command->number, $today, $now);

            if ($this->cards->countForDay($day->id()) === 0) {
                $cards = $this->dealer->deal($plan, $day);
                $this->cards->insertAll($cards);
                $day->updateMetrics(new DayMetrics(count($cards), 0, 0));
            }
            $this->plans->save($plan);

            $next = $plan->nextSceneDayAfter($day->number());
            $scene = $next === null ? null : $plan->sceneOf($next);

            return [$day->id(), $scene !== null && $scene->needsLesson() ? $scene->id() : null];
        });

        if ($nextSceneId !== null) {
            $this->dispatcher->buildLesson($nextSceneId);
        }

        return $dayId;
    }
}

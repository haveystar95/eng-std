<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Port\PlanCollectionWriter;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Application\Service\UnitNames;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Exception\PlanDayNotOpen;
use App\Modules\Plan\Domain\Exception\StageIncomplete;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\Service\DayMetricsCalculator;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

final readonly class CloseDayHandler
{
    public function __construct(
        private PlanAccess $access,
        private PlanRepository $plans,
        private DayCardRepository $cards,
        private PlanTermRepository $terms,
        private PlanCollectionWriter $collection,
        private DayMetricsCalculator $metrics,
        private LearnerCalendar $calendar,
        private PlanDispatcher $dispatcher,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function __invoke(CloseDay $command): void
    {
        $now = $this->clock->now();
        $today = $this->calendar->todayFor($command->actorId, $now);

        $nextSceneId = $this->tx->run(function () use ($command, $today, $now): ?\App\Modules\Plan\Domain\ValueObject\PlanSceneId {
            $plan = $this->access->ownedForUpdate($command->planId, $command->actorId);
            $day = $plan->day($command->number);
            if ($day->status() !== DayStatus::InProgress) {
                throw PlanDayNotOpen::day($command->number, $day->status());
            }

            $cards = $this->cards->forDay($day->id());
            foreach ($cards as $card) {
                if (! $card->isAnswered()) {
                    throw StageIncomplete::stage($card->stage(), count(array_filter(
                        $cards,
                        static fn (DayCard $c): bool => $c->stage() === $card->stage() && ! $c->isAnswered(),
                    )));
                }
            }

            $metrics = $this->metrics->calculate($cards, UnitNames::of(...));
            $next = $plan->closeDay($command->number, $metrics, $today, $now);

            // The day's words and phrases go to the plan's collection — the ordinary mechanism.
            $scene = $day->type() === DayType::Scene ? $plan->sceneOf($day) : null;
            if ($scene !== null) {
                $collectionId = $this->collection->ensureCollection($plan);
                $plan->attachCollection($collectionId);
                $this->collection->addTerms(
                    $plan, $collectionId, $this->terms->forScene($scene->id()),
                    $scene->lessonCall()->promptVersion ?? '',
                );
            }
            $this->plans->save($plan);

            $nextScene = $next === null ? null : $plan->sceneOf($next);

            return $nextScene !== null && $nextScene->needsLesson() ? $nextScene->id() : null;
        });

        if ($nextSceneId !== null) {
            $this->dispatcher->buildLesson($nextSceneId);
        }
    }
}

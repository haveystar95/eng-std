<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Service\ConversationMaterial;
use App\Modules\Plan\Application\Service\DayDealer;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\DayMetrics;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * Opens day N: the aggregate decides whether it may open (the day before it closed, its lesson written, its calendar day
 * come or the plan catching up), and the dealer writes its cards once. Nothing is asked of the model here: the next day's
 * lesson is asked for when this day CLOSES ({@see CloseDayHandler}, наряд GEN-3 §11) — and the talk of the sixth stage
 * is asked for when the learner starts it, not before.
 */
final readonly class OpenDayHandler
{
    public function __construct(
        private PlanAccess $access,
        private PlanRepository $plans,
        private DayCardRepository $cards,
        private DayDealer $dealer,
        private ConversationMaterial $material,
        private LearnerCalendar $calendar,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function __invoke(OpenDay $command): PlanDayId
    {
        $now = $this->clock->now();
        $today = $this->calendar->todayFor($command->actorId, $now);

        return $this->tx->run(function () use ($command, $today, $now): PlanDayId {
            $plan = $this->access->ownedForUpdate($command->planId, $command->actorId);
            $day = $plan->openDay($command->number, $today, $now);

            if ($this->cards->countForDay($day->id()) === 0) {
                $cards = $this->dealer->deal($plan, $day);
                $this->cards->insertAll($cards);
                $day->updateMetrics(new DayMetrics(count($cards), 0, 0));
                // THE COMPOSITION IS FIXED HERE, and since наряд CONV-1 it includes the sixth stage.
                // Only when there IS something to talk about: a day whose scenes have no written
                // lesson gets no talk, and therefore is not held shut waiting for one.
                if ($this->material->for($plan, $day)->checkpoints !== []) {
                    $day->dealWithConversation();
                }
            }
            $this->plans->save($plan);

            return $day->id();
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Exception\EventDateInPast;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use DateTimeImmutable;

/**
 * A draft costs nothing: no model call, no day, no enrolment. That is what makes it safe to create
 * one from a half-typed goal and let the learner change their mind.
 *
 * The event date IS checked here, before anything is stored — the whole plan is arithmetic over
 * that date, and a draft built on a date in the past is a draft that can never be outlined.
 */
final readonly class CreatePlanHandler
{
    public function __construct(
        private PlanRepository $plans,
        private \App\Modules\Shared\Domain\Service\Clock $clock,
    ) {}

    /** @throws EventDateInPast */
    public function __invoke(CreatePlan $command): PlanId
    {
        $eventDate = new DateTimeImmutable($command->eventDate . ' 00:00:00');
        $today = $this->clock->now()->setTime(0, 0, 0);
        if ($eventDate < $today) {
            throw EventDateInPast::make($eventDate->format('Y-m-d'), $today->format('Y-m-d'));
        }

        $plan = LearningPlan::draft(
            id: PlanId::generate(),
            userId: $command->actorId,
            // A placeholder title until P1 names the plan. The learner's own words rather than
            // «Новый план»: a draft list of three «Новый план» rows is useless.
            title: mb_substr(trim($command->goalText), 0, 120),
            goalText: trim($command->goalText),
            targetLang: new LanguageCode($command->targetLang),
            level: PlanLevel::from($command->level),
            eventDate: $eventDate,
            minutesPerDay: $command->minutesPerDay,
        );

        $this->plans->save($plan);

        return $plan->id();
    }
}

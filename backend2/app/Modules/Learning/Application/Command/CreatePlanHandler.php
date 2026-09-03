<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Exception\EventDateInPast;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\ValueObject\ListeningDiagnostics;
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
        private LearnerProfileReader $profiles,
        private \App\Modules\Shared\Domain\Service\Clock $clock,
    ) {}

    /** @throws EventDateInPast */
    public function __invoke(CreatePlan $command): PlanId
    {
        // «Без даты» skips the check rather than passing it: there is no date to be in the past,
        // and a plan without one is laid out by {@see PlanScheduler::undated()} instead.
        $eventDate = null;
        if ($command->eventDate !== null) {
            $eventDate = new DateTimeImmutable($command->eventDate . ' 00:00:00');
            $today = $this->clock->now()->setTime(0, 0, 0);
            if ($eventDate < $today) {
                throw EventDateInPast::make($eventDate->format('Y-m-d'), $today->format('Y-m-d'));
            }
        }

        $plan = LearningPlan::draft(
            id: PlanId::generate(),
            userId: $command->actorId,
            // A placeholder title until P1 names the plan. The learner's own words rather than
            // «Новый план»: a draft list of three «Новый план» rows is useless.
            title: mb_substr(trim($command->goalText), 0, 120),
            goalText: trim($command->goalText),
            targetLang: new LanguageCode($command->targetLang),
            // Read from the account ONCE, here, and carried by the plan from now on. It stays
            // fluid while the plan is a draft (the outline is written in it) and is frozen the
            // moment the plan starts.
            supportLang: new LanguageCode($this->profiles->nativeLangFor($command->actorId)),
            level: PlanLevel::from($command->level),
            eventDate: $eventDate,
            minutesPerDay: $command->minutesPerDay,
            // The listening step's answers, turned into the record the two prompts read. NULL when
            // the step was skipped — and «пропущен» is deliberately not the same value as «прошёл
            // и ничего не понял», which is a real diagnostics with three `understood: false` rows.
            diagnostics: ListeningDiagnostics::fromLines($command->listened),
        );

        $this->plans->save($plan);

        return $plan->id();
    }
}

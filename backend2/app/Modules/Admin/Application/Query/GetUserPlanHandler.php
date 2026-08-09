<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Admin\Application\Dto\AdminDayPlanEntry;
use App\Modules\Admin\Application\Dto\AdminDayPlanView;
use App\Modules\Learning\Application\Dto\DayPlanEntryView;
use App\Modules\Learning\Application\Query\GetDayPlan;
use App\Modules\Learning\Application\Query\GetDayPlanHandler;

/**
 * The admin day-simulator: delegates to Learning's dry-run planner (the real GetDueTerms +
 * ExerciseSelector, no writes) and maps its view into an admin-owned DTO so the boundary holds.
 */
final readonly class GetUserPlanHandler
{
    public function __construct(private GetDayPlanHandler $plan) {}

    public function __invoke(GetUserPlan $query): AdminDayPlanView
    {
        $view = ($this->plan)(new GetDayPlan(
            userId: $query->userId,
            date: $query->date,
        ));

        $entries = array_map(static fn (DayPlanEntryView $e): AdminDayPlanEntry => new AdminDayPlanEntry(
            termId: $e->termId,
            text: $e->text,
            translation: $e->translation,
            type: $e->type,
            state: $e->state,
            reps: $e->reps,
            intervalDays: $e->intervalDays,
            dueAt: $e->dueAt,
            exerciseMode: $e->exerciseMode,
            clozeable: $e->clozeable,
            isNew: $e->isNew,
        ), $view->entries);

        return new AdminDayPlanView(
            date: $view->date,
            timezone: $view->timezone,
            dueCount: $view->dueCount,
            newIntroduced: $view->newIntroduced,
            newTermsPerDay: $view->newTermsPerDay,
            entries: $entries,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Resource;

use App\Modules\Learning\Application\Dto\PlanDayView;
use App\Modules\Learning\Application\Dto\PlanView;

/**
 * A plan on the wire.
 *
 * The whole structure, days included, in one payload — the screens land in 1c and a shape trimmed
 * against imagined ones is how an API needs a v2 the week the real screens arrive.
 */
final class PlanResource
{
    /** @return array<string, mixed> */
    public static function toArray(PlanView $plan): array
    {
        return [
            'id' => $plan->id,
            'status' => $plan->status,
            'title' => $plan->title,
            'goal_text' => $plan->goalText,
            'goal_restated' => $plan->goalRestated,
            'support_lang' => $plan->supportLang,
            'target_lang' => $plan->targetLang,
            'level' => $plan->level,
            'event_date' => $plan->eventDate,
            'minutes_per_day' => $plan->minutesPerDay,
            'started_at' => $plan->startedAt,
            'completed_at' => $plan->completedAt,
            'readiness' => $plan->readiness,
            'recommended_days' => $plan->recommendedDays,
            'entities' => $plan->entities,
            'constraints' => $plan->constraints,
            'goal_terms' => $plan->goalTerms,
            // The server's own arithmetic, verbatim — including `fits` and `dropped_skills`, which
            // is what the «срок мал» card is built from. Nothing is cut silently, so nothing is
            // hidden here either.
            'computed' => $plan->computed,
            'days' => array_map(static fn (PlanDayView $d): array => self::day($d), $plan->days),
        ];
    }

    /** @return array<string, mixed> */
    public static function day(PlanDayView $day): array
    {
        return [
            'id' => $day->id,
            'index' => $day->index,
            'kind' => $day->kind,
            'title' => $day->title,
            'scheduled_on' => $day->scheduledOn,
            'collection_id' => $day->collectionId,
            'status' => $day->status,
            'generation_attempts' => $day->generationAttempts,
            'fail_reason' => $day->failReason,
            'term_budget' => $day->termBudget,
            'outcome' => $day->outcomes,
            'checkpoints' => $day->checkpoints,
            'topics' => $day->topics,
            'role' => $day->role,
        ];
    }
}

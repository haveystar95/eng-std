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
            // Where the learner IS, and what the plan owes them next. Derived on every read from
            // the review log, so it cannot disagree with the session the client is about to build.
            'focus_day_index' => $plan->focusDayIndex,
            'next_day_index' => $plan->nextDayIndex,
            'days_to_event' => $plan->daysToEvent,
            // A7. Nothing is cut on the strength of it — the «срок мал» card is, and the decision
            // is the learner's.
            'deadline_tight' => $plan->deadlineTight,
            // «Ты уже можешь: … ✓ / — ещё нет». Every `hit` is false until CONV-1 writes the first
            // conversation; the SHAPE ships now because the screen is built against it.
            'can_already' => $plan->canAlready,
            // The plain count beside the percentage — see PlanView::$stageCensus. On the wire as a
            // whole object so «сколько всего» and «сколько закрыло A» can never be read apart.
            'stage_census' => $plan->stageCensus,
            // Which of those the learner ticked after the event (кадр 1c · 14). Null = never asked,
            // `[]` = asked and none used — the finished plan says a different sentence for each.
            'event_feedback' => $plan->eventFeedback,
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
            'fail_code' => $day->failCode,
            'term_budget' => $day->termBudget,
            'outcome' => $day->outcomes,
            'checkpoints' => $day->checkpoints,
            'topics' => $day->topics,
            'role' => $day->role,
        ];
    }
}

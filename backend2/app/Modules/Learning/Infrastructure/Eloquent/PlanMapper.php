<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\ValueObject\PlanDayId;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\PlanLevel;
use App\Modules\Learning\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/** Row ↔ entity, both ways, for the two plan tables. */
final class PlanMapper
{
    public function toPlan(PlanModel $row): LearningPlan
    {
        return LearningPlan::reconstitute(
            id: PlanId::fromString($row->id),
            userId: UserId::fromString($row->user_id),
            status: PlanStatus::from($row->status),
            title: $row->title,
            goalText: $row->goal_text,
            goalRestated: $row->goal_restated,
            targetLang: new LanguageCode($row->target_lang),
            supportLang: new LanguageCode($row->support_lang),
            level: PlanLevel::from($row->level),
            eventDate: new DateTimeImmutable($row->event_date . ' 00:00:00'),
            minutesPerDay: $row->minutes_per_day,
            outline: $row->outline,
            computed: $row->computed,
            startedAt: $row->started_at?->toDateTimeImmutable(),
            completedAt: $row->completed_at?->toDateTimeImmutable(),
            eventFeedback: self::indexes($row->event_feedback),
            abandonReason: $row->abandon_reason,
        );
    }

    /**
     * The stored `event_feedback` as the entity wants it — a list of ints, or null.
     *
     * Filtered rather than trusted: it is a jsonb column, and a column is not a type. Null stays
     * null, because «never asked» and «asked, nothing used» are two different facts.
     *
     * @param  array<mixed>|null  $raw
     * @return list<int>|null
     */
    private static function indexes(?array $raw): ?array
    {
        if ($raw === null) {
            return null;
        }

        return array_values(array_map(intval(...), array_filter($raw, is_numeric(...))));
    }

    /** @return array<string, mixed> */
    public function planColumns(LearningPlan $plan): array
    {
        return [
            'user_id' => $plan->userId()->value,
            'status' => $plan->status()->value,
            'title' => $plan->title(),
            'goal_text' => $plan->goalText(),
            'goal_restated' => $plan->goalRestated(),
            'target_lang' => $plan->targetLang()->value,
            'support_lang' => $plan->supportLang()->value,
            'level' => $plan->level()->value,
            'event_date' => $plan->eventDate()->format('Y-m-d'),
            'minutes_per_day' => $plan->minutesPerDay(),
            'outline' => $plan->outline(),
            'computed' => $plan->computed(),
            'started_at' => $plan->startedAt(),
            'completed_at' => $plan->completedAt(),
            'event_feedback' => $plan->eventFeedback(),
            'abandon_reason' => $plan->abandonReason(),
        ];
    }

    public function toDay(PlanDayModel $row): PlanDay
    {
        /** @var list<array<string, mixed>> $skills */
        $skills = is_array($row->skills) ? array_values(array_filter($row->skills, 'is_array')) : [];

        return PlanDay::reconstitute(
            id: PlanDayId::fromString($row->id),
            planId: PlanId::fromString($row->plan_id),
            dayIndex: $row->day_index,
            kind: PlanDayKind::from($row->kind),
            collectionId: $row->collection_id !== null ? CollectionId::fromString($row->collection_id) : null,
            title: $row->title,
            outcomeText: $row->outcome_text,
            skills: $skills,
            roleBrief: $row->role_brief,
            scheduledOn: $row->scheduled_on !== null ? new DateTimeImmutable($row->scheduled_on . ' 00:00:00') : null,
            status: PlanDayStatus::from($row->status),
            generationAttempts: $row->generation_attempts,
            failReason: $row->fail_reason,
            lastViolations: is_array($row->generation_violations)
                ? array_values(array_filter($row->generation_violations, 'is_string'))
                : [],
        );
    }

    /** @return array<string, mixed> */
    public function dayColumns(PlanDay $day): array
    {
        return [
            'plan_id' => $day->planId()->value,
            'day_index' => $day->dayIndex(),
            'kind' => $day->kind()->value,
            'collection_id' => $day->collectionId()?->value,
            'title' => $day->title(),
            'outcome_text' => $day->outcomeText(),
            'skills' => $day->skills(),
            'role_brief' => $day->roleBrief(),
            'scheduled_on' => $day->scheduledOn()?->format('Y-m-d'),
            'status' => $day->status()->value,
            'generation_attempts' => $day->generationAttempts(),
            'fail_reason' => $day->failReason(),
            // Null and not `[]` when there is nothing: «this day has never been refused» and «this
            // day was refused for no reasons» should not look the same in the table.
            'generation_violations' => $day->lastViolations() === [] ? null : $day->lastViolations(),
        ];
    }
}

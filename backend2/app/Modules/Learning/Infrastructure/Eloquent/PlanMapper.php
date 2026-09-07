<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\ValueObject\ListeningDiagnostics;
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
            eventDate: $row->event_date !== null ? new DateTimeImmutable($row->event_date . ' 00:00:00') : null,
            minutesPerDay: $row->minutes_per_day,
            outline: $row->outline,
            computed: $row->computed,
            startedAt: $row->started_at?->toDateTimeImmutable(),
            completedAt: $row->completed_at?->toDateTimeImmutable(),
            eventFeedback: self::indexes($row->event_feedback),
            abandonReason: $row->abandon_reason,
            diagnostics: ListeningDiagnostics::fromArray($row->listening_diagnostics),
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
            'event_date' => $plan->eventDate()?->format('Y-m-d'),
            'minutes_per_day' => $plan->minutesPerDay(),
            'outline' => $plan->outline(),
            'computed' => $plan->computed(),
            'started_at' => $plan->startedAt(),
            'completed_at' => $plan->completedAt(),
            'event_feedback' => $plan->eventFeedback(),
            'abandon_reason' => $plan->abandonReason(),
            // ENCODED HERE, unlike `outline` and `event_feedback`, and the difference is the INSERT.
            // The repository writes through `updateOrInsert`, which bypasses the model's casts;
            // Laravel's grammar json-encodes array bindings on an UPDATE and not on an INSERT, and
            // those two columns only ever arrive on an update (a draft is inserted with both null).
            // This one arrives with the row itself — the listening step happens before «Собрать
            // план» — so it has to be a string by the time it gets here.
            'listening_diagnostics' => $plan->diagnostics() === null
                ? null
                : json_encode($plan->diagnostics()->toArray(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
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
            repairCalls: $row->repair_calls,
            failCode: $row->fail_code,
            dialogue: self::dialogueOf($row),
            claimedAt: $row->claimed_at !== null ? new DateTimeImmutable((string) $row->claimed_at) : null,
        );
    }

    /**
     * The stored chain, kept only if every turn is still a turn — `{turn, term_id}` and both
     * strings.
     *
     * A row that half-decodes is treated as no chain at all rather than as a shorter one: the
     * fallback ({@see \App\Modules\Learning\Domain\Service\PlanDialogueChain}) builds a whole
     * conversation out of the shelves, and half a stored conversation is worse than a derived one.
     *
     * @return list<array{turn: string, term_id: string, pair?: string|null}>|null
     */
    private static function dialogueOf(PlanDayModel $row): ?array
    {
        if (! is_array($row->dialogue) || $row->dialogue === []) {
            return null;
        }

        $out = [];
        foreach ($row->dialogue as $turn) {
            if (! is_string($turn['turn'] ?? null) || ! is_string($turn['term_id'] ?? null)) {
                return null;
            }
            // THE PAIR'S TYPE RIDES WITH THE TURN (v0.6) — and it was being dropped right here on
            // every read, so `turns[].pair` was null on the wire for every day ever written
            // (found by наряд DAY-FIX-3, Ч.2.2: the function of a role line — invitation or
            // question — is what the такт-1 options are chosen by). Null on a chain a v0.5 answer wrote.
            $out[] = [
                'turn' => $turn['turn'],
                'term_id' => $turn['term_id'],
                'pair' => is_string($turn['pair'] ?? null) ? $turn['pair'] : null,
            ];
        }

        return $out;
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
            // Null and not `[]` for the same reason `generation_violations` is: «no chain» and «an
            // empty chain» are different days, and only the first one has an answer.
            'dialogue' => $day->dialogue(),
            'scheduled_on' => $day->scheduledOn()?->format('Y-m-d'),
            'status' => $day->status()->value,
            'generation_attempts' => $day->generationAttempts(),
            'claimed_at' => $day->claimedAt()?->format('Y-m-d H:i:sP'),
            'repair_calls' => $day->repairCalls(),
            'fail_code' => $day->failCode(),
            'fail_reason' => $day->failReason(),
            // Null and not `[]` when there is nothing: «this day has never been refused» and «this
            // day was refused for no reasons» should not look the same in the table.
            'generation_violations' => $day->lastViolations() === [] ? null : $day->lastViolations(),
        ];
    }
}

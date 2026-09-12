<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Domain\Entity\PlanEvent;
use App\Modules\Plan\Domain\Repository\PlanEventRepository;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanEventId;
use App\Modules\Plan\Domain\ValueObject\PlanEventKind;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/** `plan_events` — INSERT … ON CONFLICT DO NOTHING and SELECTs. There is no UPDATE or DELETE in this class. */
final class EloquentPlanEventRepository implements PlanEventRepository
{
    public function append(PlanEvent $event): bool
    {
        return DB::table('plan_events')->insertOrIgnore([
            'id' => $event->id->value,
            'user_id' => $event->userId->value,
            'plan_id' => $event->planId->value,
            'day_id' => $event->dayId?->value,
            'day_number' => $event->dayNumber,
            'kind' => $event->kind->value,
            'payload' => json_encode((object) $event->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'occurred_at' => $event->occurredAt->format(DATE_ATOM),
        ]) === 1;
    }

    public function has(PlanId $planId, PlanEventKind $kind): bool
    {
        return DB::table('plan_events')->where('plan_id', $planId->value)->where('kind', $kind->value)->exists();
    }

    public function forPlan(PlanId $planId): array
    {
        return array_values(DB::table('plan_events')
            ->where('plan_id', $planId->value)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(static function (object $row): PlanEvent {
                /** @var array<string, int|string|null> $payload */
                $payload = (array) json_decode((string) $row->payload, true, flags: JSON_THROW_ON_ERROR);

                return PlanEvent::reconstitute(
                    PlanEventId::fromString((string) $row->id),
                    UserId::fromString((string) $row->user_id),
                    PlanId::fromString((string) $row->plan_id),
                    $row->day_id === null ? null : PlanDayId::fromString((string) $row->day_id),
                    $row->day_number === null ? null : (int) $row->day_number,
                    PlanEventKind::from((string) $row->kind),
                    $payload,
                    new DateTimeImmutable((string) $row->occurred_at),
                );
            })
            ->all());
    }
}

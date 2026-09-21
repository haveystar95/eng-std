<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Domain\Repository\StagePassageRepository;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\StagePassage;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * `plan_stage_passages` (наряд CONV-2, п. 2) — INSERT … ON CONFLICT DO NOTHING and SELECTs. There is no UPDATE or DELETE
 * in this class: a walked stage stays walked, and the first write of a day's stage is the one that holds.
 */
final class EloquentStagePassageRepository implements StagePassageRepository
{
    public function record(StagePassage $passage): bool
    {
        return DB::table('plan_stage_passages')->insertOrIgnore([
            'id' => Ulid::generate(),
            'plan_id' => $passage->planId->value,
            'day_id' => $passage->dayId->value,
            'stage' => $passage->stage->value,
            'conversation_id' => $passage->conversationId?->value,
            'passed_at' => $passage->passedAt->format(DATE_ATOM),
            'created_at' => now(),
        ]) === 1;
    }

    public function of(PlanDayId $dayId, Stage $stage): ?StagePassage
    {
        $row = DB::table('plan_stage_passages')->where('day_id', $dayId->value)->where('stage', $stage->value)->first();

        return $row === null ? null : self::toDomain($row);
    }

    public function ofDays(array $dayIds, Stage $stage): array
    {
        if ($dayIds === []) {
            return [];
        }
        $rows = DB::table('plan_stage_passages')
            ->whereIn('day_id', array_map(static fn (PlanDayId $id): string => $id->value, $dayIds))
            ->where('stage', $stage->value)
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->day_id] = self::toDomain($row);
        }

        return $out;
    }

    private static function toDomain(object $row): StagePassage
    {
        /** @var object{plan_id: string, day_id: string, stage: string, conversation_id: string|null, passed_at: string} $row */
        return new StagePassage(
            planId: PlanId::fromString($row->plan_id),
            dayId: PlanDayId::fromString($row->day_id),
            stage: Stage::from($row->stage),
            conversationId: $row->conversation_id === null ? null : ConversationId::fromString($row->conversation_id),
            passedAt: new DateTimeImmutable($row->passed_at),
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Plan\Infrastructure\Eloquent;

use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\CardSource;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentDayCardRepository implements DayCardRepository
{
    /** The stage order in SQL, so one query returns the cards in walking order. */
    private const STAGE_ORDER = "CASE stage WHEN 'words' THEN 0 WHEN 'phrases' THEN 1 WHEN 'dialogue' THEN 2 WHEN 'listen' THEN 3 ELSE 4 END";

    public function forDay(PlanDayId $dayId): array
    {
        $rows = DayCardModel::query()
            ->where('day_id', $dayId->value)
            ->orderByRaw(self::STAGE_ORDER)
            ->orderBy('position')
            ->get();

        return array_values($rows->map(fn (DayCardModel $r): DayCard => $this->toDomain($r))->all());
    }

    public function countForDay(PlanDayId $dayId): int
    {
        return DayCardModel::query()->where('day_id', $dayId->value)->count();
    }

    public function find(DayCardId $id): ?DayCard
    {
        $row = DayCardModel::query()->find($id->value);

        return $row === null ? null : $this->toDomain($row);
    }

    public function findForUpdate(DayCardId $id): ?DayCard
    {
        $row = DayCardModel::query()->whereKey($id->value)->lockForUpdate()->first();

        return $row === null ? null : $this->toDomain($row);
    }

    public function insertAll(array $cards): void
    {
        if ($cards === []) {
            return;
        }
        $userId = DB::table('plan_days')->where('id', $cards[0]->dayId()->value)->value('user_id');
        $now = now();
        $rows = [];
        foreach ($cards as $card) {
            $rows[] = [
                ...$this->columns($card),
                'id' => $card->id()->value,
                'user_id' => (string) $userId,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DayCardModel::query()->insert($rows);
    }

    public function save(DayCard $card): void
    {
        DayCardModel::query()->whereKey($card->id()->value)->update([...$this->columns($card), 'updated_at' => now()]);
    }

    /** @return list<DayCard> */
    public function returningFrom(PlanDayId $dayId): array
    {
        $rows = DayCardModel::query()
            ->where('day_id', $dayId->value)
            ->where('returns', true)
            ->orderByRaw(self::STAGE_ORDER)
            ->orderBy('position')
            ->get();

        return array_values($rows->map(fn (DayCardModel $r): DayCard => $this->toDomain($r))->all());
    }

    /** @return array<string, mixed> */
    private function columns(DayCard $card): array
    {
        return [
            'day_id' => $card->dayId()->value,
            'stage' => $card->stage()->value,
            'position' => $card->position(),
            'kind' => $card->kind()->value,
            'payload' => json_encode($card->payload(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'source' => $card->source()->value,
            'source_day_id' => $card->sourceDayId()?->value,
            'unit_kind' => $card->unitKind()->value,
            'unit_ref' => $card->unitRef(),
            'retry_of' => $card->retryOf()?->value,
            'result' => $card->result()?->value,
            'attempts' => $card->attempts(),
            'answered_at' => $card->answeredAt()?->format(DATE_ATOM),
            'returns' => $card->returns(),
        ];
    }

    private function toDomain(DayCardModel $row): DayCard
    {
        return DayCard::reconstitute(
            id: DayCardId::fromString($row->id),
            dayId: PlanDayId::fromString($row->day_id),
            stage: Stage::from($row->stage),
            position: $row->position,
            kind: CardKind::from($row->kind),
            payload: $row->payload,
            source: CardSource::from($row->source),
            sourceDayId: $row->source_day_id === null ? null : PlanDayId::fromString($row->source_day_id),
            unitKind: UnitKind::from($row->unit_kind),
            unitRef: $row->unit_ref,
            retryOf: $row->retry_of === null ? null : DayCardId::fromString($row->retry_of),
            result: $row->result === null ? null : CardResult::from($row->result),
            attempts: $row->attempts,
            answeredAt: $row->answered_at === null ? null : new DateTimeImmutable($row->answered_at),
            returns: $row->returns,
        );
    }
}

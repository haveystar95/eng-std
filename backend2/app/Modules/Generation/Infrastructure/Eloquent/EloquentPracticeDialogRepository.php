<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Domain\Entity\PracticeDialog;
use App\Modules\Generation\Domain\Repository\PracticeDialogRepository;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\PracticeDialogId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

final class EloquentPracticeDialogRepository implements PracticeDialogRepository
{
    public function __construct(private readonly PracticeDialogMapper $mapper) {}

    public function findById(PracticeDialogId $id): ?PracticeDialog
    {
        $model = PracticeDialogModel::query()->find($id->value);

        return $model !== null ? $this->mapper->toEntity($model) : null;
    }

    public function save(PracticeDialog $dialog): void
    {
        PracticeDialogModel::query()->updateOrCreate(
            ['id' => $dialog->id()->value],
            $this->mapper->toAttributes($dialog),
        );
    }

    public function lastConcludedForCollection(UserId $userId, CollectionId $collectionId): ?PracticeDialog
    {
        $model = PracticeDialogModel::query()
            ->where('user_id', $userId->value)
            ->where('collection_id', $collectionId->value)
            ->whereIn('status', ['finished', 'expired'])
            ->orderByDesc('created_at')
            ->first();

        return $model !== null ? $this->mapper->toEntity($model) : null;
    }

    public function staleActive(DateTimeImmutable $now, int $limit): array
    {
        $models = PracticeDialogModel::query()
            ->where('status', 'active')
            ->where('expires_at', '<', $now)
            ->orderBy('expires_at')
            ->limit($limit)
            ->get();

        return array_values(array_map(
            fn (PracticeDialogModel $m): PracticeDialog => $this->mapper->toEntity($m),
            $models->all(),
        ));
    }
}

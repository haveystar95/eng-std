<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Domain\Entity\Collection;
use App\Modules\Collections\Domain\Repository\CollectionRepository;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Support\Facades\DB;

final class EloquentCollectionRepository implements CollectionRepository
{
    public function __construct(private readonly CollectionMapper $mapper) {}

    public function findById(CollectionId $id): ?Collection
    {
        $model = CollectionModel::query()->with('items')->find($id->value);

        return $model !== null ? $this->mapper->toDomain($model) : null;
    }

    public function save(Collection $collection): void
    {
        DB::transaction(function () use ($collection): void {
            $collectionId = $collection->id()->value;

            CollectionModel::query()->updateOrCreate(
                ['id' => $collectionId],
                $this->mapper->toAttributes($collection),
            );

            $keepTermIds = array_map(
                static fn ($item): string => $item->termId->value,
                $collection->items(),
            );

            $removeQuery = CollectionItemModel::query()->where('collection_id', $collectionId);
            if ($keepTermIds !== []) {
                $removeQuery->whereNotIn('term_id', $keepTermIds);
            }
            $removeQuery->delete();

            foreach ($collection->items() as $item) {
                $model = CollectionItemModel::query()->firstOrCreate(
                    ['collection_id' => $collectionId, 'term_id' => $item->termId->value],
                    ['id' => Ulid::generate(), 'position' => $item->position, 'note' => $item->note],
                );

                if (! $model->wasRecentlyCreated) {
                    $model->update(['position' => $item->position, 'note' => $item->note]);
                }
            }
        });
    }
}

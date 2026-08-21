<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Domain\Entity\Collection;
use App\Modules\Collections\Domain\Repository\CollectionRepository;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

final class EloquentCollectionRepository implements CollectionRepository
{
    public function __construct(private readonly CollectionMapper $mapper) {}

    public function findById(CollectionId $id): ?Collection
    {
        $model = CollectionModel::query()->with('items')->find($id->value);

        return $model !== null ? $this->mapper->toDomain($model) : null;
    }

    public function findDefaultFor(UserId $ownerId): ?Collection
    {
        $model = CollectionModel::query()
            ->with('items')
            ->where('owner_id', $ownerId->value)
            ->where('is_default', true)
            ->first();

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

            // Items no longer in the aggregate are soft-deleted (SoftDeletes → sets deleted_at),
            // so GET /sync ships them as tombstones instead of hard-deleting to a ghost.
            $removeQuery = CollectionItemModel::query()->where('collection_id', $collectionId);
            if ($keepTermIds !== []) {
                $removeQuery->whereNotIn('term_id', $keepTermIds);
            }
            $removeQuery->delete();

            foreach ($collection->items() as $item) {
                // withTrashed so re-adding a previously removed term RESTORES its row (one row per
                // (collection, term)) rather than piling up a second — deleted_at is cleared below.
                $model = CollectionItemModel::withTrashed()->firstOrNew(
                    ['collection_id' => $collectionId, 'term_id' => $item->termId->value],
                );
                $model->id ??= Ulid::generate();
                $model->position = $item->position;
                $model->note = $item->note;
                $model->deleted_at = null;
                $model->save();
            }
        });
    }

    public function delete(CollectionId $id): void
    {
        // SoftDeletes on the model → sets deleted_at (cascades to items via FK on hard delete only).
        CollectionModel::query()->whereKey($id->value)->delete();
    }
}

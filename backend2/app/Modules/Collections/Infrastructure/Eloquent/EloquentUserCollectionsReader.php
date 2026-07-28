<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Application\Dto\CollectionPage;
use App\Modules\Collections\Application\Dto\CollectionSummaryView;
use App\Modules\Collections\Application\Port\UserCollectionsReader;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

final class EloquentUserCollectionsReader implements UserCollectionsReader
{
    public function forUser(UserId $userId, ?string $cursor, int $limit): CollectionPage
    {
        // ULIDs are time-sortable, so ordering by id desc == newest first, and the cursor
        // is simply the last id seen. Fetch one extra row to detect another page.
        $query = CollectionModel::query()
            ->where('owner_id', $userId->value)
            ->orderByDesc('id')
            ->limit($limit + 1);

        if ($cursor !== null && $cursor !== '') {
            $query->where('id', '<', $cursor);
        }

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $page = $rows->take($limit);

        $items = array_values($page->map($this->toView(...))->all());
        $nextCursor = $hasMore && $page->isNotEmpty() ? (string) $page->last()->id : null;

        return new CollectionPage($items, $nextCursor, $hasMore);
    }

    private function toView(CollectionModel $model): CollectionSummaryView
    {
        return new CollectionSummaryView(
            id: $model->id,
            type: $model->type,
            source: $model->source,
            title: $model->title,
            description: $model->description,
            sourceLang: $model->source_lang,
            targetLang: $model->target_lang,
            visibility: $model->visibility,
            itemsCount: $model->items_count,
            createdAt: $model->created_at->toDateTimeImmutable(),
        );
    }
}

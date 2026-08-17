<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Application\Dto\StoreCollectionPage;
use App\Modules\Collections\Application\Dto\StoreCollectionView;
use App\Modules\Collections\Application\Port\StoreCollectionsReader;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

final class EloquentStoreCollectionsReader implements StoreCollectionsReader
{
    public function forLanguagePair(
        UserId $viewer,
        LanguageCode $sourceLang,
        LanguageCode $targetLang,
        ?string $cursor,
        int $limit,
    ): StoreCollectionPage {
        // Store = public collections + system collections, for this language pair. Ordered by
        // (topic, id) so the client can render topic sections; keyset-paginated on that same tuple.
        $query = CollectionModel::query()
            ->where('source_lang', $sourceLang->value)
            ->where('target_lang', $targetLang->value)
            ->where(function ($w): void {
                $w->where('visibility', 'public')->orWhere('type', 'system');
            })
            ->orderByRaw("COALESCE(collections.topic, '') asc")
            ->orderBy('collections.id', 'asc')
            ->limit($limit + 1);

        $decoded = $this->decodeCursor($cursor);
        if ($decoded !== null) {
            $query->whereRaw("(COALESCE(collections.topic, ''), collections.id) > (?, ?)", $decoded);
        }

        $rows = $query->get();
        $hasMore = $rows->count() > $limit;
        $page = $rows->take($limit);

        $ids = array_values(array_map(
            static fn (CollectionModel $m): string => $m->id,
            $page->all(),
        ));
        $subscribed = $this->subscribedIds($viewer, $ids);
        $levels = $this->levelsFor($ids);

        $items = array_values($page->map(
            fn (CollectionModel $m): StoreCollectionView => $this->toView(
                $m,
                isset($subscribed[$m->id]),
                $levels[$m->id] ?? null,
            ),
        )->all());

        $last = $page->last();
        $nextCursor = $hasMore && $last !== null
            ? $this->encodeCursor((string) ($last->topic ?? ''), $last->id)
            : null;

        return new StoreCollectionPage($items, $nextCursor, $hasMore);
    }

    private function toView(CollectionModel $model, bool $isSubscribed, ?string $level): StoreCollectionView
    {
        return new StoreCollectionView(
            id: $model->id,
            title: $model->title,
            description: $model->description,
            topic: $model->topic,
            sourceLang: $model->source_lang,
            targetLang: $model->target_lang,
            isPremium: (bool) $model->is_premium,
            isSubscribed: $isSubscribed,
            itemsCount: $model->items_count,
            imageUrl: $model->image_url,
            imageAuthor: $model->image_author,
            imageAuthorUrl: $model->image_author_url,
            level: $level,
        );
    }

    /**
     * CEFR range per collection, derived from its terms' `cefr` (read projection over the
     * Vocabulary tables — the generator never linked the requested levels back to the collection).
     * 'A1'..'C2' order lexicographically, so string MIN/MAX give the true CEFR bounds. Collapses to
     * a single level when min == max; skips terms with no level; absent when none carry one.
     *
     * @param  list<string>  $collectionIds
     * @return array<string, string>
     */
    private function levelsFor(array $collectionIds): array
    {
        if ($collectionIds === []) {
            return [];
        }

        $rows = DB::table('collection_items as ci')
            ->join('terms as t', 't.id', '=', 'ci.term_id')
            ->whereIn('ci.collection_id', $collectionIds)
            ->whereNull('ci.deleted_at')
            ->whereNotNull('t.cefr')
            ->groupBy('ci.collection_id')
            ->selectRaw('ci.collection_id as cid, min(t.cefr) as lo, max(t.cefr) as hi')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $lo = (string) $row->lo;
            $hi = (string) $row->hi;
            $map[(string) $row->cid] = $lo === $hi ? $lo : $lo . '–' . $hi;
        }

        return $map;
    }

    /**
     * @param  list<string>  $collectionIds
     * @return array<string, true>
     */
    private function subscribedIds(UserId $viewer, array $collectionIds): array
    {
        if ($collectionIds === []) {
            return [];
        }

        $rows = DB::table('user_collections')
            ->where('user_id', $viewer->value)
            ->whereIn('collection_id', $collectionIds)
            ->whereNull('unsubscribed_at') // an unsubscribed row is a tombstone, not a subscription
            ->pluck('collection_id');

        $set = [];
        foreach ($rows as $id) {
            $set[(string) $id] = true;
        }

        return $set;
    }

    /** @return array{0: string, 1: string}|null */
    private function decodeCursor(?string $cursor): ?array
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }
        $raw = base64_decode($cursor, true);
        if ($raw === false || ! str_contains($raw, "\x1f")) {
            return null;
        }
        [$topic, $id] = explode("\x1f", $raw, 2);

        return [$topic, $id];
    }

    private function encodeCursor(string $topicKey, string $id): string
    {
        return base64_encode($topicKey . "\x1f" . $id);
    }
}

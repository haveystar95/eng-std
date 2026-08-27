<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Application\Dto\StoreCatalogueItem;
use App\Modules\Collections\Application\Dto\StoreCatalogueSummary;
use App\Modules\Collections\Application\Port\StoreCatalogueReader;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class EloquentStoreCatalogueReader implements StoreCatalogueReader
{
    /**
     * How many of the previewed decks are ALSO published as bare titles.
     *
     * Three, which is what `topics` held before the covers existed. A build that predates
     * {@see StoreCatalogueItem} reads that field and lays three chips out; widening it to the whole
     * window would silently redesign a screen on a phone nobody rebuilt.
     */
    private const TOPICS_PREVIEW = 3;

    public function __construct(private readonly CollectionLevels $levels) {}

    public function summaryFor(
        UserId $viewer,
        LanguageCode $sourceLang,
        LanguageCode $targetLang,
        int $sampleSize,
    ): StoreCatalogueSummary {
        $count = $this->takeable($viewer, $sourceLang, $targetLang)->count();
        if ($count === 0) {
            return new StoreCatalogueSummary(0, [], []);
        }

        // Same ordering the store screen pages in, so the covers on the home screen are the decks
        // the learner meets first when they follow the link.
        $rows = $this->takeable($viewer, $sourceLang, $targetLang)
            ->orderByRaw("COALESCE(collections.topic, '') asc")
            ->orderBy('collections.id', 'asc')
            ->limit(max(0, $sampleSize))
            ->get([
                'collections.id', 'collections.title', 'collections.description',
                'collections.items_count', 'collections.image_url', 'collections.is_premium',
                'collections.source_lang', 'collections.target_lang',
            ]);

        $ids = array_values(array_map(static fn (CollectionModel $m): string => $m->id, $rows->all()));
        $levels = $this->levels->forCollections($ids);

        $items = array_values($rows->map(static fn (CollectionModel $m): StoreCatalogueItem => new StoreCatalogueItem(
            id: $m->id,
            title: $m->title,
            itemsCount: $m->items_count,
            imageUrl: $m->image_url,
            level: $levels[$m->id] ?? null,
            description: $m->description,
            sourceLang: $m->source_lang,
            targetLang: $m->target_lang,
            isPremium: (bool) $m->is_premium,
        ))->all());

        return new StoreCatalogueSummary(
            $count,
            array_map(
                static fn (StoreCatalogueItem $i): string => $i->title,
                array_slice($items, 0, self::TOPICS_PREVIEW),
            ),
            $items,
        );
    }

    /**
     * Store rows this learner does not already have: public + system collections of the pair, minus
     * the ones they are actively subscribed to. An unsubscribed row is a tombstone and puts the deck
     * back on the shelf, which is why the NOT EXISTS carries the same `unsubscribed_at IS NULL` the
     * store listing uses to draw its «уже добавлено» flag.
     *
     * @return Builder<CollectionModel>
     */
    private function takeable(UserId $viewer, LanguageCode $sourceLang, LanguageCode $targetLang): Builder
    {
        return CollectionModel::query()
            ->where('source_lang', $sourceLang->value)
            ->where('target_lang', $targetLang->value)
            ->where(function (Builder $w): void {
                $w->where('visibility', 'public')->orWhere('type', 'system');
            })
            ->whereNotExists(function (QueryBuilder $q) use ($viewer): void {
                $q->from('user_collections')
                    ->whereColumn('user_collections.collection_id', 'collections.id')
                    ->where('user_collections.user_id', $viewer->value)
                    ->whereNull('user_collections.unsubscribed_at');
            });
    }
}

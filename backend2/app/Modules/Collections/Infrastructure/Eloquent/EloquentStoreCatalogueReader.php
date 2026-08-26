<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Application\Dto\StoreCatalogueSummary;
use App\Modules\Collections\Application\Port\StoreCatalogueReader;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class EloquentStoreCatalogueReader implements StoreCatalogueReader
{
    public function summaryFor(
        UserId $viewer,
        LanguageCode $sourceLang,
        LanguageCode $targetLang,
        int $sampleSize,
    ): StoreCatalogueSummary {
        $count = $this->takeable($viewer, $sourceLang, $targetLang)->count();
        if ($count === 0) {
            return new StoreCatalogueSummary(0, []);
        }

        // Same ordering the store screen pages in, so the three example topics on the empty-state
        // card are the three the learner meets first when they follow the link.
        $titles = $this->takeable($viewer, $sourceLang, $targetLang)
            ->orderByRaw("COALESCE(collections.topic, '') asc")
            ->orderBy('collections.id', 'asc')
            ->limit(max(0, $sampleSize))
            ->pluck('collections.title');

        return new StoreCatalogueSummary(
            $count,
            array_values(array_map(static fn ($t): string => (string) $t, $titles->all())),
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

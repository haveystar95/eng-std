<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

/**
 * «Сколько готовых наборов меня ждёт» — the store, counted rather than paged.
 *
 * The home screen offers the store as one quiet line («или взять из 17 готовых») and, from кадр
 * 19-2 on, as a STRIP OF COVERS at the foot of the evening screen and as the main entrance on the
 * first day (19-3). All three need a NUMBER and a few DECKS, and none of them needs a page: asking
 * {@see StoreCollectionsReader} for the catalogue would fetch descriptions, premium flags and
 * subscription state to throw all but a handful of them away.
 *
 * The count is of what the learner can still TAKE — decks they are not already subscribed to. A
 * store of 17 of which 17 are already in the library is not an invitation, and saying «17 готовых»
 * there would be a lie by omission.
 *
 * [topics] is the older, thinner shape of the same preview — the titles alone. It stays because a
 * phone built before the covers existed reads it and would otherwise get an empty store from a
 * server that has one; it is derived from [items] rather than queried a second time, so the two
 * cannot come to describe different decks.
 */
final readonly class StoreCatalogueSummary
{
    /**
     * @param  list<StoreCatalogueItem>  $items   a few decks, in the catalogue's own order — a window, not a page
     * @param  list<string>              $topics  the first few of the same titles, for older clients
     */
    public function __construct(
        public int $count,
        public array $topics,
        public array $items = [],
    ) {}
}

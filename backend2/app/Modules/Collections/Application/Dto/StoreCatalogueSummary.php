<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

/**
 * «Сколько готовых наборов меня ждёт» — the store, counted rather than paged.
 *
 * The home screen offers the store as one quiet line («или выбрать из 17 готовых») and, on the very
 * first day, as a card with three example topics. Both need a NUMBER and a few TITLES, and neither
 * needs a page: asking {@see StoreCollectionsReader} for the catalogue would fetch rows, levels and
 * subscription flags to throw all but three of them away.
 *
 * The count is of what the learner can still TAKE — decks they are not already subscribed to. A
 * store of 17 of which 17 are already in the library is not an invitation, and saying «17 готовых»
 * there would be a lie by omission.
 */
final readonly class StoreCatalogueSummary
{
    /** @param list<string> $topics  a few titles, in the catalogue's own order — a preview, not a page */
    public function __construct(
        public int $count,
        public array $topics,
    ) {}
}

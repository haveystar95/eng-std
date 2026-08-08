<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use DateTimeImmutable;

/**
 * One page of a delta sync. `serverTime` is the frozen upper bound the client stores as its next
 * `since` once `hasMore` is false. `nextCursor` (opaque) is passed back verbatim to fetch the
 * next page while `hasMore` is true.
 */
final readonly class SyncDeltaView
{
    /**
     * @param  list<CollectionChange>  $collections
     * @param  list<CollectionItemChange>  $collectionItems
     * @param  list<TermSyncView>  $terms
     * @param  list<ProgressSyncRow>  $progress
     * @param  list<TriageSyncRow>  $triages
     */
    public function __construct(
        public DateTimeImmutable $serverTime,
        public ?string $nextCursor,
        public bool $hasMore,
        public array $collections,
        public array $collectionItems,
        public array $terms,
        public array $progress,
        public array $triages,
    ) {}
}

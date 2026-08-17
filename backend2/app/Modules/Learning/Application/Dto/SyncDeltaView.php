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
     * @param  list<string>  $exerciseModes  the trainers this user has switched on, in rotation
     *                                       order — settings, not a change stream, so they ride
     *                                       every page rather than being diffed
     * @param  list<array{mode: string, min_acquisition: string, min_learning_step: int|null, min_reps: int|null, options_policy: string, min_step: int}>  $modeAdmission
     *        the acquisition-ladder matrix, riding along for the same reason: the device builds
     *        sessions offline and needs to know which rung opens which trainer, not only which
     *        trainers exist
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
        public array $exerciseModes = [],
        public array $modeAdmission = [],
    ) {}
}

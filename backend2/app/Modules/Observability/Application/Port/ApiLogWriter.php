<?php

declare(strict_types=1);

namespace App\Modules\Observability\Application\Port;

use App\Modules\Observability\Application\Dto\ApiLogEntry;

/** Sink for recorded HTTP calls. Implementations must never throw into the caller. */
interface ApiLogWriter
{
    /** Returns the id of the row written, or null if the write was dropped. */
    public function write(ApiLogEntry $entry): ?string;

    /**
     * Late-stamp the collection on already-written rows — the collection-generation call is logged
     * before the collection it creates exists. @see OutboundCallContext::attachCollection().
     *
     * @param  list<string>  $logIds
     */
    public function linkCollection(array $logIds, string $collectionId): void;
}

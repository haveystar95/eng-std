<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Domain\ValueObject\RejectedItem;

/**
 * The barrier's log: items a generation refused to write, and why.
 *
 * Separate from the request aggregate on purpose. A rejection is an append-only observation about
 * one item, not a state the request is in — the request still succeeded, it just delivered less.
 * Same shape and same reasoning as {@see EnrichmentJournal::recordFindings()}.
 */
interface RecordsGenerationRejections
{
    /** @param  list<RejectedItem>  $rejections */
    public function record(string $requestId, array $rejections): void;
}

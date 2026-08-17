<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Query;

use App\Modules\Vocabulary\Application\Dto\EnrichableTermView;
use App\Modules\Shared\Domain\ValueObject\TermId;

/**
 * Returns the term IF it is eligible for enrichment — source=user AND it has no translations yet —
 * else null. This is the idempotency gate: once a term has a translation it is no longer returned,
 * so a re-run of the enrich job skips the (paid) LLM call.
 */
interface EnrichableTermReader
{
    public function find(TermId $termId): ?EnrichableTermView;
}

<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

/**
 * Run the enrichment станок over a chunk of terms. Ids are plain strings, not TermId, because this
 * command crosses a queue boundary — the job serialises exactly this list.
 */
final readonly class BuildTermEnrichments
{
    /** @param  list<string>  $termIds */
    public function __construct(
        public array $termIds,
        public string $generatorVersion,
    ) {}
}

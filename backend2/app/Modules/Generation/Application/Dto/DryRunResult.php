<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * What a dry run concluded. `kept` is «сколько строк станок записал бы», not «сколько строк
 * хорошие» — the two differ when the example is already stocked, and the cap reason says so.
 */
final readonly class DryRunResult
{
    /** @param  list<DryRunItemVerdict>  $items */
    public function __construct(
        public array $items,
        public int $kept,
        public int $total,
        public ?string $termId,
        public string $termText,
        public ?string $exampleSentence,
        /** How many sentences the dedup set already held — the reason a good row can still be a dupe. */
        public int $existingCount,
        public int $suppressedCount,
    ) {}
}

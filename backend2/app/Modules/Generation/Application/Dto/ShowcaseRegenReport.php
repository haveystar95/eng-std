<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * What a regeneration pass did, or — on a dry run — what it would do and what it would cost.
 *
 * The cursor travels in the report because the pass is resumable: it is the id of the last term the
 * run actually finished, and handing it back to `--after` continues exactly where it stopped.
 */
final readonly class ShowcaseRegenReport
{
    /**
     * @param  int  $pending  terms matching the filter that still carry an old prompt version
     * @param  list<array{term: string, was: string, now: string}>  $replaced  the keys that changed
     * @param  list<array{term_id: string, stage: string, error: string}>  $failures
     * @param  string|null  $cursor  last finished term id — pass it to `--after` to resume
     */
    public function __construct(
        public int $pending = 0,
        public int $attempted = 0,
        public int $regenerated = 0,
        public array $replaced = [],
        public array $failures = [],
        public ?string $cursor = null,
        public int $tokensIn = 0,
        public int $tokensOut = 0,
        public string $costUsd = '0.000000',
        public ?ShowcaseCostEstimate $estimate = null,
    ) {}
}

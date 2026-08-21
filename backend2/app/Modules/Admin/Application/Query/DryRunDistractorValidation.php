<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

/**
 * Run distractor rows past the real validator without writing anything.
 *
 * Exactly one reference is used: a term from the database (`termId`), or a hand-typed pair
 * (`manualTerm` + `manualExample`). The term wins when both arrive, because a real term brings the
 * real dedup set and the real suppressions with it, and that is the run worth trusting.
 */
final readonly class DryRunDistractorValidation
{
    /**
     * @param  list<array{sentence: string, error_span: string, correction: string, error_type?: string|null}>  $items
     */
    public function __construct(
        public array $items,
        public ?string $termId = null,
        public ?string $manualTerm = null,
        public ?string $manualExample = null,
    ) {}
}

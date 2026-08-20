<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Generation\Domain\ValueObject\EnrichmentFinding;

/**
 * What one translation-audit pass saw and what it cost. A report, not a state machine: the audit
 * writes findings and changes no content, so re-running it is always safe and always honest.
 */
final readonly class TranslationAuditOutcome
{
    /**
     * @param  list<EnrichmentFinding>  $findings  written to the journal unless the run was a dry one
     * @param  list<array{term: string, stored: string, fresh: string}>  $disagreements  the pairs a
     *         human is being asked to read, in the order they were checked
     * @param  list<array{term_id: string, error: string}>  $failures
     */
    public function __construct(
        public int $termsSeen = 0,
        public array $findings = [],
        public array $disagreements = [],
        public array $failures = [],
        public ?int $tokensIn = null,
        public ?int $tokensOut = null,
        public ?string $costUsd = null,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

use App\Modules\Generation\Domain\ValueObject\EnrichmentFinding;
use App\Modules\Vocabulary\Application\Dto\TermEnrichmentExportRow;

/** One term in the proofreading export: its stored content plus whatever the run flagged about it. */
final readonly class EnrichmentExportItem
{
    /** @param  list<EnrichmentFinding>  $findings */
    public function __construct(
        public TermEnrichmentExportRow $row,
        public array $findings,
    ) {}
}

<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Port\TermEnrichmentWriter;

/**
 * Writes nothing, records nothing. For the pipeline tests, whose subject is which terms are created
 * and in what order — not what the enrichment tables end up holding.
 */
final class NullTermEnrichmentWriter implements TermEnrichmentWriter
{
    public function append(
        TermId $termId,
        ?string $exampleId,
        array $variants,
        array $distractors,
        string $generatorVersion,
        array $synonyms = [],
    ): void {}
}

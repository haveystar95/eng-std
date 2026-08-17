<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Port;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\AcceptedVariantInput;
use App\Modules\Vocabulary\Application\Dto\ExampleDistractorInput;

/**
 * Appends accepted variants and example distractors. Vocabulary owns both tables.
 *
 * Append-and-ignore-duplicates, never replace: a variant already in the key stays exactly as it is
 * (it may have been hand-corrected during proofreading, and a later run must not silently undo
 * that). This is also what makes a job retry harmless.
 */
interface TermEnrichmentWriter
{
    /**
     * @param  list<AcceptedVariantInput>  $variants
     * @param  list<ExampleDistractorInput>  $distractors
     */
    public function append(
        TermId $termId,
        ?string $exampleId,
        array $variants,
        array $distractors,
        string $generatorVersion,
    ): void;
}

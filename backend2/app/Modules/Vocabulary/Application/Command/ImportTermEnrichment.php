<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\AcceptedVariantInput;
use App\Modules\Vocabulary\Application\Dto\ExampleDistractorInput;

/**
 * Store the enrichment of one term: extra correct answers, plus wrong sentences for its pinned
 * example. Both lists are already validated — Vocabulary owns the tables, not the judgement.
 */
final readonly class ImportTermEnrichment
{
    /**
     * @param  list<AcceptedVariantInput>  $variants
     * @param  list<ExampleDistractorInput>  $distractors  ignored when $exampleId is null
     */
    public function __construct(
        public TermId $termId,
        public ?string $exampleId,
        public array $variants,
        public array $distractors,
        public string $generatorVersion,
    ) {}
}

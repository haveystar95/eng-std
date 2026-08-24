<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Command;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\AcceptedVariantInput;
use App\Modules\Vocabulary\Application\Dto\ExampleDistractorInput;
use App\Modules\Vocabulary\Application\Dto\TermSynonymInput;

/**
 * Store the enrichment of one term: extra correct answers, near-synonyms, and wrong sentences for
 * its pinned example. Every list is already validated — Vocabulary owns the tables, not the
 * judgement.
 */
final readonly class ImportTermEnrichment
{
    /**
     * @param  list<AcceptedVariantInput>  $variants
     * @param  list<ExampleDistractorInput>  $distractors  ignored when $exampleId is null
     * @param  list<TermSynonymInput>  $synonyms  near-synonyms on the term's own studied side. A
     *        separate list from $variants and not a wider reading of it: a variant is another
     *        spelling of this word and a synonym is another word, and only one of the two answers a
     *        card whose question was the sound of the term. See the `term_synonyms` migration.
     */
    public function __construct(
        public TermId $termId,
        public ?string $exampleId,
        public array $variants,
        public array $distractors,
        public string $generatorVersion,
        public array $synonyms = [],
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * Everything the validator needs about ONE term to judge the model's proposal, with no database
 * and no framework in reach: the term's own accepted forms (what a typed answer already matches),
 * the pinned example the distractors are built against, and the natural-language fields the
 * purity check reads.
 *
 * `acceptedForms` is the CURRENT key — term text plus any variant stored by an earlier run. The
 * validator compares proposed distractors against it, which is what makes a re-run at a new
 * version unable to introduce a distractor that an already-accepted variant makes correct.
 */
final readonly class EnrichmentCandidate
{
    /**
     * @param  list<string>  $acceptedForms  non-empty: the term text first, then stored variants
     * @param  list<RawDistractor>  $distractors
     * @param  list<RawVariant>  $variants
     * @param  list<RawLanguageNote>  $languageNotes  the model's own lexis observations (see the validator)
     * @param  list<string>  $synonyms  near-synonyms the model proposed, unvalidated
     * @param  list<string>  $existingSynonyms  near-synonyms ALREADY stored for this term
     * @param  list<string>  $existingDistractors  sentences ALREADY stored against the pinned example
     * @param  bool  $backTranslationAsked  whether the shape that produced this pack was asked for a
     *         back-translation at all. It decides what a NULL one means, and the two meanings are
     *         opposite: for a shape that had to return one, null is a defect worth a human's time;
     *         for a shape that was never asked (the machinery shape produces no translations at all),
     *         null is silence, and reporting silence as a finding buries the real ones.
     */
    public function __construct(
        public string $termId,
        public array $acceptedForms,
        public ?string $exampleId,
        public ?string $exampleSentence,
        public ?string $translation,           // RU — the prompt side
        public ?string $exampleTranslation,    // RU
        public array $distractors,
        public array $variants,
        public ?string $backTranslation,       // model's EN reconstruction from the RU prompt alone
        public array $languageNotes = [],
        public array $existingDistractors = [],
        public bool $backTranslationAsked = true,
        public array $synonyms = [],
        public array $existingSynonyms = [],
    ) {}
}

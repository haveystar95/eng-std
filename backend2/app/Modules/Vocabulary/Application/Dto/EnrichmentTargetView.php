<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * Everything the enrichment станок needs to know about one term, read in one batch so nobody joins
 * Vocabulary's tables from outside. `acceptedForms` is the term's CURRENT answer key — its text plus
 * variants a previous run already stored — because the validator judges new distractors against
 * what is accepted today, not against the bare term text.
 *
 * The example is the PINNED one (ordered by id, like every other reader), so distractors are written
 * against the sentence the card actually shows.
 */
final readonly class EnrichmentTargetView
{
    /**
     * @param  list<string>  $acceptedForms  non-empty; the term text first
     * @param  string  $lang  the language being LEARNED (the term's own)
     * @param  string|null  $translationLang  the learner's language — the primary translation's. Null
     *        when the term has no translation, in which case there is no prompt side to reason about.
     */
    public function __construct(
        public string $termId,
        public string $text,
        public array $acceptedForms,
        public ?string $translation,
        public ?string $exampleId,
        public ?string $exampleSentence,
        public ?string $exampleTranslation,
        public string $lang = 'en',
        public ?string $translationLang = null,
    ) {}
}

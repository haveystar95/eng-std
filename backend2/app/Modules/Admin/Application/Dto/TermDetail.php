<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * Everything hanging off one term: translations, ALL its examples (the pinned one flagged) with
 * their distractors, the accepted answer variants the grader uses, whatever the last enrichment
 * run flagged about it, the collections holding it, and its progress footprint.
 *
 * The enrichment-side collections (`term_accepted_variants`, `example_distractors`,
 * `enrichment_findings`) are read defensively: on an install where the станок has never run they
 * come back empty and the page still renders.
 */
final readonly class TermDetail
{
    /**
     * @param list<TermTranslationRow> $translations
     * @param list<TermExampleRow> $examples
     * @param list<CollectionRefRow> $collections
     * @param list<TermVariantRow> $acceptedVariants
     * @param list<EnrichmentFindingRow> $findings
     */
    public function __construct(
        public string $id,
        public string $lang,
        public string $text,
        public string $normalizedText,
        public string $type,
        public ?string $pos,
        public ?string $ipa,
        public ?string $imageUrl,
        public ?string $imageAuthor,
        public ?string $imageAuthorUrl,
        public ?string $cefr,
        public string $source,
        public ?string $createdAt,
        public ?string $updatedAt,
        public array $translations,
        public array $examples,
        public array $collections,
        public array $acceptedVariants,
        public array $findings,
        /** The generator version that last enriched this term, or null if it never was. */
        public ?string $enrichmentVersion,
        public int $progressCount,   // how many (user,term) progress rows reference this term
    ) {}
}

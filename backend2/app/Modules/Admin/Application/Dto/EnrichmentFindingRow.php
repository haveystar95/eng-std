<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * Something the enrichment run flagged about this term — an ambiguous translation, a word that
 * isn't a word, Ukrainian leaking into a Russian translation. These are the reasons a term might
 * need a human look, so the term page shows them where the decision gets made.
 */
final readonly class EnrichmentFindingRow
{
    public function __construct(
        public string $kind,        // ambiguity | language | ua_leakage | misspelled_or_nonword | variant_conflict
        public ?string $field,
        public string $detail,
        public string $generatorVersion,
        public ?string $createdAt,
    ) {}
}

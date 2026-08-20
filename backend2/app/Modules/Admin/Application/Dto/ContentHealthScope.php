<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * The coverage counters for one slice of the dictionary.
 *
 * Three slices are reported — the whole base, terms held by at least one SYSTEM collection, and
 * terms held by at least one user collection (custom/shared). They are NOT a partition and are not
 * meant to add up: a term can sit in a system collection and in someone's own list at once, and a
 * term in no collection at all appears only in the total. Stated here because a table of three rows
 * whose numbers do not sum invites exactly one wrong conclusion.
 */
final readonly class ContentHealthScope
{
    /** @param  list<ContentVersionCount>  $enrichmentVersions */
    public function __construct(
        public string $scope,             // all | system | user
        public int $terms,
        public int $withDistractors,      // ≥1 usable distractor on the pinned example
        public int $pickCorrectReady,     // the whole pick_correct card assembles (эталон + 2 неверных)
        public int $withVariants,
        public int $withoutExample,
        public int $needsEnrichment,
        public float $estimatedTopUpUsd,
        public array $enrichmentVersions,
    ) {}
}

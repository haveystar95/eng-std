<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * «Здоровье контента» in one read: coverage over the whole dictionary and per collection, plus the
 * two journals that explain why coverage is where it is — what a human or the audit THREW OUT
 * (`enrichment_suppressions`) and what the language barrier REFUSED to write in the first place
 * (`generation_rejections`).
 *
 * Read-only and derived: nothing here is stored, so it is always the answer for the moment it was
 * asked, and re-running the станок changes it without a migration.
 */
final readonly class ContentHealthSummary
{
    /**
     * @param  list<ContentHealthCollectionRow>  $collections
     * @param  list<ContentLabelCount>  $suppressionsBySource
     * @param  list<ContentLabelCount>  $rejectionsByField
     */
    public function __construct(
        public ContentHealthScope $all,
        public ContentHealthScope $system,
        public ContentHealthScope $user,
        public array $collections,
        public int $suppressionsTotal,
        public array $suppressionsBySource,
        public int $rejectionsTotal,
        public array $rejectionsByField,
        /** The version a plain run writes and skips by today — what «уже обогащён» is measured against. */
        public string $currentGeneratorVersion,
        public int $minDistractors,
        public float $costPerTermUsd,
    ) {}
}

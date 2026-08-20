<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * One term as the «Здоровье контента» tables show it: what it has, what a card can be built from it,
 * and whether the станок would help.
 *
 * The two "broken" flags are kept apart on purpose, because they have different cures:
 *
 *  * `missingExample` — there is no pinned sentence at all. The станок writes distractors and
 *    variants AGAINST an example; with no example it has nothing to work on, so this is fixed by
 *    regenerating the example, never by a догон.
 *  * `needsEnrichment` — there IS a live example, and it is under-stocked: fewer than
 *    {@see \App\Modules\Admin\Application\Service\ContentTopUp::MIN_DISTRACTORS} usable distractors,
 *    or no accepted variants at all. THIS is what a догон costs money to fix.
 */
final readonly class ContentHealthTermRow
{
    /** @param  list<string>  $needsEnrichmentReasons  `few_distractors` | `no_variants` */
    public function __construct(
        public string $termId,
        public string $text,
        public ?string $translation,
        public bool $hasExample,
        public bool $missingExample,
        /** Span-distinct — the number the card can actually deal, not the row count. */
        public int $usableDistractors,
        /** Raw rows on the pinned example; differs from `usableDistractors` when spans collide. */
        public int $rawDistractors,
        public bool $pickCorrectReady,
        public int $variants,
        public ?string $enrichmentVersion,
        public bool $needsEnrichment,
        public array $needsEnrichmentReasons,
    ) {}
}

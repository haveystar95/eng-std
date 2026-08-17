<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

/**
 * Apply a human review of an enrichment run. The payload is the decoded review file, passed as a plain
 * array because it is data authored by a person, not a shape this layer gets to dictate.
 *
 * @phpstan-type ReviewPayload array{
 *     generator_version?: string,
 *     remove_distractors?: list<array{term: string, sentence?: string, contains?: string}>,
 *     remove_variants?: list<array{term: string, text: string}>,
 *     add_variants?: list<array{term: string, text: string, note?: string}>,
 *     set_translations?: list<array{term: string, translation?: string, example_translation?: string}>,
 *     acknowledge_all_remaining_findings?: bool
 * }
 */
final readonly class ApplyEnrichmentReview
{
    /** @param  ReviewPayload  $review */
    public function __construct(
        public array $review,
        public string $generatorVersion,
    ) {}
}

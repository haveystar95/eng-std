<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

/**
 * Populate `enrichment_suppressions` from decisions that predate the table: an applied review file's
 * `remove_distractors`, or (when one exists) an audit run's own record of what it deleted. Rows are
 * addressed by TEXT, the same convention {@see ApplyEnrichmentReview} uses — ids are regenerated on
 * every `migrate:fresh`.
 */
final readonly class BackfillEnrichmentSuppressions
{
    /** @param  list<array{term: string, sentence: string, source: string}>  $rows */
    public function __construct(public array $rows) {}
}

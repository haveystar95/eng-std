<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * One term's enrichment as a human reads it during proofreading: what the card asks, what counts as
 * right, and every wrong sentence we wrote against it. Shaped for the export file, not for the app.
 *
 * @phpstan-type ExportVariant array{text: string, note: string|null}
 * @phpstan-type ExportDistractor array{sentence: string, error_type: string, error_span: string, correction: string}
 */
final readonly class TermEnrichmentExportRow
{
    /**
     * @param  list<ExportVariant>  $variants
     * @param  list<ExportDistractor>  $distractors
     */
    public function __construct(
        public string $termId,
        public string $text,
        public ?string $translation,
        public ?string $exampleSentence,
        public array $variants,
        public array $distractors,
    ) {}
}

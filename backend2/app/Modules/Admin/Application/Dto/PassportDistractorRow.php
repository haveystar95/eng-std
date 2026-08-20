<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * A live distractor with the one fact the term page does not carry: whether a card would actually
 * DEAL it.
 *
 * `usable` is false for the second and later rows sharing an `error_span` — two options broken in
 * the same place turn «какое предложение верное» into «какое написание этого слова мы имели в
 * виду», so the assembler keeps one per span. Marked rather than hidden: someone reading the page
 * is deciding what to do about the term, and a row silently missing from the list is a row nobody
 * knows to delete.
 *
 * `errorType` is the станок's own label and is NOISY — it is the model's guess at what kind of
 * mistake it wrote, checked by nothing. Read the span and the correction; treat the label as a hint.
 */
final readonly class PassportDistractorRow
{
    public function __construct(
        public string $id,
        public string $sentence,
        public string $errorType,
        public string $errorSpan,
        public string $correction,
        public string $generatorVersion,
        public bool $usable,
    ) {}
}

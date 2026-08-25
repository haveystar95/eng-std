<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/**
 * What the model answered about one term's reading, with the provenance a written row must carry.
 *
 * `text` may legitimately be an empty string: the prompt says so for a term already spelled the way
 * a reader of the support language would sound it out, and «nothing to write» is an ANSWER here, not
 * a failure. The gate downstream treats blank and refused alike, which is why this DTO does not.
 *
 * `promptVersion` is the CORE version the rules were taken from — the same string the станок's own
 * reading hints are stamped with, because the bytes that produced both are the same bytes.
 */
final readonly class TermReadingResult
{
    public function __construct(
        public string $text,
        public string $model,
        public string $promptVersion,
        public ?int $tokensIn = null,
        public ?int $tokensOut = null,
    ) {}
}

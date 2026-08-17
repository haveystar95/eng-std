<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Application\Dto;

/**
 * A deliberately wrong variant of an example sentence, already validated by the caller: the span is
 * known to occur in the sentence and the sentence is known NOT to be an accepted answer.
 */
final readonly class ExampleDistractorInput
{
    public function __construct(
        public string $sentence,
        public string $errorType,
        public string $errorSpan,
        public string $correction,
    ) {}
}

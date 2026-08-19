<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

use InvalidArgumentException;

/** A usage example for a term: a target-language sentence with an optional translation. */
final class Example
{
    public readonly string $sentence;

    /**
     * @param  Provenance|null  $provenance  which prompt version and model wrote this sentence,
     *                                       when it came from the станок — see {@see Provenance}.
     */
    public function __construct(
        string $sentence,
        public readonly ?string $sentenceTranslation = null,
        public readonly ?Provenance $provenance = null,
    ) {
        $trimmed = trim($sentence);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Example sentence cannot be empty.');
        }
        $this->sentence = $trimmed;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

use InvalidArgumentException;

/** A usage example for a term: a target-language sentence with an optional translation. */
final class Example
{
    public readonly string $sentence;

    public function __construct(string $sentence, public readonly ?string $sentenceTranslation = null)
    {
        $trimmed = trim($sentence);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Example sentence cannot be empty.');
        }
        $this->sentence = $trimmed;
    }
}

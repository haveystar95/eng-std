<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

use InvalidArgumentException;

/** The original surface text of a term (word or phrase). */
final class TermText
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            throw new InvalidArgumentException('Term text cannot be empty.');
        }
        $this->value = $trimmed;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Domain\ValueObject;

use App\Modules\Shared\Domain\Service\TextNormalizer;
use InvalidArgumentException;

/**
 * The original surface text of a term (word or phrase).
 *
 * Canonical Unicode, not whatever byte sequence arrived: see {@see TextNormalizer}. This value
 * object is one of the three content gates — with {@see Translation} and {@see Example} — and
 * canonicalising here rather than at each writer is what makes «no second spelling reaches the
 * store» a property of the type instead of a rule everyone has to remember.
 */
final class TermText
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $trimmed = trim((new TextNormalizer())->canonical($value));
        if ($trimmed === '') {
            throw new InvalidArgumentException('Term text cannot be empty.');
        }
        $this->value = $trimmed;
    }
}

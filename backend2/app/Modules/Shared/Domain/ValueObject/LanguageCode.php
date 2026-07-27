<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObject;

use InvalidArgumentException;

/** BCP-47-ish language code, normalized to lowercase (e.g. "en", "ru", "en-us"). */
final class LanguageCode
{
    public readonly string $value;

    public function __construct(string $value)
    {
        $normalized = strtolower(trim($value));
        if (preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $normalized) !== 1) {
            throw new InvalidArgumentException("Invalid language code: {$value}");
        }
        $this->value = $normalized;
    }

    public function equals(self $other): bool
    {
        return $other->value === $this->value;
    }
}

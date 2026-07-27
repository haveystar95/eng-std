<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObject;

use Stringable;

/** Base for typed ULID identifiers (TermId, CollectionId, …). */
abstract class Identifier implements Stringable
{
    final public function __construct(public readonly string $value)
    {
        Ulid::assertValid($value);
    }

    public static function generate(): static
    {
        return new static(Ulid::generate());
    }

    public static function fromString(string $value): static
    {
        return new static($value);
    }

    public function equals(self $other): bool
    {
        return $other::class === static::class && $other->value === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

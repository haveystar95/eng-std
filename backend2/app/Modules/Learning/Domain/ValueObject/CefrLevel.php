<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\ValueObject;

/** A CEFR proficiency level, ordered A1 < … < C2, used to compare a term's level to the user's. */
enum CefrLevel: string
{
    case A1 = 'A1';
    case A2 = 'A2';
    case B1 = 'B1';
    case B2 = 'B2';
    case C1 = 'C1';
    case C2 = 'C2';

    public function rank(): int
    {
        return match ($this) {
            self::A1 => 1,
            self::A2 => 2,
            self::B1 => 3,
            self::B2 => 4,
            self::C1 => 5,
            self::C2 => 6,
        };
    }

    public function isHigherThan(self $other): bool
    {
        return $this->rank() > $other->rank();
    }

    /** Parse a stored label (case-insensitive). Unknown/empty → null ("level unknown"). */
    public static function tryFromLabel(?string $label): ?self
    {
        return $label === null ? null : self::tryFrom(strtoupper(trim($label)));
    }
}

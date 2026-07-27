<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObject;

use InvalidArgumentException;

/**
 * ULID string helper — 26-char Crockford base32, time-sortable.
 * Pure PHP so the Domain stays free of framework/uid libraries.
 */
final class Ulid
{
    private const ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    public const LENGTH = 26;

    public static function generate(): string
    {
        $time = (int) (microtime(true) * 1000);

        $chars = '';
        for ($i = 0; $i < 10; $i++) {
            $chars = self::ALPHABET[$time % 32] . $chars;
            $time = intdiv($time, 32);
        }
        for ($i = 0; $i < 16; $i++) {
            $chars .= self::ALPHABET[random_int(0, 31)];
        }

        return $chars;
    }

    public static function isValid(string $value): bool
    {
        return strlen($value) === self::LENGTH
            && strspn($value, self::ALPHABET) === self::LENGTH;
    }

    public static function assertValid(string $value): void
    {
        if (! self::isValid($value)) {
            throw new InvalidArgumentException("Invalid ULID: {$value}");
        }
    }
}

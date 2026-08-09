<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Support;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/** Formats a raw DB timestamp value (string|null) as an ISO-8601 instant, or null. */
final class Iso
{
    public static function orNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable((string) $value))->format(DateTimeInterface::ATOM);
        } catch (Throwable) {
            return null;
        }
    }
}

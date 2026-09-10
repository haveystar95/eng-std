<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Exception;

use RuntimeException;

/** The vendor could not be reached or refused the call — no answer to check. */
final class PlanModelUnavailable extends RuntimeException
{
    public static function because(string $reason): self
    {
        return new self("Модель не ответила: {$reason}");
    }
}

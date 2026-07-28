<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Exception;

use DomainException;

final class GenerationQuotaExceeded extends DomainException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct("Daily generation limit of {$limit} reached.");
    }

    public static function perDay(int $limit): self
    {
        return new self($limit);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

final class GenerationQuotaExceeded extends DomainException implements ProblemDetails
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct("Daily generation limit of {$limit} reached.");
    }

    public static function perDay(int $limit): self
    {
        return new self($limit);
    }

    public function problemStatus(): int
    {
        return 429;
    }

    public function problemCode(): string
    {
        return 'generation_quota_exceeded';
    }

    public function problemTitle(): string
    {
        return 'Daily generation limit reached';
    }

    public function problemMeta(): array
    {
        return ['limit' => $this->limit];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

final class PlanNotFound extends DomainException implements ProblemDetails
{
    private function __construct(private readonly string $planId, string $message)
    {
        parent::__construct($message);
    }

    public static function withId(string $planId): self
    {
        return new self($planId, "План {$planId} не найден.");
    }

    public function problemStatus(): int
    {
        return 404;
    }

    public function problemCode(): string
    {
        return 'plan_not_found';
    }

    public function problemTitle(): string
    {
        return 'Plan not found';
    }

    /** @return array<string, mixed> */
    public function problemMeta(): array
    {
        return ['plan_id' => $this->planId];
    }
}

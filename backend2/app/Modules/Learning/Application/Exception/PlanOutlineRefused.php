<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use RuntimeException;

/**
 * P1 did not produce a usable skeleton — the vendor failed, or the answer did not survive
 * {@see \App\Modules\Generation\Domain\Service\PlanOutlineValidator}.
 *
 * 502 rather than 500: nothing here is broken, an upstream did not answer usefully, and the right
 * button is «попробовать ещё раз». The violations ride in the meta so a defect that repeats can be
 * read off the client's error report rather than out of a log.
 */
final class PlanOutlineRefused extends RuntimeException implements ProblemDetails
{
    /** @param list<string> $violations */
    private function __construct(private readonly array $violations, string $message)
    {
        parent::__construct($message);
    }

    /** @param list<string> $violations */
    public static function invalid(array $violations): self
    {
        return new self(
            $violations,
            'Модель вернула каркас плана, который нельзя использовать: ' . implode('; ', $violations) . '.',
        );
    }

    public static function unavailable(string $reason): self
    {
        return new self([], 'Каркас плана не собрался: ' . $reason);
    }

    public function problemStatus(): int
    {
        return 502;
    }

    public function problemCode(): string
    {
        return 'plan_outline_refused';
    }

    public function problemTitle(): string
    {
        return 'The plan skeleton could not be produced';
    }

    /** @return array<string, mixed> */
    public function problemMeta(): array
    {
        return ['violations' => $this->violations];
    }
}

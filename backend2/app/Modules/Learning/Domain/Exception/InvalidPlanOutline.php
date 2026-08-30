<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/**
 * The stored outline is not an outline.
 *
 * Reachable in one realistic way: a plan whose `outline` column was written by an older prompt
 * version and is being read by newer code. It is a 500-class problem, not a 4xx one — the learner
 * did nothing wrong and can do nothing about it — but it carries a machine code anyway, because
 * the answer to it is «пересобери каркас» and the client needs to be able to offer that button.
 */
final class InvalidPlanOutline extends DomainException implements ProblemDetails
{
    /** @param list<string> $violations */
    private function __construct(private readonly array $violations, string $message)
    {
        parent::__construct($message);
    }

    /** @param list<string> $violations */
    public static function because(array $violations): self
    {
        return new self(
            $violations,
            'Каркас плана не читается: ' . implode('; ', $violations) . '.',
        );
    }

    public function problemStatus(): int
    {
        return 422;
    }

    public function problemCode(): string
    {
        return 'invalid_plan_outline';
    }

    public function problemTitle(): string
    {
        return 'The stored plan outline cannot be read';
    }

    /** @return array<string, mixed> */
    public function problemMeta(): array
    {
        return ['violations' => $this->violations];
    }
}

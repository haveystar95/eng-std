<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/**
 * The plan refuses to write another day ahead of the learner.
 *
 * A 409 and not a silent no-op, because the screen that asked has to be able to say WHY the button
 * did nothing. The ceiling is {@see \App\Modules\Learning\Domain\Service\PlanGenerationPolicy}'s and
 * it exists to stop a plan paying for days nobody reached — the PLAN-1a tail «у плана нет своего
 * лимита трат», answered.
 */
final class PlanDayCapped extends DomainException implements ProblemDetails
{
    private function __construct(
        private readonly string $planId,
        private readonly int $dayIndex,
        private readonly int $cap,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function make(string $planId, int $dayIndex, int $cap): self
    {
        return new self(
            $planId,
            $dayIndex,
            $cap,
            "Впереди уже собрано {$cap} дня — пройди текущий, и следующий соберётся сам.",
        );
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_day_capped';
    }

    public function problemTitle(): string
    {
        return 'Plan day generation is capped';
    }

    /** @return array<string, mixed> */
    public function problemMeta(): array
    {
        return ['plan_id' => $this->planId, 'day_index' => $this->dayIndex, 'cap' => $this->cap];
    }
}

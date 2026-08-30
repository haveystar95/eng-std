<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Exception;

use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/**
 * A second plan started while one is already running.
 *
 * ONE active plan per learner, and the limit is a product decision, not a technical one: a plan is
 * a mechanism with a deadline that holds words in the pool and takes over the day. Two of them
 * fight over the same days and the same pool, and the learner ends up doing neither.
 *
 * The database holds the same rule with a partial unique index, because a check-then-insert is
 * passed by two devices at once.
 */
final class PlanAlreadyActive extends DomainException implements ProblemDetails
{
    private function __construct(private readonly string $activePlanId, string $message)
    {
        parent::__construct($message);
    }

    public static function make(string $activePlanId): self
    {
        return new self(
            $activePlanId,
            'У вас уже есть активный план. Поставьте его на паузу или откажитесь от него, '
            . 'прежде чем начинать новый.',
        );
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'plan_already_active';
    }

    public function problemTitle(): string
    {
        return 'Another plan is already running';
    }

    /** @return array<string, mixed> */
    public function problemMeta(): array
    {
        return ['active_plan_id' => $this->activePlanId];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Exception;

use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;
use App\Modules\Learning\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\Exception\ProblemDetails;
use DomainException;

/**
 * A plan asked to do something its state does not allow — starting a finished plan, generating a
 * day of a draft, pausing an abandoned one.
 *
 * A refusal rather than a no-op, and the reason is the offline queue: the client replays, and a
 * silent no-op would let «начать план» look like it worked twice. The code is stable so the client
 * can tell «уже начат» (harmless, ignore) from «нельзя» (show something).
 */
final class InvalidPlanTransition extends DomainException implements ProblemDetails
{
    private function __construct(
        private readonly string $from,
        private readonly string $action,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function make(PlanStatus $from, string $action): self
    {
        return new self(
            $from->value,
            $action,
            "План в состоянии «{$from->value}»: «{$action}» из него не делается.",
        );
    }

    public static function forDay(PlanDayStatus $from, string $action): self
    {
        return new self(
            $from->value,
            $action,
            "День плана в состоянии «{$from->value}»: «{$action}» из него не делается.",
        );
    }

    public function problemStatus(): int
    {
        return 409;
    }

    public function problemCode(): string
    {
        return 'invalid_plan_transition';
    }

    public function problemTitle(): string
    {
        return 'The plan cannot do that from its current state';
    }

    /** @return array<string, mixed> */
    public function problemMeta(): array
    {
        return ['from' => $this->from, 'action' => $this->action];
    }
}

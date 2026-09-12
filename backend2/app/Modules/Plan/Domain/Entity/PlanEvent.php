<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Entity;

use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanEventId;
use App\Modules\Plan\Domain\ValueObject\PlanEventKind;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * ONE LINE OF THE PLAN'S JOURNAL (`plan_events`) — a fact that happened, written once and never
 * changed. There is no mutator on purpose: the journal is append-only, and a wrong line is
 * answered by a later line, not by an edit.
 *
 * The payload is small and flat (`{from, to}` of a rebuild, the scene of a written day) — the
 * journal says WHAT happened; the plan itself stays the source of HOW it looks now.
 */
final readonly class PlanEvent
{
    /** @param array<string, int|string|null> $payload */
    private function __construct(
        public PlanEventId $id,
        public UserId $userId,
        public PlanId $planId,
        public ?PlanDayId $dayId,
        public ?int $dayNumber,
        public PlanEventKind $kind,
        public array $payload,
        public DateTimeImmutable $occurredAt,
    ) {}

    /** @param array<string, int|string|null> $payload */
    public static function record(
        PlanEventId $id,
        UserId $userId,
        PlanId $planId,
        PlanEventKind $kind,
        DateTimeImmutable $occurredAt,
        ?PlanDayId $dayId = null,
        ?int $dayNumber = null,
        array $payload = [],
    ): self {
        if ($kind->isAboutADay() && ($dayNumber === null || $dayNumber < 1)) {
            throw new InvalidArgumentException("A {$kind->value} event names its day.");
        }
        if ($kind === PlanEventKind::DaysSkippedRebuilt) {
            $from = $payload['from'] ?? null;
            $to = $payload['to'] ?? null;
            if (! is_int($from) || ! is_int($to) || $to >= $from) {
                throw new InvalidArgumentException('A rebuild event carries {from, to} with to < from.');
            }
        }

        return new self($id, $userId, $planId, $dayId, $dayNumber, $kind, $payload, $occurredAt);
    }

    /** @param array<string, int|string|null> $payload */
    public static function reconstitute(
        PlanEventId $id,
        UserId $userId,
        PlanId $planId,
        ?PlanDayId $dayId,
        ?int $dayNumber,
        PlanEventKind $kind,
        array $payload,
        DateTimeImmutable $occurredAt,
    ): self {
        return new self($id, $userId, $planId, $dayId, $dayNumber, $kind, $payload, $occurredAt);
    }
}

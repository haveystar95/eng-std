<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Entity;

use App\Modules\Plan\Domain\Exception\CardAlreadyAnswered;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\CardSource;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use DateTimeImmutable;

/**
 * One card of a day. The payload is everything the card needs to be shown and graded, with no
 * reference to the model; the unit (word / phrase / exchange, by reference) is what fails, comes
 * back to the end of the stage and, on a second failure, returns on the next content day.
 */
final class DayCard
{
    /** @param array<string, mixed> $payload */
    private function __construct(
        private readonly DayCardId $id,
        private readonly PlanDayId $dayId,
        private readonly Stage $stage,
        private readonly int $position,
        private readonly CardKind $kind,
        private readonly array $payload,
        private readonly CardSource $source,
        private readonly ?PlanDayId $sourceDayId,
        private readonly UnitKind $unitKind,
        private readonly string $unitRef,
        private readonly ?DayCardId $retryOf,
        private ?CardResult $result,
        private int $attempts,
        private ?DateTimeImmutable $answeredAt,
        private bool $returns,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function dealt(
        DayCardId $id,
        PlanDayId $dayId,
        Stage $stage,
        int $position,
        CardKind $kind,
        array $payload,
        CardSource $source,
        ?PlanDayId $sourceDayId,
        UnitKind $unitKind,
        string $unitRef,
    ): self {
        return new self($id, $dayId, $stage, $position, $kind, $payload, $source, $sourceDayId, $unitKind, $unitRef, null, null, 0, null, false);
    }

    /** @param array<string, mixed> $payload */
    public static function reconstitute(
        DayCardId $id,
        PlanDayId $dayId,
        Stage $stage,
        int $position,
        CardKind $kind,
        array $payload,
        CardSource $source,
        ?PlanDayId $sourceDayId,
        UnitKind $unitKind,
        string $unitRef,
        ?DayCardId $retryOf,
        ?CardResult $result,
        int $attempts,
        ?DateTimeImmutable $answeredAt,
        bool $returns,
    ): self {
        return new self($id, $dayId, $stage, $position, $kind, $payload, $source, $sourceDayId, $unitKind, $unitRef, $retryOf, $result, $attempts, $answeredAt, $returns);
    }

    /**
     * Record the answer. Returns true when the card must be dealt AGAIN at the end of its stage —
     * the first failure of a unit; the second failure stays and marks the unit to return tomorrow.
     * A skip is a failure without either consequence.
     */
    public function answer(CardResult $result, int $attempts, DateTimeImmutable $now): bool
    {
        if ($this->result !== null) {
            throw CardAlreadyAnswered::withId($this->id);
        }
        $this->result = $result;
        $this->attempts = max(1, $attempts);
        $this->answeredAt = $now;

        if ($result !== CardResult::Failed) {
            return false;
        }
        if ($this->retryOf === null) {
            return true;
        }
        $this->returns = true;

        return false;
    }

    /** The same card again, at the end of the stage. */
    public function retry(DayCardId $id, int $position): self
    {
        return new self(
            $id, $this->dayId, $this->stage, $position, $this->kind, $this->payload, $this->source,
            $this->sourceDayId, $this->unitKind, $this->unitRef, $this->id, null, 0, null, false,
        );
    }

    public function isAnswered(): bool
    {
        return $this->result !== null;
    }

    public function isGraded(): bool
    {
        return $this->kind->isGraded();
    }

    public function id(): DayCardId
    {
        return $this->id;
    }

    public function dayId(): PlanDayId
    {
        return $this->dayId;
    }

    public function stage(): Stage
    {
        return $this->stage;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function kind(): CardKind
    {
        return $this->kind;
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->payload;
    }

    public function source(): CardSource
    {
        return $this->source;
    }

    public function sourceDayId(): ?PlanDayId
    {
        return $this->sourceDayId;
    }

    public function unitKind(): UnitKind
    {
        return $this->unitKind;
    }

    public function unitRef(): string
    {
        return $this->unitRef;
    }

    public function retryOf(): ?DayCardId
    {
        return $this->retryOf;
    }

    public function result(): ?CardResult
    {
        return $this->result;
    }

    public function attempts(): int
    {
        return $this->attempts;
    }

    public function answeredAt(): ?DateTimeImmutable
    {
        return $this->answeredAt;
    }

    public function returns(): bool
    {
        return $this->returns;
    }
}

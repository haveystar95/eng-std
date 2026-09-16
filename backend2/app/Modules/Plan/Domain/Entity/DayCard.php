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
 * reference to the model; the unit (word / phrase / exchange / the day's listening, by reference) is
 * what fails, comes back to the end of the stage and, on a second failure, returns on the next content
 * day. The response is what the learner's answer left behind — what was heard, the slot's value, the
 * judge's verdict (наряд SESSION-1a).
 */
final class DayCard
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $response
     */
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
        private ?array $response,
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
        return new self($id, $dayId, $stage, $position, $kind, $payload, $source, $sourceDayId, $unitKind, $unitRef, null, null, 0, null, false, null);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>|null  $response
     */
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
        ?array $response = null,
    ): self {
        return new self($id, $dayId, $stage, $position, $kind, $payload, $source, $sourceDayId, $unitKind, $unitRef, $retryOf, $result, $attempts, $answeredAt, $returns, $response);
    }

    /**
     * Record the answer the client wrote, with what it heard or chose (`response`, наряд SESSION-1a, разд. 3).
     * Returns true when the card must be dealt AGAIN at the end of its stage.
     *
     * Only a choice has consequences, because only a choice is evidence of a lapse: its first failure comes back at
     * the end of the stage, the failure of that copy marks the unit to return on the next content day — unless the
     * unit is the day's listening, which has no next day to return to. A spoken card's skip, a walkthrough, a judged
     * card given up on: an answer, and nothing more.
     *
     * @param  array<string, mixed>|null  $response
     */
    public function answer(CardResult $result, int $attempts, ?array $response, DateTimeImmutable $now): bool
    {
        if ($this->result !== null) {
            throw CardAlreadyAnswered::withId($this->id);
        }
        $this->result = $result;
        $this->attempts = max(1, $attempts);
        $this->answeredAt = $now;
        $this->response = $response;

        if (! $this->kind->isChoice() || $result !== CardResult::Failed) {
            return false;
        }
        if ($this->retryOf === null) {
            return true;
        }
        $this->returns = $this->unitKind->returns();

        return false;
    }

    /**
     * The judge's verdict on one attempt of a card judged by meaning (`…/judge`, наряд SESSION-1a, разд. 4). Every
     * attempt counts and leaves what was heard and ruled; an accepted one answers the card — `hinted` when the frame
     * was shown before the pass — and a rejected one leaves it open for another attempt or a skip.
     *
     * @param  array<string, mixed>  $response
     */
    public function judge(bool $accepted, bool $hinted, array $response, DateTimeImmutable $now): void
    {
        if ($this->result !== null) {
            throw CardAlreadyAnswered::withId($this->id);
        }
        $this->attempts++;
        $this->response = $response;
        if ($accepted) {
            $this->result = $hinted ? CardResult::Hinted : CardResult::Passed;
            $this->answeredAt = $now;
        }
    }

    /**
     * The same card again, at the end of the stage — with its options and tiles in another order
     * ({@see \App\Modules\Plan\Domain\Assembly\Retry}), so the second try is not the first one's position remembered.
     *
     * @param  array<string, mixed>  $payload
     */
    public function retry(DayCardId $id, int $position, array $payload): self
    {
        return new self(
            $id, $this->dayId, $this->stage, $position, $this->kind, $payload, $this->source,
            $this->sourceDayId, $this->unitKind, $this->unitRef, $this->id, null, 0, null, false, null,
        );
    }

    public function isAnswered(): bool
    {
        return $this->result !== null;
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

    /**
     * What the client heard, chose or was judged on — `heard`, `slot_value`, `hinted_at`, the judge's verdict; null
     * for a card not answered or answered with nothing to keep.
     *
     * @return array<string, mixed>|null
     */
    public function response(): ?array
    {
        return $this->response;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Assembly;

use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardSource;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\UnitKind;

/** A card before it has an id and a position — what the stage builders produce. */
final readonly class CardDraft
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public CardKind $kind,
        public UnitKind $unitKind,
        public string $unitRef,
        public array $payload,
        public CardSource $source = CardSource::Today,
        public ?PlanDayId $sourceDayId = null,
    ) {}

    public function returned(PlanDayId $sourceDayId): self
    {
        return new self($this->kind, $this->unitKind, $this->unitRef, $this->payload, CardSource::Returned, $sourceDayId);
    }
}

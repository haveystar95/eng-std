<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

final readonly class DayMetricsView
{
    public function __construct(
        public int $cardsTotal,
        public int $cardsDone,
        public int $minutesSpent,
        public ?float $firstTryShare,
        public ?string $hardestUnitKind,
        public ?string $hardestUnitRef,
        public ?string $hardestUnitText,
    ) {}
}

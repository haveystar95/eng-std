<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** A plan in the list — enough for a row, never the route. */
final readonly class PlanSummaryView
{
    public function __construct(
        public string $id,
        public string $status,
        public ?string $titleNative,
        public string $goalText,
        public ?string $eventDate,
        public int $daysTotal,
        public ?string $collectionId,
        public ?string $startedAt,
        public ?string $finishedAt,
        public string $createdAt,
    ) {}
}

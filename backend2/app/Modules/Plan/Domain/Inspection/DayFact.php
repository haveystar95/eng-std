<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Inspection;

/**
 * What a check needs to know of one day. `scheduledOpen` — the calendar has come for it and the day before is closed;
 * `nextInLine` — it is the first day not closed. Money: `generationUsd` — the scene's lesson with every attempt, repair and
 * seam judge (null for a day without a scene of its own); `voiceUsd` — its scene's bought lines; `repairUsd` — the
 * repairs alone, null when the journal cannot say them exactly.
 */
final readonly class DayFact
{
    public function __construct(
        public int $number,
        public string $type,
        public string $status,
        public bool $scheduledOpen,
        public bool $nextInLine,
        public ?string $lessonStatus,
        public ?string $failReason,
        public bool $closed,
        public bool $hasPassedEvent,
        public ?float $generationUsd,
        public float $voiceUsd,
        public ?float $repairUsd,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * A plan as a LIST ROW — «Аренда квартиры · июль · 4 дня» (кадр 1c · 11, «Архив»).
 *
 * Deliberately not a {@see PlanView}: the archive shows a handful of finished preparations, and a
 * full read of each one would run {@see \App\Modules\Learning\Application\Service\PlanProgress} —
 * a collection read, a content read and a review-log read PER DAY — for a row that says three
 * words. Readiness is not here for the same reason, and its absence is honest: an archived plan's
 * story is what happened at the event, which is `event_feedback`, not a live derivation.
 */
final readonly class PlanSummaryView
{
    public function __construct(
        public string $id,
        public string $status,
        public string $title,
        public string $targetLang,
        public ?string $eventDate,
        public int $dayCount,
        public ?string $startedAt,
        public ?string $completedAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'title' => $this->title,
            'target_lang' => $this->targetLang,
            'event_date' => $this->eventDate,
            'day_count' => $this->dayCount,
            'started_at' => $this->startedAt,
            'completed_at' => $this->completedAt,
        ];
    }
}

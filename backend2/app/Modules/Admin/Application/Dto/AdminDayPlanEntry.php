<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** One simulated card in a user's day plan (mirror of Learning's DayPlanEntryView, admin-owned). */
final readonly class AdminDayPlanEntry
{
    public function __construct(
        public string $termId,
        public string $text,
        public ?string $translation,
        public string $type,
        public string $state,
        public int $reps,
        public int $intervalDays,
        public ?string $dueAt,
        public string $exerciseMode,
        public bool $clozeable,
        public bool $isNew,
    ) {}
}

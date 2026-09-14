<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** The plate of the day window: which day, its scene and photo, one word for where it stands, its minutes and goals. */
final readonly class WindowDayView
{
    /** @param list<WindowGoalView> $goals */
    public function __construct(
        public int $index,
        public string $type,
        public ?SceneView $scene,
        public string $imageTone,
        /** `not_started` | `in_progress` | `passed` — or `locked`, which the window refuses */
        public string $status,
        public ?int $minutesEstimate,
        public ?int $minutesSpent,
        public array $goals,
    ) {}
}

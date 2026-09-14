<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * «Кабинет дня»: the day as the tab's plate and the session read it — header, stages with their
 * counts, the day's numbers, the programme's states — and, since DAY-UI-2, the day window's own
 * reading of the same cards (`window`).
 */
final readonly class DayRoomView
{
    /**
     * @param  list<StageProgressView>  $stages
     * @param  list<ProgramUnitView>  $program
     */
    public function __construct(
        public string $planId,
        public DayRouteView $day,
        public ?SceneView $scene,
        public array $stages,
        public ?DayMetricsView $metrics,
        public array $program,
        public DayWindowView $window,
    ) {}
}

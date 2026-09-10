<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** «Кабинет дня»: header, goals, the stages with progress, the statistics and the program. */
final readonly class DayRoomView
{
    /**
     * @param  list<string>  $goalsNative
     * @param  list<StageProgressView>  $stages
     * @param  list<ProgramUnitView>  $program
     */
    public function __construct(
        public string $planId,
        public DayRouteView $day,
        public ?SceneView $scene,
        public array $goalsNative,
        public array $stages,
        public ?DayMetricsView $metrics,
        public array $program,
        public bool $sheetAvailable,
    ) {}
}

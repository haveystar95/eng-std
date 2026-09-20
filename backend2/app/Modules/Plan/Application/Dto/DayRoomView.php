<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

use App\Modules\Shared\Domain\ValueObject\SpeechPack;

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
        public SpeechPack $speech = new SpeechPack,
        public int $repeatMisses = 0,
    ) {}

    /**
     * WHAT A COMPARISON OF SPEECH MAY READ OF THE TARGET LANGUAGE (наряд FIX-2, п. 2) — the pack's own lists and the
     * loosening handle, ONCE for the whole day.
     *
     * They ride with the day and not with each card because they belong to the plan's target language, not to a
     * trainer: the phone judges a spoken attempt by the same lists the server does, instead of keeping its own copy
     * of English in Dart.
     *
     * @return array<string, mixed>
     */
    public function speechRules(): array
    {
        return [...$this->speech->toArray(), 'repeat_misses' => $this->repeatMisses];
    }
}

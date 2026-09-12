<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** One day on the route: what it is, whether it may be walked, and how it went if it was. */
final readonly class DayRouteView
{
    /**
     * @param  list<RouteStageView>  $stages  only the stages the day has, in walking order
     */
    public function __construct(
        public string $id,
        public int $number,
        public string $type,
        /** `locked` | `open` | `in_progress` | `closed` — effective for today, not the stored value */
        public string $status,
        public ?string $sceneId,
        public ?string $titleNative,
        public ?string $titleTarget,
        public ?string $teachesNative,
        public ?string $lessonStatus,
        public ?string $opensOn,
        public DaySlotView $slot,
        public int $cardsTotal,
        public int $cardsDone,
        public int $minutesSpent,
        public ?string $openedAt,
        public ?string $closedAt,
        public array $stages = [],
    ) {}
}

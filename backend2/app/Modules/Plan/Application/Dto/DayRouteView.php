<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/** One day on the route: what it is, whether it may be walked, and how it went if it was. */
final readonly class DayRouteView
{
    public const BUILDING = 'building';

    /**
     * @param  list<RouteStageView>  $stages  only the stages the day has, in walking order
     */
    public function __construct(
        public string $id,
        public int $number,
        public string $type,
        /**
         * `locked` | `building` | `open` | `in_progress` | `closed` — effective for today, not the stored value; `building` — the
         * day is next in line and its lesson is still being written (наряд GEN-3 §11)
         */
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
        /**
         * WHY a `locked` day is locked (наряд ACC-1 §2): `date` — its calendar day, the day before it, the plan not
         * started — or `subscription` — the paywall; null for a day that is not locked
         */
        public ?string $lockReason = null,
    ) {}
}

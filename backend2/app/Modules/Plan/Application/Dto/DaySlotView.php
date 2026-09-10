<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Dto;

/**
 * WHEN a day sits in the learner's calendar: «сегодня» / «завтра» as ready words, a date the
 * client formats, or nothing yet for a day whose date depends on days still open.
 */
final readonly class DaySlotView
{
    public const TODAY = 'today';

    public const TOMORROW = 'tomorrow';

    public const DATE = 'date';

    public const PAST = 'past';

    public const UNSCHEDULED = 'unscheduled';

    public function __construct(
        public string $code,
        public ?string $date,
        public ?string $labelNative,
    ) {}
}

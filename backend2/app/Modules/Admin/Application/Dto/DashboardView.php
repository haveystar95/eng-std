<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/** The admin landing screen: fleet totals plus AI spend over today / last 7 days / all time. */
final readonly class DashboardView
{
    public function __construct(
        public int $users,
        public int $collections,
        public int $terms,
        public int $reviewsToday,
        public int $reviews7d,
        public CostBreakdown $costToday,
        public CostBreakdown $cost7d,
        public CostBreakdown $costAllTime,
    ) {}
}

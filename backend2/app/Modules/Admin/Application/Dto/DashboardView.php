<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Dto;

/**
 * The admin landing screen: fleet totals, AI spend over today / last 7 days / all time, and the
 * last few outbound calls that FAILED — the one thing you want on a landing page that you would
 * otherwise only find by going looking for it.
 */
final readonly class DashboardView
{
    /** @param list<RequestLogRow> $recentFailures */
    public function __construct(
        public int $users,
        public int $collections,
        public int $terms,
        public int $activeUsers7d,
        public int $reviewsToday,
        public int $reviews7d,
        public CostBreakdown $costToday,
        public CostBreakdown $cost7d,
        public CostBreakdown $costAllTime,
        public array $recentFailures = [],
    ) {}
}

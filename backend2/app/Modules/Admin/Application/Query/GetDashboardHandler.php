<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use App\Modules\Admin\Application\Dto\DashboardView;
use App\Modules\Admin\Application\Port\AdminCostReader;
use App\Modules\Admin\Application\Port\AdminMetricsReader;
use App\Modules\Shared\Domain\Service\Clock;
use DateTimeZone;

/** Composes fleet counts and spend windows into the dashboard view. Read-only. */
final readonly class GetDashboardHandler
{
    public function __construct(
        private AdminMetricsReader $metrics,
        private AdminCostReader $costs,
        private Clock $clock,
    ) {}

    public function __invoke(): DashboardView
    {
        $now = $this->clock->now();
        $startOfToday = $now->setTimezone(new DateTimeZone('UTC'))->setTime(0, 0, 0);
        $weekAgo = $now->modify('-7 days');

        return new DashboardView(
            users: $this->metrics->userCount(),
            collections: $this->metrics->collectionCount(),
            terms: $this->metrics->termCount(),
            reviewsToday: $this->metrics->reviewsSince($startOfToday),
            reviews7d: $this->metrics->reviewsSince($weekAgo),
            costToday: $this->costs->breakdownSince($startOfToday),
            cost7d: $this->costs->breakdownSince($weekAgo),
            costAllTime: $this->costs->breakdownSince(null),
        );
    }
}

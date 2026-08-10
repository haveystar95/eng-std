<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Learning\Application\Dto\StatsView;
use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Application\Port\StatsReader;

final readonly class GetUserStatsHandler
{
    public function __construct(
        private StatsReader $stats,
        private LearnerProfileReader $profile,
    ) {}

    public function __invoke(GetUserStats $query): StatsView
    {
        // Activity is computed in the user's calendar day (device-batch F19/F18): an evening review
        // belongs to that local day, and the streak/day-lit follow the user's zone, not UTC.
        return $this->stats->read($query->userId, $query->now, $this->profile->timezoneFor($query->userId));
    }
}

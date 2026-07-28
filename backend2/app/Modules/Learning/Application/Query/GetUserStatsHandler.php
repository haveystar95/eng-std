<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Learning\Application\Dto\StatsView;
use App\Modules\Learning\Application\Port\StatsReader;

final readonly class GetUserStatsHandler
{
    public function __construct(private StatsReader $stats) {}

    public function __invoke(GetUserStats $query): StatsView
    {
        return $this->stats->read($query->userId, $query->now);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Application\Port\StatsProjector;
use App\Modules\Learning\Domain\Event\ReviewsSubmitted;

final class SpyStatsProjector implements StatsProjector
{
    /** @var list<ReviewsSubmitted> */
    public array $events = [];

    public function project(ReviewsSubmitted $event): void
    {
        $this->events[] = $event;
    }
}

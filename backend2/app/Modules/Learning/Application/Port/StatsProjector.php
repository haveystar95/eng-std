<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Port;

use App\Modules\Learning\Domain\Event\ReviewsSubmitted;

/**
 * Folds accepted reviews into the daily statistics read model. Implementations may run
 * synchronously or on a queue; either way the result must be reproducible by replaying
 * the reviews log.
 */
interface StatsProjector
{
    public function project(ReviewsSubmitted $event): void;
}

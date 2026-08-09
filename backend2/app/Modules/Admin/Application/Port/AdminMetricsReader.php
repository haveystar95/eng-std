<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Port;

use DateTimeImmutable;

/** Fleet-wide counts for the dashboard. */
interface AdminMetricsReader
{
    public function userCount(): int;

    public function collectionCount(): int;

    public function termCount(): int;

    /** Reviews (real answers, incl. practice) with answered_at at/after $since. */
    public function reviewsSince(DateTimeImmutable $since): int;
}

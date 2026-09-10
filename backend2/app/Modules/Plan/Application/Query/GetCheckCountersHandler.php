<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Application\Dto\CheckCounterRow;
use App\Modules\Plan\Application\Port\CheckCounters;

final readonly class GetCheckCountersHandler
{
    public function __construct(private CheckCounters $counters) {}

    /** @return list<CheckCounterRow> */
    public function __invoke(GetCheckCounters $query): array
    {
        return $this->counters->all();
    }
}

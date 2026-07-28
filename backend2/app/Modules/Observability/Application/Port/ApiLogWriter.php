<?php

declare(strict_types=1);

namespace App\Modules\Observability\Application\Port;

use App\Modules\Observability\Application\Dto\ApiLogEntry;

/** Sink for recorded HTTP calls. Implementations must never throw into the caller. */
interface ApiLogWriter
{
    public function write(ApiLogEntry $entry): void;
}

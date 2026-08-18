<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\LoggedResponseReader;
use App\Modules\Observability\Application\Port\ApiRequestLogReader;

final readonly class ObservabilityLoggedResponseReader implements LoggedResponseReader
{
    public function __construct(private ApiRequestLogReader $logs) {}

    public function findResponseBody(string $logId): ?array
    {
        return $this->logs->findResponseBody($logId);
    }
}

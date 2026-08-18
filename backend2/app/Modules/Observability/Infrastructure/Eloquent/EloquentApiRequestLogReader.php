<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Eloquent;

use App\Modules\Observability\Application\Port\ApiRequestLogReader;

final class EloquentApiRequestLogReader implements ApiRequestLogReader
{
    public function findResponseBody(string $logId): ?array
    {
        $model = ApiRequestLogModel::query()->find($logId);

        return $model?->response_body;
    }
}

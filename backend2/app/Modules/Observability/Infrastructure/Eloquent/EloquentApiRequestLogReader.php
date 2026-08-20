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

    public function promptCacheByModel(string $purpose, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $rows = ApiRequestLogModel::query()
            ->where('direction', 'outbound')
            ->where('purpose', $purpose)
            ->whereBetween('occurred_at', [$from, $to])
            ->get(['response_body']);

        $out = [];
        foreach ($rows as $row) {
            $body = $row->response_body;
            if (! is_array($body)) {
                continue;
            }
            $usage = is_array($body['usage'] ?? null) ? $body['usage'] : [];
            $promptTokens = $usage['prompt_tokens'] ?? null;
            if (! is_int($promptTokens)) {
                continue;
            }
            // Absent means "this vendor did not say", which is counted as zero cached rather than
            // skipped: a call that reported no cache still consumed its prompt at full price.
            $details = is_array($usage['prompt_tokens_details'] ?? null) ? $usage['prompt_tokens_details'] : [];
            $cached = is_int($details['cached_tokens'] ?? null) ? $details['cached_tokens'] : 0;
            $model = is_string($body['model'] ?? null) ? $body['model'] : 'unknown';

            $out[$model] ??= ['calls' => 0, 'prompt_tokens' => 0, 'cached_tokens' => 0];
            $out[$model]['calls']++;
            $out[$model]['prompt_tokens'] += $promptTokens;
            $out[$model]['cached_tokens'] += $cached;
        }

        return $out;
    }
}

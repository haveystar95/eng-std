<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\PlaygroundAnswer;
use App\Modules\Generation\Application\Dto\PlaygroundRun;
use App\Modules\Generation\Application\Port\PlaygroundRunStore;
use Illuminate\Contracts\Cache\Repository;

/**
 * Sandbox runs in the application cache, for a day. A run is read by the screen that started it within minutes; nothing of
 * the catalogue is written, and the money of the call is in the journal of model calls, not here.
 */
final readonly class CachePlaygroundRunStore implements PlaygroundRunStore
{
    private const TTL_SECONDS = 86_400;

    public function __construct(private Repository $cache) {}

    public function save(PlaygroundRun $run): void
    {
        $this->cache->put(self::key($run->id), [
            'id' => $run->id,
            'status' => $run->status,
            'provider' => $run->provider,
            'model' => $run->model,
            'answer' => $run->answer === null ? null : [
                'provider' => $run->answer->provider,
                'model' => $run->answer->model,
                'raw_text' => $run->answer->rawText,
                'parsed_json' => $run->answer->parsedJson,
                'parse_error' => $run->answer->parseError,
                'tokens_in' => $run->answer->tokensIn,
                'tokens_out' => $run->answer->tokensOut,
                'cost_usd' => $run->answer->costUsd,
                'latency_ms' => $run->answer->latencyMs,
                'error' => $run->answer->error,
            ],
        ], self::TTL_SECONDS);
    }

    public function find(string $id): ?PlaygroundRun
    {
        $row = $this->cache->get(self::key($id));
        if (! is_array($row)) {
            return null;
        }
        $a = is_array($row['answer'] ?? null) ? $row['answer'] : null;

        return new PlaygroundRun(
            (string) $row['id'],
            (string) $row['status'],
            (string) $row['provider'],
            (string) $row['model'],
            $a === null ? null : new PlaygroundAnswer(
                provider: (string) $a['provider'],
                model: (string) $a['model'],
                rawText: (string) $a['raw_text'],
                parsedJson: is_array($a['parsed_json']) ? $a['parsed_json'] : null,
                parseError: is_string($a['parse_error']) ? $a['parse_error'] : null,
                tokensIn: is_int($a['tokens_in']) ? $a['tokens_in'] : null,
                tokensOut: is_int($a['tokens_out']) ? $a['tokens_out'] : null,
                costUsd: is_string($a['cost_usd']) ? $a['cost_usd'] : null,
                latencyMs: (int) $a['latency_ms'],
                error: is_string($a['error']) ? $a['error'] : null,
            ),
        );
    }

    private static function key(string $id): string
    {
        return "playground-run:{$id}";
    }
}

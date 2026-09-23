<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Eloquent;

use App\Modules\Observability\Application\Dto\InboundCallRecord;
use App\Modules\Observability\Application\Dto\ModelCallRecord;
use App\Modules\Observability\Application\Dto\SpeechCallRecord;
use App\Modules\Observability\Application\Port\CallLogReader;
use DateTimeImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The journal and the request log read back for one plan's page (наряд ADM-1). Every query is bounded by a time window or a
 * path prefix, and each has its index: `model_calls_started_idx`, `api_request_logs_direction_idx`, and the inbound path
 * index `api_request_logs_inbound_path_idx` (migration `2026_09_23_140000`).
 */
final class EloquentCallLogReader implements CallLogReader
{
    /** How long after a call's finish its outbound log row may be written — the listener runs after the response. */
    private const LOG_LAG_SECONDS = 5;

    private const TTS_PATH = '/v1/text-to-speech/';

    public function modelCalls(DateTimeImmutable $from, DateTimeImmutable $to, array $purposes): array
    {
        if ($purposes === []) {
            return [];
        }
        $rows = DB::table('model_calls')
            ->whereBetween('started_at', [$from->format(DATE_ATOM), $to->format(DATE_ATOM)])
            ->whereIn('purpose', $purposes)
            ->orderBy('started_at')
            ->orderBy('id')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $r = (array) $row;
            $out[] = new ModelCallRecord(
                id: (string) $r['id'],
                status: (string) $r['status'],
                provider: (string) $r['provider'],
                model: (string) $r['model'],
                answeredModel: self::str($r['answered_model']),
                purpose: self::str($r['purpose']),
                estimatedTokensIn: (int) $r['estimated_tokens_in'],
                tokensIn: self::int($r['tokens_in']),
                cachedTokens: self::int($r['cached_tokens']),
                tokensOut: self::int($r['tokens_out']),
                costUsd: self::str($r['cost_usd']),
                httpStatus: self::int($r['http_status']),
                error: self::str($r['error']),
                timeoutSeconds: (int) $r['timeout_seconds'],
                latencyMs: self::int($r['latency_ms']),
                startedAt: new DateTimeImmutable((string) $r['started_at']),
                finishedAt: $r['finished_at'] === null ? null : new DateTimeImmutable((string) $r['finished_at']),
            );
        }

        return $out;
    }

    public function withLogIds(array $calls): array
    {
        $answered = array_values(array_filter($calls, static fn (ModelCallRecord $c): bool => $c->tokensIn !== null && $c->tokensOut !== null && $c->finishedAt !== null));
        if ($answered === []) {
            return $calls;
        }
        $from = min(array_map(static fn (ModelCallRecord $c): int => $c->startedAt->getTimestamp(), $answered));
        $to = max(array_map(static fn (ModelCallRecord $c): int => (int) $c->finishedAt?->getTimestamp(), $answered)) + self::LOG_LAG_SECONDS;

        // One read of the window's outbound rows that carry a usage block — the plan's calls are minutes apart, so this
        // is a handful of rows, never the log.
        $rows = DB::table('api_request_logs')
            ->where('direction', 'outbound')
            ->whereBetween('occurred_at', [date(DATE_ATOM, $from), date(DATE_ATOM, $to)])
            ->whereNotNull('response_body')
            ->select(['id', 'occurred_at'])
            ->selectRaw("COALESCE(response_body->'usage'->>'prompt_tokens', response_body->'usage'->>'input_tokens') AS tin")
            ->selectRaw("COALESCE(response_body->'usage'->>'completion_tokens', response_body->'usage'->>'output_tokens') AS tout")
            ->get();

        $candidates = [];
        foreach ($rows as $row) {
            $r = (array) $row;
            if ($r['tin'] === null || $r['tout'] === null) {
                continue;
            }
            $candidates[] = ['id' => (string) $r['id'], 'at' => (new DateTimeImmutable((string) $r['occurred_at']))->getTimestamp(), 'in' => (int) $r['tin'], 'out' => (int) $r['tout']];
        }

        return array_map(static function (ModelCallRecord $call) use ($candidates): ModelCallRecord {
            if ($call->tokensIn === null || $call->tokensOut === null || $call->finishedAt === null) {
                return $call;
            }
            $start = $call->startedAt->getTimestamp();
            $end = $call->finishedAt->getTimestamp() + self::LOG_LAG_SECONDS;
            $hits = array_values(array_filter($candidates, static fn (array $c): bool => $c['in'] === $call->tokensIn
                && $c['out'] === $call->tokensOut && $c['at'] >= $start && $c['at'] <= $end));

            return $call->withLogId(count($hits) === 1 ? $hits[0]['id'] : null);
        }, $calls);
    }

    public function speechCalls(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rows = DB::table('api_request_logs')
            ->where('direction', 'outbound')
            ->whereBetween('occurred_at', [$from->format(DATE_ATOM), $to->format(DATE_ATOM)])
            ->where('path', 'like', self::TTS_PATH.'%')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->select(['id', 'path', 'status', 'occurred_at'])
            ->selectRaw("request_body->>'text' AS text")
            ->selectRaw("response_body->>'bytes' AS audio_bytes")
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $r = (array) $row;
            $out[] = new SpeechCallRecord(
                id: (string) $r['id'],
                voiceId: substr((string) $r['path'], strlen(self::TTS_PATH)),
                text: self::str($r['text']),
                status: self::int($r['status']),
                audioBytes: self::int($r['audio_bytes']),
                occurredAt: new DateTimeImmutable((string) $r['occurred_at']),
            );
        }

        return $out;
    }

    public function inbound(array $pathPrefixes, array $exactPaths, ?array $before, int $limit): array
    {
        if ($pathPrefixes === [] && $exactPaths === []) {
            return [];
        }
        $query = $this->inboundQuery($pathPrefixes, $exactPaths)
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->select(['id', 'method', 'path', 'status', 'duration_ms', 'request_bytes', 'response_bytes', 'error', 'occurred_at'])
            ->selectRaw("request_headers->'user-agent'->>0 AS user_agent");
        if ($before !== null) {
            $query->whereRaw('(occurred_at, id) < (?, ?)', [$before[0]->format(DATE_ATOM), $before[1]]);
        }

        $out = [];
        foreach ($query->get() as $row) {
            $r = (array) $row;
            $out[] = new InboundCallRecord(
                id: (string) $r['id'],
                method: (string) $r['method'],
                path: (string) $r['path'],
                status: self::int($r['status']),
                durationMs: self::int($r['duration_ms']),
                requestBytes: self::int($r['request_bytes']),
                responseBytes: self::int($r['response_bytes']),
                userAgent: self::str($r['user_agent']),
                error: self::str($r['error']),
                occurredAt: new DateTimeImmutable((string) $r['occurred_at']),
            );
        }

        return $out;
    }

    public function inboundCount(array $pathPrefixes, array $exactPaths): int
    {
        return $pathPrefixes === [] && $exactPaths === [] ? 0 : $this->inboundQuery($pathPrefixes, $exactPaths)->count();
    }

    public function firstServed(array $paths): array
    {
        if ($paths === []) {
            return [];
        }
        $rows = DB::table('api_request_logs')
            ->where('direction', 'inbound')
            ->where('method', 'GET')
            ->whereIn('path', $paths)
            ->where('status', 200)
            ->groupBy('path')
            ->selectRaw('path, MIN(occurred_at) AS first_at')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $r = (array) $row;
            $out[(string) $r['path']] = new DateTimeImmutable((string) $r['first_at']);
        }

        return $out;
    }

    /**
     * @param  list<string>  $pathPrefixes
     * @param  list<string>  $exactPaths
     */
    private function inboundQuery(array $pathPrefixes, array $exactPaths): Builder
    {
        return DB::table('api_request_logs')
            ->where('direction', 'inbound')
            ->where(static function (Builder $q) use ($pathPrefixes, $exactPaths): void {
                foreach ($pathPrefixes as $prefix) {
                    $q->orWhere('path', 'like', self::escapeLike($prefix).'%');
                }
                if ($exactPaths !== []) {
                    $q->orWhereIn('path', $exactPaths);
                }
            });
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private static function str(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }

    private static function int(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}

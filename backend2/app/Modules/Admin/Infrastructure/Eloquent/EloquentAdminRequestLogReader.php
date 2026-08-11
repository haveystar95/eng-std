<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Dto\ListWindow;
use App\Modules\Admin\Application\Dto\LogFilters;
use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Dto\RequestLogDetail;
use App\Modules\Admin\Application\Dto\RequestLogRow;
use App\Modules\Admin\Application\Port\AdminRequestLogReader;
use App\Modules\Admin\Infrastructure\Support\Iso;
use App\Modules\Admin\Infrastructure\Support\Keyset;
use App\Modules\Shared\Domain\Service\ModelCost;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Projection over Observability's api_request_logs.
 *
 * Model and token counts are read straight out of the stored JSON rather than from columns of their
 * own — the usage block is part of the response we already logged, and deriving it means the whole
 * history is priced, not just calls made after this feature shipped. Cost then comes from the one
 * shared pricing table, so a call's price here and the same call's price in a spend ledger cannot
 * drift apart.
 */
final class EloquentAdminRequestLogReader implements AdminRequestLogReader
{
    /** Both shapes OpenAI uses: chat/completions (prompt_/completion_) and responses (input_/output_). */
    private const TOKENS_IN_SQL = "COALESCE(response_body->'usage'->>'prompt_tokens', response_body->'usage'->>'input_tokens')";
    private const TOKENS_OUT_SQL = "COALESCE(response_body->'usage'->>'completion_tokens', response_body->'usage'->>'output_tokens')";
    private const MODEL_SQL = "COALESCE(response_body->>'model', request_body->>'model')";

    private const LIST_COLUMNS = [
        'id', 'direction', 'method', 'host', 'path', 'service', 'purpose', 'collection_id',
        'status', 'duration_ms', 'user_id', 'occurred_at', 'error',
    ];

    public function __construct(private readonly ModelCost $cost) {}

    public function list(LogFilters $filters, ListWindow $window): Page
    {
        $base = $this->filtered($filters)->select([
            ...self::LIST_COLUMNS,
            DB::raw(self::MODEL_SQL . ' AS model'),
            DB::raw(self::TOKENS_IN_SQL . ' AS tokens_in'),
            DB::raw(self::TOKENS_OUT_SQL . ' AS tokens_out'),
        ]);

        return Keyset::page(
            $base,
            $window,
            'id',
            ['*'],
            fn (array $rows): array => array_map($this->row(...), $rows),
        );
    }

    public function detail(string $id): ?RequestLogDetail
    {
        $r = DB::table('api_request_logs')
            ->where('id', $id)
            ->select([
                '*',
                DB::raw(self::MODEL_SQL . ' AS model'),
                DB::raw(self::TOKENS_IN_SQL . ' AS tokens_in'),
                DB::raw(self::TOKENS_OUT_SQL . ' AS tokens_out'),
            ])
            ->first();
        if ($r === null) {
            return null;
        }

        return new RequestLogDetail(
            row: $this->row($r),
            requestBytes: $r->request_bytes !== null ? (int) $r->request_bytes : null,
            responseBytes: $r->response_bytes !== null ? (int) $r->response_bytes : null,
            requestHeaders: $this->json($r->request_headers ?? null),
            requestBody: $this->json($r->request_body ?? null),
            responseBody: $this->json($r->response_body ?? null),
        );
    }

    public function recentFailures(int $limit): array
    {
        $rows = DB::table('api_request_logs')
            ->where('direction', 'outbound')
            ->where(function (Builder $q): void {
                $q->where('status', '>=', 500)->orWhereNotNull('error');
            })
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->select([
                ...self::LIST_COLUMNS,
                DB::raw(self::MODEL_SQL . ' AS model'),
                DB::raw(self::TOKENS_IN_SQL . ' AS tokens_in'),
                DB::raw(self::TOKENS_OUT_SQL . ' AS tokens_out'),
            ])
            ->get();

        return array_values(array_map($this->row(...), $rows->all()));
    }

    private function filtered(LogFilters $f): Builder
    {
        $q = DB::table('api_request_logs');

        if ($f->direction !== null) {
            $q->where('direction', $f->direction);
        }
        if ($f->provider !== null) {
            $q->where('service', $f->provider);
        }
        if ($f->status !== null) {
            $q->where('status', $f->status);
        }
        if ($f->statusClass !== null) {
            $this->applyStatusClass($q, $f->statusClass);
        }
        if ($f->purpose !== null) {
            $q->where('purpose', $f->purpose);
        }
        if ($f->userId !== null) {
            $q->where('user_id', $f->userId);
        }
        if ($f->collectionId !== null) {
            $q->where('collection_id', $f->collectionId);
        }
        if ($f->from !== null) {
            $q->where('occurred_at', '>=', $f->from);
        }
        if ($f->to !== null) {
            $q->where('occurred_at', '<=', $f->to);
        }
        if ($f->path !== null) {
            $q->where('path', 'ILIKE', '%' . $f->path . '%');
        }
        if ($f->search !== null) {
            // Body search: cast the jsonb to text and match a substring. No index backs this — it
            // is a forensic tool over a small table, not a hot path.
            $like = '%' . $f->search . '%';
            $q->where(function (Builder $inner) use ($like): void {
                $inner->whereRaw('request_body::text ILIKE ?', [$like])
                    ->orWhereRaw('response_body::text ILIKE ?', [$like]);
            });
        }

        return $q;
    }

    private function applyStatusClass(Builder $q, string $class): void
    {
        match ($class) {
            '2xx' => $q->whereBetween('status', [200, 299]),
            '4xx' => $q->whereBetween('status', [400, 499]),
            '5xx' => $q->where('status', '>=', 500),
            // "error" = the call never came back with a status at all (transport failure).
            'error' => $q->whereNotNull('error'),
            default => null,
        };
    }

    private function row(stdClass $r): RequestLogRow
    {
        $model = $this->str($r, 'model');
        $tokensIn = $this->int($r, 'tokens_in');
        $tokensOut = $this->int($r, 'tokens_out');

        $cost = $model !== null ? $this->cost->estimate($model, $tokensIn, $tokensOut) : null;

        return new RequestLogRow(
            id: (string) $r->id,
            direction: (string) $r->direction,
            method: (string) $r->method,
            host: $r->host !== null ? (string) $r->host : null,
            path: (string) $r->path,
            service: $r->service !== null ? (string) $r->service : null,
            purpose: $r->purpose !== null ? (string) $r->purpose : null,
            collectionId: $r->collection_id !== null ? (string) $r->collection_id : null,
            status: $r->status !== null ? (int) $r->status : null,
            durationMs: $r->duration_ms !== null ? (int) $r->duration_ms : null,
            userId: $r->user_id !== null ? (string) $r->user_id : null,
            occurredAt: Iso::orNull($r->occurred_at),
            model: $model,
            tokensIn: $tokensIn,
            tokensOut: $tokensOut,
            costUsd: $cost !== null ? (float) $cost : null,
            error: $r->error !== null ? (string) $r->error : null,
        );
    }

    /** Derived columns arrive as `mixed` off a raw select — narrow them in one place. */
    private function str(stdClass $r, string $key): ?string
    {
        $value = $r->{$key} ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    private function int(stdClass $r, string $key): ?int
    {
        $value = $r->{$key} ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /** @return array<mixed>|null */
    private function json(mixed $raw): ?array
    {
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}

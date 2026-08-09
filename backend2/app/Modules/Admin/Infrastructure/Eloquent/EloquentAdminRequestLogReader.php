<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Dto\RequestLogRow;
use App\Modules\Admin\Application\Port\AdminRequestLogReader;
use App\Modules\Admin\Infrastructure\Support\Iso;
use Illuminate\Support\Facades\DB;
use stdClass;

/** Projection over Observability's api_request_logs. */
final class EloquentAdminRequestLogReader implements AdminRequestLogReader
{
    public function list(?string $userId, ?int $status, ?string $path, int $page, int $perPage): Page
    {
        $base = DB::table('api_request_logs');
        if ($userId !== null && $userId !== '') {
            $base->where('user_id', $userId);
        }
        if ($status !== null) {
            $base->where('status', $status);
        }
        if ($path !== null && $path !== '') {
            $base->where('path', 'ILIKE', '%' . $path . '%');
        }

        $total = (clone $base)->count();

        $rows = (clone $base)
            ->orderByDesc('occurred_at')
            ->offset(max(0, ($page - 1) * $perPage))
            ->limit($perPage)
            ->get(['id', 'direction', 'method', 'host', 'path', 'service', 'status', 'duration_ms', 'user_id', 'occurred_at']);

        $items = array_map(static fn (stdClass $r): RequestLogRow => new RequestLogRow(
            id: (string) $r->id,
            direction: (string) $r->direction,
            method: (string) $r->method,
            host: $r->host !== null ? (string) $r->host : null,
            path: (string) $r->path,
            service: $r->service !== null ? (string) $r->service : null,
            status: $r->status !== null ? (int) $r->status : null,
            durationMs: $r->duration_ms !== null ? (int) $r->duration_ms : null,
            userId: $r->user_id !== null ? (string) $r->user_id : null,
            occurredAt: Iso::orNull($r->occurred_at),
        ), $rows->all());

        return new Page(array_values($items), $total, $page, $perPage);
    }
}

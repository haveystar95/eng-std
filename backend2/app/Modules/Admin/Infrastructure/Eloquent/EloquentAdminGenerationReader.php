<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Dto\GenerationRow;
use App\Modules\Admin\Application\Dto\ListWindow;
use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Port\AdminGenerationReader;
use App\Modules\Admin\Infrastructure\Support\Iso;
use App\Modules\Admin\Infrastructure\Support\Keyset;
use Illuminate\Support\Facades\DB;
use stdClass;

/** Projection over Generation's generation_requests. */
final class EloquentAdminGenerationReader implements AdminGenerationReader
{
    public function list(?string $userId, ?string $status, ListWindow $window): Page
    {
        $base = DB::table('generation_requests');
        if ($userId !== null && $userId !== '') {
            $base->where('user_id', $userId);
        }
        if ($status !== null && $status !== '') {
            $base->where('status', $status);
        }

        return Keyset::page(
            $base,
            $window,
            'id',
            ['id', 'user_id', 'prompt', 'status', 'model', 'tokens_in', 'tokens_out', 'cost_usd', 'collection_id', 'error', 'created_at', 'finished_at'],
            static fn (array $rows): array => array_map(
                static fn (stdClass $r): GenerationRow => new GenerationRow(
                    id: (string) $r->id,
                    userId: (string) $r->user_id,
                    prompt: (string) $r->prompt,
                    status: (string) $r->status,
                    model: $r->model !== null ? (string) $r->model : null,
                    tokensIn: $r->tokens_in !== null ? (int) $r->tokens_in : null,
                    tokensOut: $r->tokens_out !== null ? (int) $r->tokens_out : null,
                    costUsd: $r->cost_usd !== null ? (float) $r->cost_usd : null,
                    collectionId: $r->collection_id !== null ? (string) $r->collection_id : null,
                    error: $r->error !== null ? (string) $r->error : null,
                    createdAt: Iso::orNull($r->created_at),
                    finishedAt: Iso::orNull($r->finished_at),
                ),
                $rows,
            ),
        );
    }
}

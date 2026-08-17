<?php

declare(strict_types=1);

namespace App\Modules\Admin\Infrastructure\Eloquent;

use App\Modules\Admin\Application\Dto\DialogDetail;
use App\Modules\Admin\Application\Dto\DialogRow;
use App\Modules\Admin\Application\Dto\ListWindow;
use App\Modules\Admin\Application\Dto\Page;
use App\Modules\Admin\Application\Dto\TranscriptLineRow;
use App\Modules\Admin\Application\Port\AdminDialogReader;
use App\Modules\Admin\Infrastructure\Support\Iso;
use App\Modules\Admin\Infrastructure\Support\Keyset;
use Illuminate\Support\Facades\DB;
use stdClass;

/** Projection over Generation's practice_dialogs (+ transcript from practice_dialog_messages). */
final class EloquentAdminDialogReader implements AdminDialogReader
{
    public function list(?string $userId, ListWindow $window): Page
    {
        $base = DB::table('practice_dialogs');
        if ($userId !== null && $userId !== '') {
            $base->where('user_id', $userId);
        }

        return Keyset::page(
            $base,
            $window,
            'id',
            ['id', 'user_id', 'collection_id', 'status', 'tokens_in', 'tokens_out', 'cost_usd', 'created_at', 'finished_at'],
            fn (array $rows): array => array_map($this->toRow(...), $rows),
        );
    }

    public function detail(string $dialogId): ?DialogDetail
    {
        $d = DB::table('practice_dialogs')->where('id', $dialogId)->first();
        if ($d === null) {
            return null;
        }

        $lines = DB::table('practice_dialog_messages')
            ->where('dialog_id', $dialogId)
            ->orderBy('ts')
            ->get(['role', 'text', 'ts']);

        return new DialogDetail(
            id: (string) $d->id,
            userId: (string) $d->user_id,
            collectionId: (string) $d->collection_id,
            status: (string) $d->status,
            tokensIn: $d->tokens_in !== null ? (int) $d->tokens_in : null,
            tokensOut: $d->tokens_out !== null ? (int) $d->tokens_out : null,
            costUsd: $d->cost_usd !== null ? (float) $d->cost_usd : null,
            summary: $d->summary !== null ? (string) $d->summary : null,
            createdAt: Iso::orNull($d->created_at),
            finishedAt: Iso::orNull($d->finished_at),
            transcript: array_values(array_map(static fn (stdClass $r): TranscriptLineRow => new TranscriptLineRow(
                role: (string) $r->role,
                text: (string) $r->text,
                ts: (int) $r->ts,
            ), $lines->all())),
        );
    }

    private function toRow(stdClass $r): DialogRow
    {
        return new DialogRow(
            id: (string) $r->id,
            userId: (string) $r->user_id,
            collectionId: (string) $r->collection_id,
            status: (string) $r->status,
            tokensIn: $r->tokens_in !== null ? (int) $r->tokens_in : null,
            tokensOut: $r->tokens_out !== null ? (int) $r->tokens_out : null,
            costUsd: $r->cost_usd !== null ? (float) $r->cost_usd : null,
            createdAt: Iso::orNull($r->created_at),
            finishedAt: Iso::orNull($r->finished_at),
        );
    }
}

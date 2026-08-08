<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Dto\TriageSyncRow;
use App\Modules\Learning\Application\Port\TriageSyncReader;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentTriageSyncReader implements TriageSyncReader
{
    public function changedTriages(UserId $userId, ?DateTimeImmutable $since, DateTimeImmutable $upper): array
    {
        // Governing verdict per term = the row with the greatest client_seq. DISTINCT ON keeps
        // exactly that row (backed by term_triages_user_term_seq_idx), same as currentByTerm.
        $governing = DB::table('term_triages')
            ->select(['term_id', 'verdict', 'collection_id', 'client_seq', 'created_at'])
            ->where('user_id', $userId->value)
            ->orderBy('term_id')
            ->orderByDesc('client_seq')
            ->distinct('term_id');

        // Then bound that governing row to the (since, upper] window on the server receipt time,
        // exactly like the rest of the delta feed, and order it into the paged stream.
        $query = DB::query()
            ->fromSub($governing, 'g')
            ->where('g.created_at', '<=', $upper)
            ->orderBy('g.created_at')
            ->orderBy('g.term_id');

        // Inclusive `since`, matching every other reader in the delta feed: at second precision the
        // boundary second is re-sent, and the client upserts idempotently so a re-send is a no-op.
        if ($since !== null) {
            $query->where('g.created_at', '>=', $since);
        }

        $rows = $query->get();

        $out = [];
        foreach ($rows as $row) {
            $out[] = new TriageSyncRow(
                termId: (string) $row->term_id,
                verdict: (string) $row->verdict,
                clientSeq: (int) $row->client_seq,
                collectionId: $row->collection_id !== null ? (string) $row->collection_id : null,
                updatedAt: new DateTimeImmutable((string) $row->created_at),
            );
        }

        return $out;
    }
}

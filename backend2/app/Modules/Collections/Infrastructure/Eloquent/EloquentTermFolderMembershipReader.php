<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Application\Port\TermFolderMembershipReader;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

final class EloquentTermFolderMembershipReader implements TermFolderMembershipReader
{
    public function foldersHolding(UserId $userId, array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $rows = DB::table('collection_items as ci')
            ->join('collections as c', 'c.id', '=', 'ci.collection_id')
            ->where('c.owner_id', $userId->value)
            ->whereNull('c.deleted_at')
            ->whereNull('ci.deleted_at')
            ->whereIn('ci.term_id', $termIds)
            // Newest folder last, like the shelf: ULIDs are time-sortable.
            ->orderBy('c.id')
            ->get(['ci.term_id', 'c.id', 'c.title', 'c.is_default']);

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->term_id][] = [
                'id' => (string) $row->id,
                'title' => (string) $row->title,
                'is_default' => (bool) $row->is_default,
            ];
        }

        return $out;
    }
}

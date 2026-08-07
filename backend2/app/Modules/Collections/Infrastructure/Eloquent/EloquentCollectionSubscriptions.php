<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Application\Port\CollectionSubscriptions;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentCollectionSubscriptions implements CollectionSubscriptions
{
    public function subscribe(UserId $userId, CollectionId $collectionId, DateTimeImmutable $addedAt): void
    {
        $existing = DB::table('user_collections')
            ->where('user_id', $userId->value)
            ->where('collection_id', $collectionId->value)
            ->first();

        if ($existing === null) {
            DB::table('user_collections')->insert([
                'user_id' => $userId->value,
                'collection_id' => $collectionId->value,
                'added_at' => $addedAt,
                'unsubscribed_at' => null,
                'is_pinned' => false,
            ]);

            return;
        }

        // Re-subscribe after an unsubscribe: clear the tombstone with a fresh added_at so the delta
        // re-ships the collection. An already-active row is left untouched (preserves pin/added_at).
        if ($existing->unsubscribed_at !== null) {
            DB::table('user_collections')
                ->where('user_id', $userId->value)
                ->where('collection_id', $collectionId->value)
                ->update(['unsubscribed_at' => null, 'added_at' => $addedAt]);
        }
    }

    public function unsubscribe(UserId $userId, CollectionId $collectionId, DateTimeImmutable $at): void
    {
        // Soft-unsubscribe: stamp the tombstone only on a currently-active row (idempotent).
        DB::table('user_collections')
            ->where('user_id', $userId->value)
            ->where('collection_id', $collectionId->value)
            ->whereNull('unsubscribed_at')
            ->update(['unsubscribed_at' => $at]);
    }
}

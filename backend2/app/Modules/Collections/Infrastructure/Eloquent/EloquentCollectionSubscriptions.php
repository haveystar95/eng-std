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
        // PK (user_id, collection_id) → insertOrIgnore makes a repeat subscribe a no-op.
        DB::table('user_collections')->insertOrIgnore([
            'user_id' => $userId->value,
            'collection_id' => $collectionId->value,
            'added_at' => $addedAt,
            'is_pinned' => false,
        ]);
    }

    public function unsubscribe(UserId $userId, CollectionId $collectionId): void
    {
        DB::table('user_collections')
            ->where('user_id', $userId->value)
            ->where('collection_id', $collectionId->value)
            ->delete();
    }
}

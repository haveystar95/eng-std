<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Application\Port\CollectionsAccountEraser;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

final class EloquentCollectionsAccountEraser implements CollectionsAccountEraser
{
    public function eraseFor(UserId $userId): void
    {
        // Hard-delete the user's own collections (forceDelete past SoftDeletes) so the FK
        // cascade removes their collection_items too. Store/system decks (no owner) are untouched.
        CollectionModel::withTrashed()->where('owner_id', $userId->value)->forceDelete();

        // Drop their store subscriptions (a link table, no soft-delete).
        DB::table('user_collections')->where('user_id', $userId->value)->delete();
    }
}

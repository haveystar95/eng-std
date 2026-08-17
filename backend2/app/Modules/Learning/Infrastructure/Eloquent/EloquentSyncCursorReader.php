<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Dto\SyncCursorView;
use App\Modules\Learning\Application\Port\SyncCursorReader;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

// max('client_seq') returns null on an empty log; the (int) cast collapses that to 0.
final class EloquentSyncCursorReader implements SyncCursorReader
{
    public function cursorFor(UserId $userId): SyncCursorView
    {
        return new SyncCursorView(
            maxTriageSeq: (int) DB::table('term_triages')->where('user_id', $userId->value)->max('client_seq'),
            maxReviewSeq: (int) DB::table('reviews')->where('user_id', $userId->value)->max('client_seq'),
        );
    }
}

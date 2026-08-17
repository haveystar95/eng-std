<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Application\Port\LearningAccountEraser;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

final class EloquentLearningAccountEraser implements LearningAccountEraser
{
    public function eraseFor(UserId $userId): void
    {
        $id = $userId->value;

        // reviews.session_id → study_sessions is nullOnDelete, so order doesn't matter here.
        DB::table('reviews')->where('user_id', $id)->delete();
        DB::table('term_triages')->where('user_id', $id)->delete();
        // term_exposures.session_id → study_sessions is nullOnDelete too, so it can go here.
        DB::table('term_exposures')->where('user_id', $id)->delete();
        DB::table('user_term_progress')->where('user_id', $id)->delete();
        DB::table('daily_user_stats')->where('user_id', $id)->delete();
        DB::table('study_sessions')->where('user_id', $id)->delete();
    }
}

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Honest naming, at the column, for the «честная лестница» наряд (QA-20 follow-up): the stored
     * threshold was never a count of `reps` (the SM-2 field this ladder does not use) — it is a
     * count of SUCCESSFUL reviews since graduation ({@see \App\Modules\Learning\Domain\ValueObject\ModeRule}).
     * The Domain and the writer already spoke the honest name (`minSuccessfulReviews`); only the
     * column itself still carried the historical one. This migration closes that last gap.
     *
     * The WIRE key is deliberately NOT renamed here. `/sync` ships this threshold to the Flutter
     * client as `min_reps` and nothing in this наряд may touch the client or its fixture — the
     * rename stops at {@see \App\Modules\Learning\Domain\ValueObject\ModeAdmission::toWire()},
     * marked RENAME-DEFERRED, until a contract version bump (FSRS) is the deliberate reason to move
     * the client too.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE learning_mode_settings RENAME COLUMN min_reps TO min_successful_reviews');
        DB::statement('ALTER TABLE learning_mode_settings RENAME CONSTRAINT learning_mode_settings_reps_check TO learning_mode_settings_min_successful_reviews_check');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE learning_mode_settings RENAME CONSTRAINT learning_mode_settings_min_successful_reviews_check TO learning_mode_settings_reps_check');
        DB::statement('ALTER TABLE learning_mode_settings RENAME COLUMN min_successful_reviews TO min_reps');
    }
};

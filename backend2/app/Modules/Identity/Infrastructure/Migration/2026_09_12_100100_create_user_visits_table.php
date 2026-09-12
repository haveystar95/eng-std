<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * WHEN THE LEARNER CAME (наряд PLAN-UI-3) — append-only, one row per visit that survived the
     * 30-minute throttle. Read for one thing: the last seven visits of one user, newest first, to
     * aim the daily reminder at their usual hour. The (user_id, visited_at DESC) index is exactly
     * that read and also the throttle's «last visit» lookup. Cascades with the user row.
     */
    public function up(): void
    {
        Schema::create('user_visits', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26);
            $table->timestampTz('visited_at');

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
        DB::statement('CREATE INDEX user_visits_user_time_idx ON user_visits (user_id, visited_at DESC)');
    }

    public function down(): void
    {
        Schema::dropIfExists('user_visits');
    }
};

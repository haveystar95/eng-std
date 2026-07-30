<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // A session is no longer one presentation mode (the flashcard era) — per-card modes are
    // chosen by the ExerciseSelector at build time. What the session needs to record is whether
    // it was free practice, and its fixed composition (the term ids it was built with) so an
    // answer for a term outside the session can be rejected.
    public function up(): void
    {
        DB::statement('ALTER TABLE study_sessions DROP CONSTRAINT study_sessions_mode_check');
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->dropColumn('mode');
            $table->boolean('is_practice')->default(false)->after('collection_id');
            $table->jsonb('composition')->nullable()->after('is_practice'); // term ids fixed at build time
        });
    }

    public function down(): void
    {
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->dropColumn(['is_practice', 'composition']);
            $table->string('mode', 16)->default('typing');
        });
        DB::statement("ALTER TABLE study_sessions ADD CONSTRAINT study_sessions_mode_check CHECK (mode IN ('flashcard','typing','multiple_choice','listening'))");
    }
};

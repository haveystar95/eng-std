<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Adds the `known` progress state (triage "I know this" shortcut). The state column is
    // text + CHECK (not a PG enum), so widening it is a constraint swap, no table rewrite.
    // Existing partial indexes already cover it: `known` is `<> 'new'`, so a known term with
    // a scheduled verification due_at is found by the due index like any other.
    public function up(): void
    {
        DB::statement('ALTER TABLE user_term_progress DROP CONSTRAINT user_term_progress_state_check');
        DB::statement("ALTER TABLE user_term_progress ADD CONSTRAINT user_term_progress_state_check CHECK (state IN ('new','learning','review','relearning','known'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE user_term_progress DROP CONSTRAINT user_term_progress_state_check');
        DB::statement("ALTER TABLE user_term_progress ADD CONSTRAINT user_term_progress_state_check CHECK (state IN ('new','learning','review','relearning'))");
    }
};

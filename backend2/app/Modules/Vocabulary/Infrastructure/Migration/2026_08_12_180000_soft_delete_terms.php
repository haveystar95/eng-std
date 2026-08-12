<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A term can be RETIRED but never physically removed, for two independent reasons:
        //  1. `reviews.term_id` is ON DELETE RESTRICT and the review log is append-only — a hard
        //     delete would either fail or force us to rewrite history, which is the one thing that
        //     log exists to prevent.
        //  2. Delta sync needs something to read to tell an offline device the term is gone; a
        //     vanished row is indistinguishable from a row the client never had.
        Schema::table('terms', function (Blueprint $table) {
            $table->timestampTz('deleted_at')->nullable()->after('updated_at');
        });

        // Retired terms are excluded from dedup: retiring "acount" must not block re-adding it
        // properly later. Rebuilt as a partial unique index over live rows only.
        DB::statement('DROP INDEX IF EXISTS terms_dedup_uidx');
        DB::statement(
            "CREATE UNIQUE INDEX terms_dedup_uidx ON terms (lang, normalized_text, COALESCE(pos, ''))"
            . ' WHERE deleted_at IS NULL'
        );
        DB::statement('CREATE INDEX terms_deleted_idx ON terms (deleted_at) WHERE deleted_at IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS terms_deleted_idx');
        DB::statement('DROP INDEX IF EXISTS terms_dedup_uidx');
        DB::statement("CREATE UNIQUE INDEX terms_dedup_uidx ON terms (lang, normalized_text, COALESCE(pos, ''))");

        Schema::table('terms', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });
    }
};

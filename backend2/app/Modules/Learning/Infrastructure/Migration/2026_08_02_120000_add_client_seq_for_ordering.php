<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Clock-independent ordering. The current verdict for a (user, term) and the fold order of
    // reviews must NOT depend on the device clock (decided_at / answered_at): a lagging phone can
    // stamp a genuinely-later action with an earlier time and silently roll back progress (a term
    // returned to learning loses to the older "known"). The client now assigns a per-user
    // monotonic sequence to every swipe / answer, and the server orders by it. decided_at /
    // answered_at stay as reference-only analytics fields.
    //
    // DEFAULT 0 keeps any pre-existing append-only rows valid; real client sequences start at 1,
    // so a legacy row (seq 0) always loses to a real one.
    public function up(): void
    {
        Schema::table('term_triages', function (Blueprint $table) {
            $table->bigInteger('client_seq')->default(0)->after('latency_ms');
        });
        Schema::table('reviews', function (Blueprint $table) {
            $table->bigInteger('client_seq')->default(0)->after('latency_ms');
        });

        // The governing verdict for a (user, term) is the row with the greatest client_seq;
        // this index backs the DISTINCT ON (term_id) ... ORDER BY client_seq DESC lookup.
        DB::statement('CREATE INDEX term_triages_user_term_seq_idx ON term_triages (user_id, term_id, client_seq DESC)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS term_triages_user_term_seq_idx');
        Schema::table('term_triages', function (Blueprint $table) {
            $table->dropColumn('client_seq');
        });
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn('client_seq');
        });
    }
};

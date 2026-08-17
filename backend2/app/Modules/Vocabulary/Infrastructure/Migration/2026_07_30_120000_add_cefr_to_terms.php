<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // CEFR is a term attribute, not a generation artifact: the LLM already returns it per
    // item (paid for in tokens), and it feeds both difficulty-aware generation and "at what
    // level am I learning" stats. Persist it on the term. NULL means "unknown" and MUST be
    // treated NEUTRALLY by anything reading it (never as a risk) — otherwise every curated
    // term without a level would look hard.
    //
    // The sibling signal `frequency_rank` stays NULL: > Not implemented yet. Reason: phrases
    // have no frequency rank at all (frequency lists are per single word), and phrases are
    // ~half the corpus — so it is treated NEUTRALLY too, same as a NULL cefr.
    public function up(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->string('cefr', 2)->nullable()->after('ipa');
        });

        // NULL passes the CHECK (NULL IN (...) is unknown, not false), so "unknown" is allowed.
        DB::statement("ALTER TABLE terms ADD CONSTRAINT terms_cefr_check CHECK (cefr IN ('A1','A2','B1','B2','C1','C2'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE terms DROP CONSTRAINT IF EXISTS terms_cefr_check');
        Schema::table('terms', function (Blueprint $table) {
            $table->dropColumn('cefr');
        });
    }
};

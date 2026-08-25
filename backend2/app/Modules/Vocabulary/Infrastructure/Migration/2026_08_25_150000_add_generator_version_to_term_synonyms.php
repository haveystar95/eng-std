<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WHO wrote each synonym — the same stamp `term_accepted_variants` and `example_distractors` have
 * carried since they existed, and which `term_synonyms` was created without.
 *
 * The gap showed up the first time it mattered. The SYN-1 run's own pilot produced fifteen synonyms
 * on twenty terms, of which five were a NARROWER word rather than a synonym («bank account» →
 * «savings account»), the prompt was rewritten, and there was then no way to say «delete what the
 * previous prompt wrote» — the rows were indistinguishable from ones a person had pinned. A content
 * table whose rows cannot be traced to the text that produced them cannot be rolled back, and every
 * other enriched table in this schema already knew that.
 *
 * `source` does NOT answer this question and is not a substitute: it says whether a human or a
 * machine put the row there, which is a question about AUTHORITY (a curated row survives a re-run).
 * This says which prompt version a machine used, which is a question about PROVENANCE. Both are
 * needed for the same reason `terms` carries `source` and `prompt_version` side by side (п. 59).
 *
 * Nullable with no DEFAULT, deliberately, exactly like the provenance columns of `2026_08_20_100000`:
 * a NULL means «written before the stamp existed, or by a hand that is not a generator», and a
 * DEFAULT would turn «nobody recorded this» into a confident lie about which prompt ran.
 *
 * The rows already in the table are stamped `mech-v14.1` and that is a FACT, not a guess: the table
 * was created empty by this same наряд hours earlier and the v14.1 pilot is the only thing that has
 * ever written to it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('term_synonyms', function (Blueprint $table): void {
            $table->string('generator_version', 16)->nullable()->after('source');
        });

        DB::table('term_synonyms')->whereNull('generator_version')->update(['generator_version' => 'mech-v14.1']);
    }

    public function down(): void
    {
        Schema::table('term_synonyms', function (Blueprint $table): void {
            $table->dropColumn('generator_version');
        });
    }
};

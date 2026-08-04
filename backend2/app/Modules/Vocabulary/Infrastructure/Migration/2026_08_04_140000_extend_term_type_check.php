<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Widen the term-type taxonomy from word|phrase to word|phrase|idiom|phrasal_verb. Idioms and
 * phrasal verbs are still phrase-like everywhere (see TermType::isPhraseLike) — this only lets the
 * generator tag them precisely so the UI can badge them. No backfill: existing rows stay word|phrase.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE terms DROP CONSTRAINT terms_type_check');
        DB::statement("ALTER TABLE terms ADD CONSTRAINT terms_type_check CHECK (type IN ('word','phrase','idiom','phrasal_verb'))");
    }

    public function down(): void
    {
        // Reversible only while no row uses the new values; fold them back to 'phrase' first so the
        // narrower constraint can be re-applied.
        DB::statement("UPDATE terms SET type = 'phrase' WHERE type IN ('idiom','phrasal_verb')");
        DB::statement('ALTER TABLE terms DROP CONSTRAINT terms_type_check');
        DB::statement("ALTER TABLE terms ADD CONSTRAINT terms_type_check CHECK (type IN ('word','phrase'))");
    }
};

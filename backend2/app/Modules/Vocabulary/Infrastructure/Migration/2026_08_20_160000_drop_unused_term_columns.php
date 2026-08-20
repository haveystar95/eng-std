<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Three columns nobody has ever written, and an index maintained on top of one of them.
 *
 * The data-flow audit counted them: `audio_url` 0 of 483 rows, `frequency_rank` 0, `embedding` 0.
 * The last one is the expensive part — `terms_embedding_hnsw` is an HNSW index over a `vector(1536)`
 * column that is empty in every row, so every insert and update of a term has paid to maintain an
 * index structure with no entries and no reader. A column that is merely empty costs storage; an
 * indexed empty column costs write time.
 *
 * They were not mistakes when they were added — they are the schema of features that were designed
 * and then not built (pronunciation audio, frequency-ranked ordering, semantic dedup by embedding
 * similarity). Keeping a column for a feature nobody is building is how a schema stops describing
 * the app; and this migration is reversible, so the day semantic dedup is actually built, `down()`
 * is the shape it comes back in.
 *
 * `pos` is deliberately NOT dropped, although the audit lists it in the same breath and it is just as
 * empty. It is not dead weight: it is the third component of the dedup key
 * (`terms_dedup_uidx (lang, normalized_text, COALESCE(pos, ''))`), it is a field of the Term entity,
 * and it travels through `ImportTerm`, the repository's `findByDedup`, the mapper and the admin
 * projection. Removing it changes what "the same term" MEANS and touches an API surface — that is a
 * dedup decision with its own tests, not hygiene, and hiding it inside a cleanup migration is how an
 * invariant gets changed without anyone reviewing the change.
 *
 * `audio_url` also disappears from the admin term projection in this commit — a field that can only
 * ever be null is worse than an absent one: it reads as "this term has no audio" rather than "this
 * app has no audio".
 */
return new class extends Migration
{
    public function up(): void
    {
        // The index first: dropping the column would take it with it, but naming it here is what
        // makes the reason for this migration legible in the schema history.
        DB::statement('DROP INDEX IF EXISTS terms_embedding_hnsw');
        DB::statement('ALTER TABLE terms DROP COLUMN IF EXISTS embedding');

        Schema::table('terms', function (Blueprint $table): void {
            $table->dropColumn(['audio_url', 'frequency_rank']);
        });
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table): void {
            $table->text('audio_url')->nullable();
            $table->integer('frequency_rank')->nullable();
        });

        // pgvector is still installed (the create migration leaves it), so the column and its index
        // come back exactly as they were.
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
        DB::statement('ALTER TABLE terms ADD COLUMN embedding vector(1536)');
        DB::statement('CREATE INDEX terms_embedding_hnsw ON terms USING hnsw (embedding vector_cosine_ops)');
    }
};

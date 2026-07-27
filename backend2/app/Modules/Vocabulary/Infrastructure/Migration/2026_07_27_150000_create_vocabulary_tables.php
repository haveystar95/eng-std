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
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('terms', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('lang', 5);
            $table->text('text');
            $table->text('normalized_text');
            $table->string('type', 16);          // word | phrase
            $table->string('pos', 24)->nullable();
            $table->text('ipa')->nullable();
            $table->text('audio_url')->nullable();
            $table->integer('frequency_rank')->nullable();
            $table->string('source', 16);        // curated | ai | user
            $table->char('created_by', 26)->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE terms ADD COLUMN embedding vector(1536)');
        DB::statement("ALTER TABLE terms ADD CONSTRAINT terms_type_check CHECK (type IN ('word','phrase'))");
        DB::statement("ALTER TABLE terms ADD CONSTRAINT terms_source_check CHECK (source IN ('curated','ai','user'))");
        // The dedup key: one term per (lang, normalized_text, pos-or-empty).
        DB::statement("CREATE UNIQUE INDEX terms_dedup_uidx ON terms (lang, normalized_text, COALESCE(pos, ''))");
        DB::statement('CREATE INDEX terms_embedding_hnsw ON terms USING hnsw (embedding vector_cosine_ops)');

        Schema::create('term_translations', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('term_id', 26);
            $table->string('lang', 5);
            $table->text('text');
            $table->boolean('is_primary')->default(false);
            $table->timestampsTz();

            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
            $table->unique(['term_id', 'lang', 'text'], 'term_translations_uidx');
        });

        Schema::create('term_examples', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('term_id', 26);
            $table->text('sentence');
            $table->text('sentence_translation')->nullable();
            $table->string('source', 16)->nullable();
            $table->timestampsTz();

            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
            $table->index('term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('term_examples');
        Schema::dropIfExists('term_translations');
        Schema::dropIfExists('terms');
        // The `vector` extension is left installed; other modules may use it.
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A term's DESCRIPTION: what the word means, said in the language being learned.
     *
     * A table of its own rather than a column on `terms`, for the same reason translations and
     * examples have one — it is written in A LANGUAGE, and a column would have to pick one forever.
     * Today only `en` is written (the search lookup asks for an English definition at A2–B1, which
     * is the thing the `description_match` trainer shows); the `lang` column is what keeps a second
     * one from needing a migration.
     *
     * `source`/`prompt_version`/`generation_model` are the same provenance triple the rest of the
     * generated content carries: a description written by a model must be able to say WHICH model
     * and WHICH prompt, or a content sweep six months from now has nothing to sort by.
     *
     * NOT backfilled onto the store catalogue. Those terms have no description and will not get one
     * in this наряд — it is a paid станок run over the whole showcase, and the trainer that needs
     * one simply refuses a term that has none (`ContentGap::NoDescription`).
     */
    public function up(): void
    {
        Schema::create('term_descriptions', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('term_id', 26);
            $table->string('lang', 5);
            $table->text('text');
            $table->string('source', 16)->nullable();   // curated | ai | user
            $table->text('prompt_version')->nullable();
            $table->text('generation_model')->nullable();
            $table->timestampsTz();

            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
            // One description per (term, language). A card shows exactly one, so a heap of rows to
            // pick from would make «which description» a question the reader answers by accident.
            $table->unique(['term_id', 'lang'], 'term_descriptions_uidx');
        });

        DB::statement("ALTER TABLE term_descriptions ADD CONSTRAINT term_descriptions_source_check CHECK (source IS NULL OR source IN ('curated','ai','user'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('term_descriptions');
    }
};

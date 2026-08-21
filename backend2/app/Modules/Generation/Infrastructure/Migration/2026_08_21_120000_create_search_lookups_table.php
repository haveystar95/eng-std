<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The search lookup CACHE: one paid model call per word, ever, for everybody.
     *
     * A learner types a word the database has never seen; one cheap model call comes back with a
     * translation, a description, an example and a level. That answer is not personal — it is a fact
     * about the word — so it is stored under the NORMALIZED QUERY and the next person to type the
     * same thing (or the same person tomorrow) is served for free. The unique index is what makes
     * that true rather than aspirational.
     *
     * `user_id` is WHO PAID, not who may read: every row is readable by everyone, and the column
     * exists so the daily cap has something to count. Nullable and `nullOnDelete` so deleting an
     * account does not delete the word knowledge it happened to buy — that content belongs to the
     * catalogue now, and the alternative (cascade) would make one deletion re-charge everybody else.
     *
     * The term is NOT created here. A lookup is an answer the learner is looking at; the term row
     * appears only when they explicitly add it (`POST /search/add`), which is what keeps the term
     * table free of words nobody ever wanted.
     */
    public function up(): void
    {
        Schema::create('search_lookups', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('user_id', 26)->nullable();
            $table->text('normalized_query');
            $table->string('lang', 5);          // the language being looked up (the term's own)
            $table->string('native_lang', 5);   // the language the translation came back in
            $table->jsonb('payload');           // the validated answer: text/translation/description/…
            $table->text('model');
            $table->text('prompt_version');
            $table->integer('tokens_in')->nullable();
            $table->integer('tokens_out')->nullable();
            $table->decimal('cost_usd', 12, 6)->nullable();
            $table->timestampsTz();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });

        // One cached answer per (query, language pair). The pair is part of the key because the
        // translation is in the learner's language — a Russian speaker's «bank» and a Ukrainian
        // speaker's are different answers to the same word.
        DB::statement('CREATE UNIQUE INDEX search_lookups_query_uidx ON search_lookups (normalized_query, lang, native_lang)');
        // What the daily cap counts: rows this user paid for today.
        DB::statement('CREATE INDEX search_lookups_payer_idx ON search_lookups (user_id, created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('search_lookups');
    }
};

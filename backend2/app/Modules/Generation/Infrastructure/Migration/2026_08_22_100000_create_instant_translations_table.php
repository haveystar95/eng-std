<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The instant-translation cache: one paid vendor call per (word, language pair), ever.
     *
     * The same shape and the same reasoning as `search_lookups`, one step earlier in the funnel: a
     * translation is a fact about a word, not about who asked, so the row is GLOBAL and permanent
     * and the second person to type the word costs nothing. On a debounced search field that is not
     * an optimisation — it is the difference between a feature and a monthly bill, because the same
     * few hundred words get typed over and over.
     *
     * `characters` is what makes the monthly budget exact. The free plan bills in characters SENT,
     * so every row carries the length of what was sent and the month's spend is a SUM over rows —
     * not a counter that a crash, a rollback or a second web worker could lose track of. It is also
     * why there is no `user_id` here: nobody is billed personally, the deployment is.
     *
     * No `updated_at`: a row is written once and never revised. A better translation of the same
     * word is a different product decision (re-run the lookup model), not a silent overwrite of a
     * hint the learner may already have read.
     */
    public function up(): void
    {
        Schema::create('instant_translations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->text('normalized_text');
            // 'en:ru' — one column rather than two because it is only ever used whole, as half of
            // the cache key.
            $table->string('lang_pair', 11);
            $table->text('translation');
            $table->string('provider', 24);              // 'deepl' — stable, it is read in reports
            $table->integer('characters');               // what the vendor billed: the SENT length
            $table->timestampTz('created_at');
        });

        // The cache key. One answer per word per direction.
        DB::statement('CREATE UNIQUE INDEX instant_translations_key_uidx ON instant_translations (normalized_text, lang_pair)');
        // The monthly meter: SUM(characters) for one provider since the 1st.
        DB::statement('CREATE INDEX instant_translations_meter_idx ON instant_translations (provider, created_at)');
    }

    public function down(): void
    {
        Schema::dropIfExists('instant_translations');
    }
};

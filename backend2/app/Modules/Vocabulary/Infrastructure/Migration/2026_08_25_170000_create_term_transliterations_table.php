<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `term_transliterations` — HOW THE TERM READS, spelled in the letters of the SUPPORT language:
 * «cómo estás» → «комо эстас» for a Russian-speaking learner.
 *
 * ## Why it is not a column on `term_translations`
 *
 * The наряд says «положи в слой пары рядом с переводом, НЕ на термин», and that is exactly what this
 * is — the question is which row in that layer owns it. `term_translations` is keyed
 * `(term_id, lang, TEXT)`: the text is part of the key, so a term with two Russian readings has two
 * rows. A transliteration is a fact about `(term, support language)` and nothing else — «cómo estás»
 * reads «комо эстас» whether the card says «как дела» or «как ты». A column there would therefore
 * exist once per translation, and the writer would have to keep N copies in step.
 *
 * That is the defect DECISIONS п. 138 already fixed once, in this same schema: `sentence_translation`
 * was a column on `term_examples`, a per-language value hung on a row that was not keyed by language,
 * and a term glossed for two collections had exactly one gloss with nothing to say whose it was. The
 * cure there was a sibling table keyed by `(parent, lang)` — `example_translations` — and this is the
 * same shape for the same reason.
 *
 * So: a sibling of `term_translations`, in the same layer, keyed the way the value actually is.
 *
 * ## Why it is not `terms.ipa`
 *
 * `ipa` is the phonetic transcription of the term, one per term, in a notation that is the same for
 * every reader — and unreadable to anyone who has not learned it. This is the opposite: a reading
 * hint for somebody who does not know the target language's spelling rules, written in an alphabet
 * they already read, and therefore DIFFERENT for each support language. Neither replaces the other
 * and neither is written over the other; `ipa` is not touched by anything in this наряд.
 *
 * `source` and `generator_version` mirror `term_synonyms`: who decided (authority) and which prompt
 * wrote it (provenance). Both are needed, and the synonym table learned that the hard way — it
 * shipped without the second one and could not be rolled back a run later.
 */
return new class extends Migration
{
    private const SOURCES = ['auto', 'curated'];

    public function up(): void
    {
        Schema::create('term_transliterations', function (Blueprint $table): void {
            $table->char('id', 26)->primary();
            $table->char('term_id', 26);
            // The SUPPORT language — whose alphabet the hint is written in. Not the term's.
            $table->string('lang', 5);
            $table->text('text');
            $table->string('source', 16);
            $table->string('generator_version', 16)->nullable();
            $table->timestampsTz();

            $table->foreign('term_id')->references('id')->on('terms')->cascadeOnDelete();
            // One hint per (term, support language) — the key the value actually has.
            $table->unique(['term_id', 'lang'], 'term_transliterations_uidx');
            $table->index('term_id', 'term_transliterations_term_idx');
        });

        $sources = "'" . implode("','", self::SOURCES) . "'";
        DB::statement("ALTER TABLE term_transliterations ADD CONSTRAINT term_transliterations_source_check CHECK (source IN ({$sources}))");
    }

    public function down(): void
    {
        Schema::dropIfExists('term_transliterations');
    }
};

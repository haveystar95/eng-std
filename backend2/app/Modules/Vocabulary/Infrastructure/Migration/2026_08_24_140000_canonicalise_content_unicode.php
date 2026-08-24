<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Service\TextNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Bring every stored content string to the canonical Unicode form the writers now produce.
     *
     * From today {@see TextNormalizer} runs on the way in, at the three content value objects. That
     * only makes the store consistent going FORWARD: everything written before is whatever byte
     * sequence the model or the keyboard happened to emit, and the two spellings sitting side by
     * side is precisely the state that makes a comparison unreliable. So the existing rows are
     * brought to the same form, once.
     *
     * `normalized_text` is recomputed for any term whose `text` moved — it is derived from the text
     * and it is the DEDUP KEY, so leaving it behind would mean a term that no longer finds itself.
     * The recomputation repeats `TermNormalizer`'s rule (lowercase, collapse whitespace, drop a
     * leading article for phrases) rather than calling it: a migration is a fact about one moment,
     * and a migration that calls today's Domain service re-runs TOMORROW's rule when the database is
     * rebuilt from scratch.
     *
     * Irreversible in the only sense that matters — `down()` cannot restore a cedilla it cannot tell
     * from a comma-below — so `down()` is a documented no-op rather than a lie. Nothing about the
     * SCHEMA changes here, so a rollback of the surrounding work is unaffected.
     */
    public function up(): void
    {
        $n = new TextNormalizer();
        $fixed = [];

        $fixed['terms'] = $this->canonicaliseTerms($n);
        $fixed['term_translations'] = $this->canonicaliseColumn($n, 'term_translations', 'text');
        $fixed['term_examples'] = $this->canonicaliseColumn($n, 'term_examples', 'sentence');
        $fixed['example_translations'] = $this->canonicaliseColumn($n, 'example_translations', 'text');
        $fixed['term_descriptions'] = $this->canonicaliseColumn($n, 'term_descriptions', 'text');
        $fixed['term_accepted_variants'] = $this->canonicaliseColumn($n, 'term_accepted_variants', 'text');
        // A distractor is a sentence the learner is shown and may be asked to compare, so it is
        // content like the rest; its span and correction are quoted FROM it and have to move with it
        // or the repair check stops matching its own sentence.
        $fixed['example_distractors.sentence'] = $this->canonicaliseColumn($n, 'example_distractors', 'sentence');
        $fixed['example_distractors.error_span'] = $this->canonicaliseColumn($n, 'example_distractors', 'error_span');
        $fixed['example_distractors.correction'] = $this->canonicaliseColumn($n, 'example_distractors', 'correction');

        // Said out loud: «0 rows fixed» is the answer this migration is expected to give on a store
        // that never held a cedilla, and an answer nobody can see is indistinguishable from a
        // migration that did not run.
        Log::info('unicode canonicalisation', ['rows_fixed' => array_filter($fixed)]);
    }

    public function down(): void
    {
        // Deliberately empty: the canonical form is not distinguishable from a hand-typed one, so
        // there is nothing to put back. No schema was touched.
    }

    /** @return int  rows whose text actually changed */
    private function canonicaliseTerms(TextNormalizer $n): int
    {
        $fixed = 0;
        foreach (DB::table('terms')->orderBy('id')->get(['id', 'text', 'type']) as $row) {
            $text = (string) $row->text;
            $canonical = $n->canonical($text);
            if ($canonical === $text) {
                continue;
            }

            DB::table('terms')->where('id', $row->id)->update([
                'text' => $canonical,
                'normalized_text' => $this->normalizedText($canonical, (string) $row->type),
            ]);
            $fixed++;
        }

        return $fixed;
    }

    /** @return int  rows whose value actually changed */
    private function canonicaliseColumn(TextNormalizer $n, string $table, string $column): int
    {
        $fixed = 0;
        foreach (DB::table($table)->orderBy('id')->get(['id', $column]) as $row) {
            $value = $row->{$column};
            if (! is_string($value)) {
                continue;
            }
            $canonical = $n->canonical($value);
            if ($canonical === $value) {
                continue;
            }

            DB::table($table)->where('id', $row->id)->update([$column => $canonical]);
            $fixed++;
        }

        return $fixed;
    }

    /** `TermNormalizer`'s rule as it stands today, frozen here on purpose — see the class docblock. */
    private function normalizedText(string $text, string $type): string
    {
        $value = mb_strtolower(trim($text));
        $value = (string) preg_replace('/\s+/u', ' ', $value);

        if ($type === 'phrase') {
            $value = (string) preg_replace('/^(the|a|an)\s+/u', '', $value);
        }

        return $value;
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Splits the single `language` finding into three, because they are repaired differently and a
 * worklist that mixes them cannot be triaged:
 *
 *  - `ua_leakage` — a word from a language closely related to the learner's (Ukrainian lexis or
 *    суржик in a Russian field). The model was writing in the wrong language → REGENERATE the item.
 *  - `misspelled_or_nonword` — a typo, an invented form, a transliteration. The item is fine and the
 *    wording is wrong → EDIT the translation. Found live on `colleague` → «колледка».
 *  - `language` — kept for the coarse case: a whole field in the wrong language.
 *
 * Existing rows keep `language`; nothing is reclassified retroactively, since the old rows carry no
 * evidence of which of the three they were.
 */
return new class extends Migration
{
    private const KINDS = ['ambiguity', 'language', 'ua_leakage', 'misspelled_or_nonword', 'variant_conflict'];

    private const PREVIOUS_KINDS = ['ambiguity', 'language', 'variant_conflict'];

    public function up(): void
    {
        $this->replaceCheck(self::KINDS);
    }

    public function down(): void
    {
        // Fold the new kinds back into `language` before narrowing the constraint, or the old CHECK
        // would refuse rows this version legitimately wrote.
        DB::table('enrichment_findings')
            ->whereIn('kind', ['ua_leakage', 'misspelled_or_nonword'])
            ->update(['kind' => 'language']);

        $this->replaceCheck(self::PREVIOUS_KINDS);
    }

    /** @param  list<string>  $kinds */
    private function replaceCheck(array $kinds): void
    {
        $list = "'" . implode("','", $kinds) . "'";
        DB::statement('ALTER TABLE enrichment_findings DROP CONSTRAINT IF EXISTS enrichment_findings_kind_check');
        DB::statement("ALTER TABLE enrichment_findings ADD CONSTRAINT enrichment_findings_kind_check CHECK (kind IN ({$list}))");
    }
};

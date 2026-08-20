<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit A7, the ten rows: terms carrying TWO primary translations in one language.
 *
 * A term is global and deduplicated, so every regeneration of the same text merged another reading
 * into it — and the merge appended, so a translation arriving marked primary landed BESIDE the
 * primary already there. «stay calm» ended up with «Оставайтесь спокойны» AND «оставаться
 * спокойным», both primary, and the question the learner is asked is whichever row the reader's
 * ordering happens to return first. That is a coin flip over the content of a card.
 *
 * The rule itself now lives in the aggregate ({@see \App\Modules\Vocabulary\Domain\Entity\Term::addTranslation()}),
 * so no eleventh term can be created this way. This migration is the ten that already exist.
 *
 * ## Which of the two survives
 *
 * The freshest by `updated_at` — the same statement the new rule makes: a merge is the newer
 * generation speaking, so its reading is the one the card asks.
 *
 * Where the two rows share an `updated_at` to the second (six of the ten were written by one batch),
 * nothing became fresher than anything, and the tie is broken by `id` ASCENDING — which is exactly
 * {@see \App\Modules\Vocabulary\Infrastructure\Eloquent\TranslationPick}'s own tiebreak, i.e. the row
 * the card is showing TODAY. A repair that has no reason to change what the learner sees must not
 * change it.
 *
 * ## What it does not do
 *
 * It deletes nothing. A demoted reading is a genuine alternative («cash register» → «касса» beside
 * «кассовый аппарат») and it stays queryable; it simply stops competing to be the question. No
 * translation TEXT is read or rewritten, no provenance is re-stamped, and `user_term_progress` and
 * `reviews` are not touched at all — this is about which row is pinned, not about content or about
 * who has learned it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('term_translations')
            ->join('terms', 'terms.id', '=', 'term_translations.term_id')
            ->whereNull('terms.deleted_at')
            ->where('term_translations.is_primary', true)
            ->orderBy('term_translations.id')
            ->get([
                'term_translations.id',
                'term_translations.term_id',
                'term_translations.lang',
                'term_translations.updated_at',
            ]);

        /** @var array<string, list<object>> $byTermAndLang */
        $byTermAndLang = [];
        foreach ($rows as $row) {
            $byTermAndLang[$row->term_id . '|' . $row->lang][] = $row;
        }

        $demote = [];
        foreach ($byTermAndLang as $group) {
            if (count($group) < 2) {
                continue;
            }

            $winner = $group[0];
            foreach ($group as $candidate) {
                if ((string) $candidate->updated_at > (string) $winner->updated_at) {
                    $winner = $candidate;
                }
            }

            foreach ($group as $candidate) {
                if ($candidate->id !== $winner->id) {
                    $demote[] = (string) $candidate->id;
                }
            }
        }

        if ($demote !== []) {
            DB::table('term_translations')
                ->whereIn('id', $demote)
                ->update(['is_primary' => false, 'updated_at' => now()]);
        }
    }

    /**
     * Deliberately a no-op. Re-creating a second primary would be re-creating the defect, and there
     * is nothing to restore it FROM: which of the two rows was doubled is not recorded anywhere, and
     * a card with one unambiguous question is not a state anyone wants rolled back.
     */
    public function down(): void {}
};

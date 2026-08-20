<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Port\TermCoreWriter;
use App\Modules\Vocabulary\Domain\ValueObject\Provenance;
use Illuminate\Support\Facades\DB;

final class EloquentTermCoreWriter implements TermCoreWriter
{
    public function replaceCore(
        TermId $termId,
        string $translation,
        string $translationLang,
        ?string $ipa,
        ?string $cefr,
        ?string $imageApiPrompt,
        Provenance $provenance,
    ): bool {
        return DB::transaction(function () use ($termId, $translation, $translationLang, $ipa, $cefr, $imageApiPrompt, $provenance): bool {
            $term = DB::table('terms')->where('id', $termId->value)->whereNull('deleted_at')->first(['id', 'lang']);
            if ($term === null) {
                return false;
            }

            $now = now();

            $changes = [
                'prompt_version' => $provenance->promptVersion,
                'generation_model' => $provenance->model,
                'updated_at' => $now,
            ];
            // A field the fresh core did not produce keeps what it had: an absent value is the model
            // saying nothing, not the model saying "empty". `text`, `type` and `normalized_text` are
            // never written here at all — the term IS the identity of the row, and rewriting it would
            // be a different term wearing this one's progress.
            if ($ipa !== null) {
                $changes['ipa'] = $ipa;
            }
            if ($cefr !== null) {
                $changes['cefr'] = $cefr;
            }
            if ($imageApiPrompt !== null) {
                $changes['image_api_prompt'] = $imageApiPrompt;
            }

            DB::table('terms')->where('id', $termId->value)->update($changes);

            $this->replacePrimaryTranslation($termId, (string) $term->lang, $translationLang, $translation, $provenance, $now);

            return true;
        });
    }

    /**
     * Rewrite the translation IN $translationLang and make it the term's ONLY primary one.
     *
     * The demotion is audit A7: nine store terms carry TWO `is_primary` rows for one term («stay
     * calm» → «Оставайтесь спокойны» AND «оставаться спокойным»), so the question on the card is
     * whichever row the query happened to return. A regeneration that wrote a third one would deepen
     * exactly that. The fresh core is the answer now; the older readings stay as rows (they may be
     * genuinely useful alternatives) but they stop competing to be the question.
     */
    private function replacePrimaryTranslation(
        TermId $termId,
        string $termLang,
        string $lang,
        string $text,
        Provenance $provenance,
        mixed $now,
    ): void {
        // The same total order a reader asking in this language would use, so the row that is
        // rewritten is the row the card was showing.
        $existing = TranslationPick::ordered(
            DB::table('term_translations')->where('term_id', $termId->value),
            $lang,
        )->first(['id']);

        $stamp = [
            'text' => $text,
            'lang' => $lang,
            'is_primary' => true,
            'prompt_version' => $provenance->promptVersion,
            'generation_model' => $provenance->model,
            'updated_at' => $now,
        ];

        if ($existing !== null) {
            DB::table('term_translations')->where('id', $existing->id)->update($stamp);
            $primaryId = (string) $existing->id;
        } else {
            $primaryId = Ulid::generate();
            DB::table('term_translations')->insert([
                'id' => $primaryId,
                'term_id' => $termId->value,
                ...$stamp,
                'created_at' => $now,
            ]);
        }

        // A7: exactly one primary per term, and it is the one just written.
        DB::table('term_translations')
            ->where('term_id', $termId->value)
            ->where('id', '!=', $primaryId)
            ->where('is_primary', true)
            ->update(['is_primary' => false, 'updated_at' => $now]);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Vocabulary\Infrastructure\Eloquent;

use App\Modules\Vocabulary\Application\Query\ExactTermTranslationReader;
use Illuminate\Support\Facades\DB;

final class EloquentExactTermTranslationReader implements ExactTermTranslationReader
{
    public function __construct(private readonly TranslationPick $pick = new TranslationPick()) {}

    public function translationFor(string $normalizedText, string $lang, string $nativeLang): ?string
    {
        if ($normalizedText === '') {
            return null;
        }

        // Terms are deduplicated on (lang, normalized_text, pos), so a word can legitimately have a
        // row per part of speech. Ordering by id makes the pick deterministic — the same word must
        // not hint one meaning today and another tomorrow.
        /** @var list<string> $termIds */
        $termIds = array_values(DB::table('terms')
            ->whereNull('deleted_at')
            ->where('lang', $lang)
            ->where('normalized_text', $normalizedText)
            ->orderBy('id')
            ->limit(4)
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all());

        if ($termIds === []) {
            return null;
        }

        // The same deterministic pick, STRICTLY in the pair's support language: the hint sits under
        // a field labelled with the pair, so a translation in a third language would contradict the
        // label (DECISIONS п. 146). Nothing in this language means no hint from our own catalogue,
        // and the ladder below carries on to the cache and the vendor, which do answer in the pair.
        $picked = $this->pick->forTermsInLanguage($termIds, $nativeLang);
        foreach ($termIds as $id) {
            $text = $picked[$id]['text'] ?? null;
            if (is_string($text) && trim($text) !== '') {
                return $text;
            }
        }

        return null;
    }

    public function termForTranslation(string $normalizedText, string $lang, string $nativeLang): ?string
    {
        if ($normalizedText === '') {
            return null;
        }

        // `lower(tt.text)` and not a stored normalized column: translations have none, and the free
        // search already matches them exactly this way — the two must agree, or the hint and the
        // list under it would disagree about whether we hold the word.
        $text = DB::table('term_translations as tt')
            ->join('terms as t', 't.id', '=', 'tt.term_id')
            ->whereNull('t.deleted_at')
            ->where('t.lang', $lang)
            ->where('tt.lang', $nativeLang)
            ->whereRaw('lower(tt.text) = ?', [$normalizedText])
            ->orderBy('t.id')
            ->value('t.text');

        return is_string($text) && trim($text) !== '' ? $text : null;
    }
}

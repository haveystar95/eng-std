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

        // The SAME deterministic language pick the card builder uses, so the hint under the search
        // field and the translation on the word's card are the same string. A hint that disagreed
        // with the card it previews would be worse than no hint.
        $picked = $this->pick->forTerms($termIds, $nativeLang);
        foreach ($termIds as $id) {
            $text = $picked[$id]['text'] ?? null;
            if (is_string($text) && trim($text) !== '') {
                return $text;
            }
        }

        return null;
    }
}

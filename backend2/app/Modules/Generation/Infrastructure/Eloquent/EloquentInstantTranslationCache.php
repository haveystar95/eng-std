<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Application\Port\InstantTranslationCache;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentInstantTranslationCache implements InstantTranslationCache
{
    public function find(string $normalizedText, string $langPair): ?string
    {
        $row = DB::table('instant_translations')
            ->where('normalized_text', $normalizedText)
            ->where('lang_pair', $langPair)
            ->value('translation');

        return is_string($row) && $row !== '' ? $row : null;
    }

    public function charactersUsedSince(string $provider, DateTimeImmutable $since): int
    {
        return (int) DB::table('instant_translations')
            ->where('provider', $provider)
            ->where('created_at', '>=', $since)
            ->sum('characters');
    }

    public function store(
        string $normalizedText,
        string $langPair,
        string $translation,
        string $provider,
        int $characters,
    ): void {
        // insertOrIgnore: two people typing the same word in the same second is the normal case on a
        // debounced field, and the loser of that race already holds the answer it is about to show.
        // Failing the request over a duplicate would turn a won race into a broken hint.
        DB::table('instant_translations')->insertOrIgnore([
            'id' => (string) Ulid::generate(),
            'normalized_text' => $normalizedText,
            'lang_pair' => $langPair,
            'translation' => $translation,
            'provider' => $provider,
            'characters' => $characters,
            'created_at' => now(),
        ]);
    }
}

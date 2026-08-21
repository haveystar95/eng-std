<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\WordLookupBrief;
use App\Modules\Generation\Application\Dto\WordLookupResult;
use App\Modules\Generation\Application\Port\WordLookupPort;

/**
 * Deterministic lookup — no network, no spend.
 *
 * Its answer deliberately SATISFIES the gates the real one has to pass: the description never
 * contains the query, the example does contain it, and every field is in the language its side of
 * the card is written in. A fake that produced content the barrier rejects would make every test
 * that uses it assert the refusal path instead of the happy one.
 */
final class FakeWordLookup implements WordLookupPort
{
    public function lookUp(WordLookupBrief $brief): WordLookupResult
    {
        $text = mb_strtolower(trim($brief->query));

        return new WordLookupResult(
            text: $text,
            type: str_contains($text, ' ') ? 'phrase' : 'word',
            translation: 'перевод',
            // No form of the query anywhere in it — the one rule the real description must keep.
            description: 'This is a common thing that people use every day.',
            example: 'We talked about ' . $text . ' at work yesterday.',
            exampleTranslation: 'Мы обсуждали это на работе вчера.',
            cefr: 'B1',
            transcription: null,
            model: 'fake',
            promptVersion: 'lookup.fake',
            tokensIn: 420,
            tokensOut: 120,
            costUsd: '0.000135',
        );
    }
}

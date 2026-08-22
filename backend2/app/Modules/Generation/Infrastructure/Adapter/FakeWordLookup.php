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
 *
 * That is also why it has a LEXICON. The real model answers a Russian query with an English card,
 * and the language barrier enforces exactly that; a fake that echoed the query back would be
 * refused by the barrier, and every reverse-direction test would be asserting the fake's own bug.
 */
final class FakeWordLookup implements WordLookupPort
{
    /**
     * The native-language queries the tests use, and the English words they mean.
     *
     * @var array<string, string>
     */
    private const LEXICON = [
        'случай' => 'occasion',
        'как дела' => 'how are you',
        'счёт' => 'invoice',
    ];

    /** Input with no word in it. The real prompt reserves `recognized: false` for exactly this. */
    private const GIBBERISH = ['asdfgh', 'йцукен', ';;;'];

    public function lookUp(WordLookupBrief $brief): WordLookupResult
    {
        $query = mb_strtolower(trim($brief->query));

        if (in_array($query, self::GIBBERISH, true)) {
            return new WordLookupResult(
                text: '', type: 'word', translation: '', description: '',
                example: null, exampleTranslation: null, cefr: null, transcription: null,
                imageApiPrompt: null, model: 'fake', promptVersion: 'lookup.fake',
                tokensIn: 180, tokensOut: 10, costUsd: '0.000034',
                notRecognized: true,
            );
        }

        $text = self::LEXICON[$query] ?? $query;

        return new WordLookupResult(
            text: $text,
            type: str_contains($text, ' ') ? 'phrase' : 'word',
            // A query the lexicon knew was the native-language one, so it IS the translation —
            // which is what makes «случай» and «occasion» visibly converge on one term.
            translation: isset(self::LEXICON[$query]) ? $query : 'перевод',
            // No form of the query anywhere in it — the one rule the real description must keep.
            description: 'This is a common thing that people use every day.',
            example: 'We talked about ' . $text . ' at work yesterday.',
            exampleTranslation: 'Мы обсуждали это на работе вчера.',
            cefr: 'B1',
            transcription: null,
            imageApiPrompt: 'office desk paperwork',
            model: 'fake',
            promptVersion: 'lookup.fake',
            tokensIn: 420,
            tokensOut: 120,
            costUsd: '0.000135',
        );
    }
}

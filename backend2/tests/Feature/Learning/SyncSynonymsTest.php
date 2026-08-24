<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * SYN-1 Ч.4 — the contract grows, it does not move.
 *
 * `synonyms` and `translations` are new keys beside the ones the app already reads; nothing that
 * existed changed name, meaning or contents. The regression diff of the whole `/sync` payload for a
 * term carrying every enrichment product is in `docs/syn-1-sync-diff.md` and shows exactly these two
 * additions.
 */
function seedSyncTerm(object $ctx): array
{
    [$user, $token] = learner();
    [, $termId] = seedCollectionWith($user, 'purpose', 'цель');

    seedExample([
        'id' => str_pad('01SYNEX', 26, '0'),
        'term_id' => $termId,
        'sentence' => 'The purpose of this meeting is to agree a date.',
        'translation' => 'Цель этой встречи — согласовать дату.',
        'source' => 'ai',
    ]);
    DB::table('term_translations')->insert([
        'id' => str_pad('01SYNT2', 26, '0'), 'term_id' => $termId, 'lang' => 'ru',
        'text' => 'задача', 'is_primary' => false, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_accepted_variants')->insert([
        'id' => str_pad('01SYNV1', 26, '0'), 'term_id' => $termId, 'text' => 'purposes',
        'note' => null, 'generator_version' => 'enrich-v1', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('term_synonyms')->insert([
        'id' => (string) Ulid::generate(), 'term_id' => $termId, 'text' => 'goal', 'lang' => 'en',
        'source' => 'auto', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$token, $termId];
}

it('carries synonyms and the full translation list, beside the fields that were already there', function () {
    [$token] = seedSyncTerm($this);

    $term = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/sync?since=1970-01-01T00:00:00Z')
        ->assertOk()
        ->json('data.changes.terms.0');

    expect($term['synonyms'])->toBe(['goal'])
        // Pinned first, so `translations[0]` is always the `translation` field.
        ->and($term['translations'])->toBe(['цель', 'задача'])
        ->and($term['translation'])->toBe('цель')
        // …and the field the device already grades against is untouched: variants only.
        ->and($term['accepted_variants'])->toBe(['purposes']);
});

it('sends empty lists rather than omitting the keys, so the client shape never varies', function () {
    [$user, $token] = learner();
    seedCollectionWith($user, 'invoice', 'счёт');

    $term = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/sync?since=1970-01-01T00:00:00Z')
        ->assertOk()
        ->json('data.changes.terms.0');

    expect($term['synonyms'])->toBe([])
        ->and($term['translations'])->toBe(['счёт']);
});

it('puts synonyms on a card that asked for the meaning, and on no other', function () {
    [$token, $termId] = seedSyncTerm($this);

    // The whole fan for this pair, so both kinds of card are in one answer.
    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['term_id' => $termId, 'practice' => true])
        ->assertOk()
        ->json('data.cards');

    foreach ($cards as $card) {
        $expected = in_array(
            $card['exercise_mode'],
            ['multiple_choice', 'word_bank', 'typing', 'description_match', 'speaking'],
            true,
        ) ? ['goal'] : [];

        // `speaking` and the sentence modes read off the rung; a practice fan on a fresh pair deals
        // the word form, so the expectation above holds for every card this call returns.
        expect($card['synonyms'])->toBe($expected, "mode {$card['exercise_mode']}");
    }

    expect($cards)->not->toBeEmpty();
});

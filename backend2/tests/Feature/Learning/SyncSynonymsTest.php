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
 * term carrying every enrichment product is in `docs/syn-1-findings.md` and shows exactly these two
 * additions.
 */
/**
 * A graduated, well-reviewed pair, so the matrix admits every trainer.
 *
 * Its own copy rather than PracticeFanTest's `graduate()`: Pest's helpers are plain global
 * functions, so borrowing one only works while both files land in the SAME worker — which they do
 * serially and do not under `--parallel`. A test that passes alone and fails in the suite is worse
 * than a duplicated fixture.
 */
function graduateForSync(string $userId, string $termId): void
{
    DB::table('user_term_progress')->updateOrInsert(
        ['user_id' => $userId, 'term_id' => $termId],
        [
            'state' => 'review',
            'acquisition' => 'graduated',
            'learning_step' => 0,
            'reps' => 12,
            'successful_reviews' => 12,
            'lapses' => 0,
            'ease_factor' => 2.5,
            'interval_days' => 10,
            'due_at' => now()->addDays(3),
            'enrolled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    );
}

function seedSyncTerm(object $ctx): array
{
    [$user, $token] = learner();
    [$collectionId, $termId] = seedCollectionWith($user, 'purpose', 'цель');

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
    // The reading hint is keyed by the SUPPORT language, not the term's — «purpose» read by a
    // Russian speaker. That is the whole reason it lives in its own table (SYN-1d Ч.7).
    DB::table('term_transliterations')->insert([
        'id' => (string) Ulid::generate(), 'term_id' => $termId, 'text' => 'пёрпэс', 'lang' => 'ru',
        'source' => 'auto', 'generator_version' => 'v15', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return [$user, $token, $collectionId, $termId];
}

it('carries synonyms and the full translation list, beside the fields that were already there', function () {
    [, $token] = seedSyncTerm($this);

    $term = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/sync?since=1970-01-01T00:00:00Z')
        ->assertOk()
        ->json('data.changes.terms.0');

    expect($term['synonyms'])->toBe(['goal'])
        // Picked by the pair's SUPPORT language, and beside `transcription` (IPA), never instead
        // of it — the two are different products for different moments.
        ->and($term['transliteration'])->toBe('пёрпэс')
        ->and($term['transcription'])->toBeNull()
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
        ->and($term['translations'])->toBe(['счёт'])
        // Null rather than an absent key: the client shape never varies.
        ->and($term['transliteration'])->toBeNull();
});

it('puts synonyms on a card that asked for the meaning, and on no other', function () {
    [$user, $token, $collectionId, $termId] = seedSyncTerm($this);
    // High enough on the ladder that the matrix admits every trainer, so one call returns both
    // kinds of card — the ones whose prompt is the meaning and the ones whose prompt is the word.
    graduateForSync($user->id, $termId);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionId, 'practice' => true])
        ->assertOk()
        ->json('data.cards');

    $meaningModes = ['multiple_choice', 'word_bank', 'typing', 'description_match', 'speaking'];
    foreach ($cards as $card) {
        // A card that asks for the pinned EXAMPLE carries none whatever its mode — the key there is
        // the sentence, and a synonym of the term is not a spelling of it.
        $asksExample = $card['answer'] !== 'purpose';
        $expected = ! $asksExample && in_array($card['exercise_mode'], $meaningModes, true) ? ['goal'] : [];

        expect($card['synonyms'])->toBe($expected, "mode {$card['exercise_mode']}");
    }

    // The fan really did deal both kinds, or the loop above proved nothing.
    $modes = array_column($cards, 'exercise_mode');
    expect(array_intersect($modes, $meaningModes))->not->toBeEmpty()
        ->and(array_diff($modes, $meaningModes))->not->toBeEmpty();
});

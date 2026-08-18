<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\AddWordToCollection;
use App\Modules\Collections\Application\Command\AddWordToCollectionHandler;
use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// learner(), seedWordFor(), seedCollectionWith() live in tests/Pest.php — shared with sibling
// Learning/Vocabulary Feature tests, and loaded regardless of parallel worker/file order.

// answerTimes() lives in tests/Pest.php — shared with IntroExposureTest, and loaded regardless of
// parallel worker/file order.

/** How many cards one never-seen term occupies in a session: its remaining ladder chain. */
const LADDER_CARDS_PER_NEW_TERM = 2; // intro ships switched off, so the chain is rung 1 + rung 2

it('submits reviews, creating progress and daily stats', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'typing', 'response' => 'apple',
            'answered_at' => now()->toIso8601String(), 'client_seq' => 1,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1)
        ->assertJsonPath('data.unknown', 0);

    $this->assertDatabaseHas('user_term_progress', ['user_id' => $user->id, 'term_id' => $termId]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/stats')
        ->assertOk()
        ->assertJsonPath('data.total_terms', 1)
        ->assertJsonPath('data.reviews_today', 1);
});

it('accepts a null response (a «не помню» / blank answer) instead of 422-losing it (F21)', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'typing', 'response' => null,
            'answered_at' => now()->toIso8601String(), 'client_seq' => 1,
        ]]])
        ->assertOk() // was 422 before the fix — the client then dropped it and the review was lost
        ->assertJsonPath('data.accepted', 1);

    $this->assertDatabaseHas('user_term_progress', ['user_id' => $user->id, 'term_id' => $termId]);
});

it('ignores a re-uploaded review batch', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user);
    $batch = ['reviews' => [[
        'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'typing', 'response' => 'apple',
        'answered_at' => now()->toIso8601String(), 'client_seq' => 1,
    ]]];

    $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/reviews/batch', $batch)->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', $batch)
        ->assertOk()
        ->assertJsonPath('data.accepted', 0)
        ->assertJsonPath('data.duplicates', 1);
});

it('reports unknown terms in a batch', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => Ulid::generate(), 'exercise_mode' => 'typing', 'response' => 'whatever',
            'answered_at' => now()->toIso8601String(), 'client_seq' => 1,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 0)
        ->assertJsonPath('data.unknown', 1);
});

it('has no due cards before anything is studied', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->assertJsonStructure(['data' => ['session_id', 'cards']])
        ->assertJsonPath('data.cards', []);
});

it('lists a due card once it is overdue, with hydrated content', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'withdraw cash', 'снять наличные');

    // Two recognition steps walk it off the ladder, then one graded answer 5 days ago enters SM-2:
    // new + good → learning (interval 1 day) → due ~4 days ago.
    answerTimes($this, $token, $termId, 'withdraw cash', times: 3, lastDaysAgo: 5);

    // Due, at rung 3 (graduated, no reviews yet) → the assembly rungs; a two-word answer is a word
    // bank. Prompt is the translation, answer the target text.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->assertJsonPath('data.cards.0.term_id', $termId)
        ->assertJsonPath('data.cards.0.exercise_mode', 'word_bank')
        ->assertJsonPath('data.cards.0.answer', 'withdraw cash')
        ->assertJsonPath('data.cards.0.prompt', 'снять наличные');
});

it('validates the review batch', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reviews');
});

it('offers unreviewed collection terms as new study cards', function () {
    [$user, $token] = learner();
    $apple = seedWordFor($user, 'apple', 'яблоко');
    $bank = seedWordFor($user, 'bank', 'банк');

    // A new term is INTRODUCED AND ANSWERED in the same session: each brings its recognition chain,
    // so two words fill four slots. Both rungs are multiple_choice; the direction differs.
    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->assertJsonCount(2 * LADDER_CARDS_PER_NEW_TERM, 'data.cards')
        ->assertJsonPath('data.cards.0.exercise_mode', 'multiple_choice')
        ->json('data.cards');

    expect(array_column($cards, 'term_id'))->toEqualCanonicalizing([$apple, $apple, $bank, $bank]);
    // Rung 1 is graded by identity, so ITS answer is the term id; rung 2 is the term's own text.
    expect(array_column($cards, 'ladder_step'))->toEqualCanonicalizing([1, 1, 2, 2]);
    $reverse = array_values(array_filter($cards, static fn (array $c): bool => $c['ladder_step'] === 2));
    expect(array_column($reverse, 'answer'))->toEqualCanonicalizing(['apple', 'bank']);
});

it('drops a term from the new pool once it has progress', function () {
    [$user, $token] = learner();
    $apple = seedWordFor($user, 'apple', 'яблоко');
    $bank = seedWordFor($user, 'bank', 'банк');

    // Study "apple" through the ladder and one SRS review → scheduled a day out, no longer new and
    // not yet due. Only "bank" is left, and it brings its own chain.
    answerTimes($this, $token, $apple, 'apple', times: 3);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->assertJsonCount(LADDER_CARDS_PER_NEW_TERM, 'data.cards')
        ->assertJsonPath('data.cards.0.exercise_mode', 'multiple_choice')
        ->json('data.cards');

    expect(array_unique(array_column($cards, 'term_id')))->toBe([$bank]);
});

it('scopes the due session to one collection', function () {
    [$user, $token] = learner();
    // Two words in A, because a recognition card needs a neighbour to offer beside the answer and a
    // deck of one is refused now (QA-15). Both must be A's, which is the point of the case.
    [$collectionA, $apple] = seedCollectionWith($user, 'apple');
    addWordTo($collectionA, $user->id, 'pear', 'груша');
    seedCollectionWith($user, 'bank'); // a second collection, must not leak in

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $collectionA])
        ->assertOk()
        ->json('data.cards');

    expect($cards)->toHaveCount(2 * LADDER_CARDS_PER_NEW_TERM);
    expect(array_unique(array_column($cards, 'term_id')))->toHaveCount(2); // the other collection never leaks in

    $own = array_values(array_filter($cards, static fn (array $c): bool => $c['term_id'] === $apple));
    expect($own[1]['answer'])->toBe('apple'); // rung 2 asks for the term itself
});

it('reports per-collection progress (learned once a term graduates)', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'apple', 'яблоко');

    // Two recognition steps off the ladder, then two good answers in order: new → learning → review.
    answerTimes($this, $token, $termId, 'apple', times: 4, lastDaysAgo: 5);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/study/progress')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.terms_total', 1)
        ->assertJsonPath('data.0.in_progress_count', 1) // review, interval 4 (< 21) → in progress
        ->assertJsonPath('data.0.mastered_count', 0);
});

it('caps new cards at the profile daily new-term quota', function () {
    [$user, $token] = learner();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 1]);
    seedWordFor($user, 'apple', 'яблоко');
    seedWordFor($user, 'bank', 'банк');

    // Two new terms are available, but the user's daily quota is 1 — so one word, and the cards are
    // its ladder chain rather than one card each for two words.
    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->assertJsonCount(LADDER_CARDS_PER_NEW_TERM, 'data.cards')
        ->json('data.cards');

    expect(array_unique(array_column($cards, 'term_id')))->toHaveCount(1);
});

it('introduces no new cards when the daily goal is zero', function () {
    [$user, $token] = learner();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 0]);
    seedWordFor($user, 'apple', 'яблоко');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->assertJsonPath('data.cards', []);
});

it('rejects an answer for a term outside the session composition', function () {
    [$user, $token] = learner();
    [$colA, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    [, $bank] = seedCollectionWith($user, 'bank', 'банк');

    // Session scoped to collection A → its fixed composition is [apple].
    $sessionId = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $colA])
        ->assertOk()
        ->json('data.session_id');

    // bank is a real term but not in this session → rejected; apple is in it → accepted.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [
            ['id' => Ulid::generate(), 'term_id' => $bank, 'exercise_mode' => 'multiple_choice', 'response' => 'bank', 'answered_at' => now()->toIso8601String(), 'session_id' => $sessionId, 'client_seq' => 1],
            ['id' => Ulid::generate(), 'term_id' => $apple, 'exercise_mode' => 'multiple_choice', 'response' => 'apple', 'answered_at' => now()->toIso8601String(), 'session_id' => $sessionId, 'client_seq' => 2],
        ]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1)
        ->assertJsonPath('data.unknown', 1);
});

it('records a free-practice answer: streak counts, but progress is untouched', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'apple', 'яблоко');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'typing', 'response' => 'apple',
            'answered_at' => now()->toIso8601String(), 'is_practice' => true, 'client_seq' => 1,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);

    // Never scheduled → no progress row; but logged and counted toward the day's reviews (streak).
    $this->assertDatabaseMissing('user_term_progress', ['user_id' => $user->id, 'term_id' => $termId]);
    $this->assertDatabaseHas('reviews', ['term_id' => $termId, 'is_practice' => true]);
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/stats')
        ->assertOk()
        ->assertJsonPath('data.reviews_today', 1)
        ->assertJsonPath('data.total_terms', 0);
});

it('does not double the daily new-term quota across two scoped sessions', function () {
    [$user, $token] = learner();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 1]);
    [$colA, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    [$colB] = seedCollectionWith($user, 'bank', 'банк');

    // Session A offers its one allowed new card; answering it spends the day's global quota.
    $sessionA = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $colA])
        ->assertOk()
        ->assertJsonCount(LADDER_CARDS_PER_NEW_TERM, 'data.cards')
        ->json('data.session_id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $apple, 'exercise_mode' => 'multiple_choice', 'response' => 'apple',
            'answered_at' => now()->toIso8601String(), 'session_id' => $sessionA, 'client_seq' => 1,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);

    // A different collection, same day → no new cards: the quota is one per user, not per collection.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $colB])
        ->assertOk()
        ->assertJsonPath('data.cards', []);
});

// ── free practice (device-batch F17): drill the whole scope on demand ────────────

it('offers every scope term as a practice card, ignoring due_at and the daily quota', function () {
    [$user, $token] = learner();
    // daily_goal 0 → a normal session introduces nothing; practice must ignore the quota entirely.
    Profile::create(['user_id' => $user->id, 'daily_goal' => 0]);
    [$colA] = seedCollectionWith($user, 'apple', 'яблоко');
    app(AddWordToCollectionHandler::class)(new AddWordToCollection(
        \App\Modules\Shared\Domain\ValueObject\CollectionId::fromString($colA),
        UserId::fromString($user->id),
        'bank',
        'банк',
    ));

    // A normal scoped session is empty (quota 0, nothing due)…
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $colA])
        ->assertOk()
        ->assertJsonPath('data.cards', []);

    // …but practice drills both never-studied terms. Practice fans across every applicable mode
    // (not the reps ladder): single words with no example → multiple_choice / typing / listening.
    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $colA, 'practice' => true])
        ->assertOk()
        ->json('data.cards');

    expect($cards)->toHaveCount(2);
    expect(array_column($cards, 'answer'))->toEqualCanonicalizing(['apple', 'bank']);
    // No word_bank (single word) and no cloze (no example); the rest of the enabled set is fair game.
    expect(array_column($cards, 'exercise_mode'))
        ->each->toBeIn(['multiple_choice', 'typing', 'listening']);
});

it('includes a studied-but-not-due term in practice (which the normal session withholds)', function () {
    [$user, $token] = learner();
    [$colA, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    // A neighbour, so a choice card can be built at all (QA-15). It stays NEW, so it is what the
    // normal session below still offers — the assertion is about `apple` either way.
    addWordTo($colA, $user->id, 'pear', 'груша');

    // Walk it off the ladder and give it one SRS review → scheduled a day out. A normal session no
    // longer offers it today (a pair mid-ladder WOULD still be offered — being unfinished outranks
    // being due — which is exactly why this has to graduate the term first)…
    answerTimes($this, $token, $apple, 'apple', times: 3);

    $due = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $colA])
        ->assertOk()
        ->json('data.cards');
    expect(array_column($due, 'term_id'))->not->toContain($apple); // not due, already studied

    // …but practice drills it regardless of due_at. The mode is fanned (not the ladder); for a
    // single word with no example that's one of multiple_choice / typing / listening.
    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $colA, 'practice' => true])
        ->assertOk()
        ->json('data.cards');

    $own = array_values(array_filter($cards, static fn (array $c): bool => $c['term_id'] === $apple));
    expect($own)->toHaveCount(1)
        ->and($own[0]['exercise_mode'])->toBeIn(['multiple_choice', 'typing', 'listening']);
});

it('does not spend the daily new-term quota during practice', function () {
    [$user, $token] = learner();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 5]);
    [$colA, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    // A neighbour, so a choice card can be built at all (QA-15).
    addWordTo($colA, $user->id, 'pear', 'груша');

    // Answer the practice card…
    $sessionId = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $colA, 'practice' => true])
        ->assertOk()
        ->json('data.session_id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $apple, 'exercise_mode' => 'multiple_choice', 'response' => 'apple',
            'answered_at' => now()->toIso8601String(), 'session_id' => $sessionId, 'is_practice' => true, 'client_seq' => 1,
        ]]])
        ->assertOk();

    // …the term is still new (no progress written), so a normal session still offers it as new.
    $this->assertDatabaseMissing('user_term_progress', ['user_id' => $user->id, 'term_id' => $apple]);
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $colA])
        ->assertOk()
        ->assertJsonCount(2 * LADDER_CARDS_PER_NEW_TERM, 'data.cards')  // still never introduced
        ->assertJsonPath('data.cards.0.exercise_mode', 'multiple_choice');
});

it('reports the new-term quota state on /stats (F13)', function () {
    [$user, $token] = learner();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 3]);
    $apple = seedWordFor($user, 'apple', 'яблоко');
    $bank = seedWordFor($user, 'bank', 'банк');

    // Introduce two new terms via study answers (quota gates the session build, not what a
    // submitted answer introduces).
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [
            ['id' => Ulid::generate(), 'term_id' => $apple, 'exercise_mode' => 'typing', 'response' => 'apple', 'answered_at' => now()->toIso8601String(), 'client_seq' => 1],
            ['id' => Ulid::generate(), 'term_id' => $bank, 'exercise_mode' => 'typing', 'response' => 'bank', 'answered_at' => now()->toIso8601String(), 'client_seq' => 2],
        ]])->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/stats')
        ->assertOk()
        ->assertJsonPath('data.new_goal', 3)
        ->assertJsonPath('data.new_today', 2)
        ->assertJsonPath('data.new_remaining', 1); // 3 goal − 2 introduced
});

it('reports a zero new-remaining once the daily new quota is spent (F13)', function () {
    [$user, $token] = learner();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 1]);
    $apple = seedWordFor($user, 'apple', 'яблоко');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [
            ['id' => Ulid::generate(), 'term_id' => $apple, 'exercise_mode' => 'typing', 'response' => 'apple', 'answered_at' => now()->toIso8601String(), 'client_seq' => 1],
        ]])->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/stats')
        ->assertOk()
        ->assertJsonPath('data.new_goal', 1)
        ->assertJsonPath('data.new_today', 1)
        ->assertJsonPath('data.new_remaining', 0);
});

it('requires authentication for stats', function () {
    $this->getJson('/api/v1/stats')->assertUnauthorized();
});

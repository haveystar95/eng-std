<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// learner() / seedCollectionWith() / addWordTo() / answerTimes() live in tests/Pest.php.

/**
 * THE POOL: the learner's own list of words being studied, separate from the catalogue of topics
 * their collections are.
 *
 * The two doors in are a triage swipe (covered by TriageApiTest) and «Учить это слово» — this
 * endpoint. The one door out is «Убрать из изучения», which is a PAUSE: the tests below are mostly
 * about what it does NOT do.
 */

it('never deals a word that is only in the catalogue', function () {
    [$user, $token] = learner();
    [$col] = seedCollectionWith($user, 'apple', 'яблоко', enroll: false);
    addWordTo($col, $user->id, 'bank', 'банк', enroll: false);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $col])
        ->assertOk()
        ->assertJsonPath('data.cards', []);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $col, 'practice' => true])
        ->assertOk()
        ->assertJsonPath('data.cards', []);
});

it('enrols a term and then deals it — and says whether the call changed anything', function () {
    [$user, $token] = learner();
    [$col, $apple] = seedCollectionWith($user, 'apple', 'яблоко', enroll: false);
    addWordTo($col, $user->id, 'bank', 'банк', enroll: false); // a neighbour, so a choice card exists

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/v1/pool/terms/{$apple}")
        ->assertOk()
        ->assertJsonPath('data.enrolled', true)
        ->assertJsonPath('data.changed', true);

    // Idempotent: a second tap (or a replayed offline request) is a 200 that moved nothing.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/v1/pool/terms/{$apple}")
        ->assertOk()
        ->assertJsonPath('data.changed', false);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['collection_id' => $col])
        ->assertOk()
        ->json('data.cards');

    expect(array_unique(array_column($cards, 'term_id')))->toBe([$apple]);
});

it('keeps the first enrolment moment when a word is enrolled twice', function () {
    [$user, $token] = learner();
    $apple = seedWordFor($user, 'apple', 'яблоко', enroll: false);

    $this->withHeader('Authorization', "Bearer {$token}")->putJson("/api/v1/pool/terms/{$apple}")->assertOk();
    $first = DB::table('user_term_progress')->where('term_id', $apple)->value('enrolled_at');

    $this->travel(2)->days();
    $this->withHeader('Authorization', "Bearer {$token}")->putJson("/api/v1/pool/terms/{$apple}")->assertOk();

    expect(DB::table('user_term_progress')->where('term_id', $apple)->value('enrolled_at'))->toBe($first);
});

it('pauses a word: it leaves the sessions, and its whole history stands', function () {
    [$user, $token] = learner();
    [$col, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    addWordTo($col, $user->id, 'bank', 'банк');

    // Walk it off the recognition rungs and give it one real review, so there IS a history to lose.
    answerTimes($this, $token, $apple, 'apple', times: 3);
    $before = DB::table('user_term_progress')->where('term_id', $apple)->first();
    $reviewsBefore = DB::table('reviews')->where('term_id', $apple)->count();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/pool/terms/{$apple}")
        ->assertOk()
        ->assertJsonPath('data.enrolled', false)
        ->assertJsonPath('data.changed', true);

    $after = DB::table('user_term_progress')->where('term_id', $apple)->first();
    expect($after?->enrolled_at)->toBeNull()
        // …and NOTHING else moved. This is the whole promise «слово можно вернуть в любой момент».
        ->and($after?->state)->toBe($before?->state)
        ->and($after?->acquisition)->toBe($before?->acquisition)
        ->and($after?->learning_step)->toBe($before?->learning_step)
        ->and($after?->successful_reviews)->toBe($before?->successful_reviews)
        ->and($after?->reps)->toBe($before?->reps)
        ->and($after?->interval_days)->toBe($before?->interval_days)
        ->and($after?->due_at)->toBe($before?->due_at)
        ->and(DB::table('reviews')->where('term_id', $apple)->count())->toBe($reviewsBefore);

    // Gone from the trainer, both kinds of session.
    $due = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')->assertOk()->json('data.cards');
    expect(array_column($due, 'term_id'))->not->toContain($apple);

    $practice = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions', ['practice' => true])->assertOk()->json('data.cards');
    expect(array_column($practice, 'term_id'))->not->toContain($apple);
});

it('resumes a returned word at the rung it left with', function () {
    [$user, $token] = learner();
    [$col, $apple] = seedCollectionWith($user, 'apple', 'яблоко');
    addWordTo($col, $user->id, 'bank', 'банк');

    answerTimes($this, $token, $apple, 'apple', times: 3, lastDaysAgo: 5); // graduated and overdue
    $rung = DB::table('user_term_progress')->where('term_id', $apple)->first();

    $this->withHeader('Authorization', "Bearer {$token}")->deleteJson("/api/v1/pool/terms/{$apple}")->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")->putJson("/api/v1/pool/terms/{$apple}")->assertOk();

    $back = DB::table('user_term_progress')->where('term_id', $apple)->first();
    expect($back?->acquisition)->toBe($rung?->acquisition)
        ->and($back?->successful_reviews)->toBe($rung?->successful_reviews)
        ->and($back?->due_at)->toBe($rung?->due_at)
        ->and($back?->enrolled_at)->not->toBeNull();

    // …and it is dealt again, as the graduated pair it always was — not as a first meeting.
    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')->assertOk()->json('data.cards');
    $own = array_values(array_filter($cards, static fn (array $c): bool => $c['term_id'] === $apple));
    expect($own)->not->toBe([])
        ->and($own[0]['ladder_step'])->toBeGreaterThanOrEqual(3);
});

it('un-enrolling a word that was never in the pool is a no-op, not an error', function () {
    [$user, $token] = learner();
    $apple = seedWordFor($user, 'apple', 'яблоко', enroll: false);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/pool/terms/{$apple}")
        ->assertOk()
        ->assertJsonPath('data.changed', false);

    $this->assertDatabaseMissing('user_term_progress', ['term_id' => $apple]);
});

it('refuses to enrol a term that does not exist', function () {
    [, $token] = learner();
    $ghost = Ulid::generate();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/api/v1/pool/terms/{$ghost}")
        ->assertOk()
        ->assertJsonPath('data.changed', false);

    $this->assertDatabaseCount('user_term_progress', 0);
});

it('404s on a term id that is not a ULID', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/v1/pool/terms/not-a-ulid')
        ->assertNotFound();
});

it('never enrols another user’s pair', function () {
    [$owner] = learner();
    [$intruder, $intruderToken] = learner();
    $apple = seedWordFor($owner, 'apple', 'яблоко', enroll: false);

    // Terms are global (deduplicated), so the intruder CAN enrol one — into their OWN pool. What
    // must not happen is the owner's pair moving.
    $this->withHeader('Authorization', "Bearer {$intruderToken}")
        ->putJson("/api/v1/pool/terms/{$apple}")
        ->assertOk();

    expect(DB::table('user_term_progress')->where('user_id', $intruder->id)->where('term_id', $apple)->count())->toBe(1)
        ->and(DB::table('user_term_progress')->where('user_id', $owner->id)->count())->toBe(0);
});

it('requires authentication', function () {
    $this->putJson('/api/v1/pool/terms/' . Ulid::generate())->assertUnauthorized();
    $this->deleteJson('/api/v1/pool/terms/' . Ulid::generate())->assertUnauthorized();
});

it('walks a word from a swipe to a rung: triage «не знаю» → pool → session → progress', function () {
    // The whole chapter in one case, over HTTP, exactly as the device drives it.
    [$user, $token] = learner();
    [$col, $antipyretic] = seedCollectionWith($user, 'antipyretic', 'жаропонижающее', enroll: false);
    $painkiller = addWordTo($col, $user->id, 'painkiller', 'обезболивающее', enroll: false);

    // 1. Nothing is studied yet, so the trainer has nothing to deal.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->assertJsonPath('data.cards', []);

    // 2. The swipe pass: both words «не знаю» — the learner deciding, word by word.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [
            ['id' => Ulid::generate(), 'term_id' => $antipyretic, 'verdict' => 'unknown', 'collection_id' => $col,
                'decided_at' => now()->toIso8601String(), 'client_seq' => 1],
            ['id' => Ulid::generate(), 'term_id' => $painkiller, 'verdict' => 'unknown', 'collection_id' => $col,
                'decided_at' => now()->toIso8601String(), 'client_seq' => 2],
        ]])->assertOk()->assertJsonPath('data.accepted', 2);

    expect(DB::table('user_term_progress')->whereNotNull('enrolled_at')->count())->toBe(2);

    // 3. The session now deals them — a first meeting brings its whole recognition CHAIN, starting
    //    at the rung this learner's trainers open at (the intro trainer ships switched off, so that
    //    is the forward recognition; with it on, the chain would start one rung lower).
    $session = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->json('data');

    $own = array_values(array_filter(
        $session['cards'],
        static fn (array $c): bool => $c['term_id'] === $antipyretic,
    ));
    expect($own)->not->toBe([])
        ->and($own[0]['exercise_mode'])->toBe('multiple_choice')
        ->and($own[0]['ladder_step'])->toBe(1);

    // 4. Playing it moves the pair: the forward recognition is answered (a TAP, graded by identity,
    //    so the response is the tapped option's term id) and the rung climbs. Progress goes, and it
    //    goes on a word the learner chose.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', [
            'reviews' => [[
                'id' => Ulid::generate(), 'term_id' => $antipyretic, 'exercise_mode' => 'multiple_choice',
                'ladder_step' => 1, 'response' => $antipyretic, 'answered_at' => now()->toIso8601String(),
                'session_id' => $session['session_id'], 'client_seq' => 3,
            ]],
        ])->assertOk();

    $row = DB::table('user_term_progress')->where('term_id', $antipyretic)->first();
    expect($row?->acquisition)->toBe('learning')
        ->and($row?->learning_step)->toBe(2)
        ->and($row?->enrolled_at)->not->toBeNull();

    // …and the day's new-word budget was spent by the MEETING, not by the decision to study it.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/stats')
        ->assertOk()
        ->assertJsonPath('data.new_today', 1);
});

it('still checks a «знаю» verification even though the pair is out of the pool', function () {
    [$user, $token] = learner();
    [$col, $money] = seedCollectionWith($user, 'money', 'деньги', enroll: false);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/triage/batch', ['triages' => [[
            'id' => Ulid::generate(), 'term_id' => $money, 'verdict' => 'known', 'collection_id' => $col,
            'decided_at' => now()->toIso8601String(), 'client_seq' => 1,
        ]]])->assertOk();

    expect(DB::table('user_term_progress')->where('term_id', $money)->value('enrolled_at'))->toBeNull();

    // Its check is not a pool card — it is the system auditing a claim — so it rides the session
    // anyway on the day it comes due. Losing it would mean a «знаю» swipe is never questioned.
    DB::table('user_term_progress')->where('term_id', $money)->update(['due_at' => now()->subDay()]);

    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')->assertOk()->json('data.cards');

    expect(array_column($cards, 'term_id'))->toContain($money);
});

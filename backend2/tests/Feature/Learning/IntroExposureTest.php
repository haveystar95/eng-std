<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Learning\Application\Port\EnabledModesWriter;
use App\Modules\Learning\Domain\ValueObject\EnabledModes;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Switch the intro trainer on globally — it ships OFF, per the release rule. */
function enableIntro(): void
{
    app(EnabledModesWriter::class)->setGlobalDefault(new EnabledModes([
        ExerciseMode::Intro, ExerciseMode::MultipleChoice, ExerciseMode::WordBank, ExerciseMode::Typing,
    ]));
}

/** @return array<string, mixed> */
function exposure(string $termId, ?string $sessionId = null, string $shownAt = 'now'): array
{
    return array_filter([
        'term_id' => $termId,
        'shown_at' => ($shownAt === 'now' ? now() : new DateTimeImmutable($shownAt))->format(DATE_ATOM),
        'session_id' => $sessionId,
    ], static fn (mixed $v): bool => $v !== null);
}

it('deals the intro as the first card of a never-seen word, and asks nothing on it', function () {
    [$user, $token] = learner();
    $apple = seedWordFor($user, 'apple', 'яблоко');
    // A neighbour, because the two recognition cards need something to offer beside the answer and
    // a deck of one is refused now (QA-15). The chain below is read off `apple`'s own cards.
    seedWordFor($user, 'bank', 'банк');
    enableIntro();

    $all = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->json('data.cards');

    $cards = array_values(array_filter($all, static fn (array $c): bool => $c['term_id'] === $apple));

    // The whole chain lands in one session: shown, then asked twice.
    expect(array_column($cards, 'ladder_step'))->toBe([0, 1, 2])
        ->and($cards[0]['exercise_mode'])->toBe('intro')
        ->and($cards[0]['prompt'])->toBe('яблоко')
        ->and($cards[0]['answer'])->toBe('apple')
        ->and($cards[0]['options'])->toBeNull()
        ->and($cards[0]['chips'])->toBeNull();
});

it('records an intro as an exposure, never as a review', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'apple', 'яблоко');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['exposures' => [exposure($termId)]])
        ->assertOk()
        ->assertJsonPath('data.exposures', 1)
        ->assertJsonPath('data.accepted', 0);

    // The review log holds real retrievals only — an intro asks for nothing, so nothing is there.
    $this->assertDatabaseCount('reviews', 0);
    $this->assertDatabaseHas('term_exposures', ['user_id' => $user->id, 'term_id' => $termId]);
    // …and the pair stepped off rung 0 without the scheduler being touched.
    $this->assertDatabaseHas('user_term_progress', [
        'term_id' => $termId, 'acquisition' => 'learning', 'learning_step' => 1,
        'state' => 'new', 'interval_days' => 0, 'reps' => 0, 'due_at' => null,
    ]);
});

it('is idempotent on the PAIR — a re-uploaded intro changes nothing and keeps the first shown_at', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'apple', 'яблоко');
    $first = now()->subHour();

    $batch = ['exposures' => [exposure($termId, shownAt: $first->toIso8601String())]];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/reviews/batch', $batch)->assertOk();

    // The same pair again, later — a device that lost its acknowledgement, not a second meeting.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['exposures' => [exposure($termId, shownAt: now()->toIso8601String())]])
        ->assertOk()
        ->assertJsonPath('data.exposures', 0);

    $this->assertDatabaseCount('term_exposures', 1);
    expect((string) DB::table('term_exposures')->where('term_id', $termId)->value('shown_at'))
        ->toStartWith($first->utc()->format('Y-m-d H:i'));
});

it('does not push a pair back down the ladder when its intro is re-uploaded', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'apple', 'яблоко');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['exposures' => [exposure($termId)]])->assertOk();
    // …the learner then passes the forward recognition…
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'multiple_choice',
            'response' => $termId, 'ladder_step' => 1,
            'answered_at' => now()->toIso8601String(), 'client_seq' => 1,
        ]]])->assertOk()->assertJsonPath('data.accepted', 1);

    $this->assertDatabaseHas('user_term_progress', ['term_id' => $termId, 'learning_step' => 2]);

    // …and only now does the stale exposure arrive again.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['exposures' => [exposure($termId)]])->assertOk();

    $this->assertDatabaseHas('user_term_progress', ['term_id' => $termId, 'learning_step' => 2]);
});

it('grades the forward-recognition card by identity — the tapped option id, never a translation', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'apple', 'яблоко');
    $other = seedWordFor($user, 'bank', 'банк');

    // The learner taps the option belonging to this term → correct.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'multiple_choice',
            'response' => $termId, 'ladder_step' => 1,
            'answered_at' => now()->toIso8601String(), 'client_seq' => 1,
        ]]])->assertOk();

    expect(DB::table('reviews')->where('term_id', $termId)->value('grade'))->not->toBe('again');

    // Tapping a neighbour's option → wrong, and the pair stays on the same rung to be re-queued.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $other, 'exercise_mode' => 'multiple_choice',
            'response' => $termId, 'ladder_step' => 1,
            'answered_at' => now()->toIso8601String(), 'client_seq' => 2,
        ]]])->assertOk();

    expect(DB::table('reviews')->where('term_id', $other)->value('grade'))->toBe('again');
    $this->assertDatabaseHas('user_term_progress', [
        'term_id' => $other, 'acquisition' => 'new', 'learning_step' => 0, 'interval_days' => 0, 'due_at' => null,
    ]);
});

it('adopts an offline practice session named by an exposure, like it does for answers', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'apple', 'яблоко');
    $sessionId = Ulid::generate(); // minted on the device, never seen by the server

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', [
            'exposures' => [exposure($termId, sessionId: $sessionId)],
            'reviews' => [[
                'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'typing', 'response' => 'apple',
                'answered_at' => now()->toIso8601String(), 'session_id' => $sessionId,
                'is_practice' => true, 'client_seq' => 1,
            ]],
        ])
        ->assertOk()
        ->assertJsonPath('data.exposures', 1)
        ->assertJsonPath('data.accepted', 1);

    $this->assertDatabaseHas('study_sessions', ['id' => $sessionId, 'user_id' => $user->id, 'is_practice' => true]);
    $this->assertDatabaseHas('term_exposures', ['term_id' => $termId, 'session_id' => $sessionId]);
});

it('keeps the exposure but drops the session when the id belongs to nobody', function () {
    // The fact that matters is that the learner met the word. `session_id` is metadata, and it has
    // a foreign key an unknown id would break.
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'apple', 'яблоко');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['exposures' => [exposure($termId, sessionId: Ulid::generate())]])
        ->assertOk()
        ->assertJsonPath('data.exposures', 1);

    $this->assertDatabaseHas('term_exposures', ['term_id' => $termId, 'session_id' => null]);
});

it('spends the daily new-term quota, so an intro-only session still counts as meeting a word', function () {
    [$user, $token] = learner();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 1]);
    $apple = seedWordFor($user, 'apple', 'яблоко');
    seedWordFor($user, 'bank', 'банк');
    enableIntro();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['exposures' => [exposure($apple)]])
        ->assertOk();

    // The quota is read from the day's new-term count, which the exposure — not a review — moved.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/stats')
        ->assertOk()
        ->assertJsonPath('data.new_today', 1);

    // …so the second word is not introduced today, and only apple's remaining rungs are dealt.
    $cards = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->json('data.cards');

    expect(array_unique(array_column($cards, 'term_id')))->toBe([$apple])
        ->and(array_column($cards, 'ladder_step'))->toBe([1, 2]); // the intro is not shown twice
});

it('never lets a claimed rung turn a tapped id into typed production', function () {
    // Identity grading is the one path where a bare id counts as correct, so the MODE has to agree
    // with the claimed rung. Otherwise `ladder_step: 1` under `typing` would grade a tap as
    // production — where a fast answer earns `easy` and skews that mode's latency median.
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'apple', 'яблоко');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'typing',
            'response' => $termId, 'ladder_step' => 1, 'latency_ms' => 300,
            'answered_at' => now()->toIso8601String(), 'client_seq' => 1,
        ]]])->assertOk()->assertJsonPath('data.accepted', 1);

    // The id was graded as TEXT against the term's forms, which it is not → a miss, not an `easy`.
    expect(DB::table('reviews')->where('term_id', $termId)->value('grade'))->toBe('again');
});

it('drops a ladder answer that arrives after the pair has left the ladder', function () {
    // Two devices interleaving uploads of one word's ladder cards: the first finishes the ladder,
    // the second then sends a rung-1 tap. Grading that as text would fail the term-forms key and —
    // the pair now being graduated — hand the scheduler a LAPSE for a correct tap.
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'apple', 'яблоко');

    answerTimes($this, $token, $termId, 'apple', times: 3); // ladder done, then one SRS review
    $before = DB::table('user_term_progress')->where('term_id', $termId)->first();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'multiple_choice',
            'response' => $termId, 'ladder_step' => 1,
            'answered_at' => now()->toIso8601String(), 'client_seq' => 99,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 0)
        ->assertJsonPath('data.unknown', 1);

    $after = DB::table('user_term_progress')->where('term_id', $termId)->first();
    expect((int) $after->lapses)->toBe((int) $before->lapses)
        ->and((int) $after->interval_days)->toBe((int) $before->interval_days)
        ->and((string) $after->state)->toBe((string) $before->state);
});

it('rejects an intro arriving as a review — an intro produces no answer', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'apple', 'яблоко');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'intro', 'response' => '',
            'answered_at' => now()->toIso8601String(), 'client_seq' => 1,
        ]]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reviews.0.exercise_mode');

    $this->assertDatabaseCount('reviews', 0);
});

it('refuses a batch that carries neither answers nor intros', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reviews');
});

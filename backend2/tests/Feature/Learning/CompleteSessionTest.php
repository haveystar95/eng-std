<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * QA-12: `study_sessions.ended_at` and `stats` had no writer anywhere in the backend — a grep found
 * one mention, the migration that declares them. Every run in the table therefore looked abandoned,
 * and neither «did the learner finish this» nor «how long did it take» could be asked of the data.
 */

/** Build a session, answer one of its cards, and return [sessionId, termId]. */
function playedSession(object $ctx, object $user, string $token): array
{
    $termId = seedWordFor($user, 'withdraw cash', 'снять наличные');

    $session = $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->json('data');

    $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'multiple_choice',
            'response' => 'withdraw cash', 'answered_at' => now()->toIso8601String(),
            'client_seq' => 1, 'session_id' => $session['session_id'], 'ladder_step' => 2,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);

    return [$session['session_id'], $termId];
}

it('stamps ended_at and a summary recomputed from the session own log', function () {
    [$user, $token] = learner();
    [$sessionId] = playedSession($this, $user, $token);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/study/sessions/{$sessionId}/complete", ['ended_at' => '2026-08-17T17:49:16+00:00'])
        ->assertOk()
        ->assertJsonPath('data.completed', true);

    $row = DB::table('study_sessions')->where('id', $sessionId)->first();
    expect($row->ended_at)->not->toBeNull();

    /** @var array{cards: int, correct: int, intros: int} $stats */
    $stats = json_decode((string) $row->stats, true);
    expect($stats)->toEqualCanonicalizing(['cards' => 1, 'correct' => 1, 'intros' => 0]);
});

it('is idempotent — a re-sent completion never rewrites the finishing time', function () {
    [$user, $token] = learner();
    [$sessionId] = playedSession($this, $user, $token);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/study/sessions/{$sessionId}/complete", ['ended_at' => '2026-08-17T17:49:16+00:00'])
        ->assertOk()
        ->assertJsonPath('data.completed', true);

    $first = DB::table('study_sessions')->where('id', $sessionId)->value('ended_at');

    // The offline queue re-sends whatever it did not see acknowledged. The moment that matters is
    // when the learner stopped, not when the phone found a network.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/study/sessions/{$sessionId}/complete", ['ended_at' => '2026-08-18T09:00:00+00:00'])
        ->assertOk()
        ->assertJsonPath('data.completed', false);

    expect(DB::table('study_sessions')->where('id', $sessionId)->value('ended_at'))->toBe($first);
});

it('leaves an ABANDONED run open rather than closing it with zeroes', function () {
    [$user, $token] = learner();
    seedWordFor($user, 'apple', 'яблоко');

    $sessionId = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->json('data.session_id');

    // Nothing was answered and nothing was shown: `ended_at IS NULL` is the true statement here, and
    // a row of zeroes would be a false one.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/study/sessions/{$sessionId}/complete")
        ->assertOk()
        ->assertJsonPath('data.completed', false);

    expect(DB::table('study_sessions')->where('id', $sessionId)->value('ended_at'))->toBeNull();
});

it('never closes another user session', function () {
    [$owner, $ownerToken] = learner();
    [$sessionId] = playedSession($this, $owner, $ownerToken);
    $intruder = App\Modules\Identity\Infrastructure\Eloquent\User::factory()->create();

    // Driven through the handler rather than over HTTP: the framework's test client keeps the
    // resolved user between requests inside ONE test, so a second call with another token would be
    // answered as the first user and the guard under test would never run.
    $completed = app(App\Modules\Learning\Application\Command\CompleteStudySessionHandler::class)(
        new App\Modules\Learning\Application\Command\CompleteStudySession(
            actorId: App\Modules\Shared\Domain\ValueObject\UserId::fromString($intruder->id),
            sessionId: App\Modules\Learning\Domain\ValueObject\StudySessionId::fromString($sessionId),
            endedAt: new DateTimeImmutable(),
        ),
    );

    expect($completed)->toBeFalse();
    expect(DB::table('study_sessions')->where('id', $sessionId)->value('ended_at'))->toBeNull();
});

it('answers 200 for a session id it has never seen, so the offline queue can drain', function () {
    [, $token] = learner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions/' . Ulid::generate() . '/complete')
        ->assertOk()
        ->assertJsonPath('data.completed', false);
});

it('counts an intro as an intro, not as a card', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'apple', 'яблоко');

    $sessionId = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/study/sessions')
        ->assertOk()
        ->json('data.session_id');

    // An intro is SHOWN, not asked: it writes an exposure and no review, and the session screen has
    // always left it out of its tally. The summary must agree with both.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['exposures' => [[
            'term_id' => $termId, 'shown_at' => now()->toIso8601String(), 'session_id' => $sessionId,
        ]]])
        ->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/study/sessions/{$sessionId}/complete")
        ->assertOk()
        ->assertJsonPath('data.completed', true);

    $stats = json_decode((string) DB::table('study_sessions')->where('id', $sessionId)->value('stats'), true);
    expect($stats)->toEqualCanonicalizing(['cards' => 0, 'correct' => 0, 'intros' => 1]);
});

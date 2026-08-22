<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The QA bench: forced-time and reset.
 *
 * Most of what is asserted here is REFUSAL. Both commands rewrite or delete learning data with no
 * undo, so the tests that matter are the ones proving they cannot be pointed at the owner's
 * account by a typo — `is_qa` false, or an environment called production.
 */

/**
 * A QA account with a bearer token, shaped like one the dev sign-in would have made: marked
 * `is_qa`, with the one profile row every user has.
 *
 * @return array{0: User, 1: string}
 */
function qaLearner(): array
{
    $user = User::factory()->create(['is_qa' => true, 'email' => 'qa@wt.test']);
    $user->profile()->firstOrCreate([]);

    return [$user, $user->createToken('simulator')->plainTextToken];
}

it('ages every learning timestamp of a QA account back by N days', function () {
    [$user, $token] = qaLearner();
    $termId = seedWordFor($user, 'apple', 'яблоко');
    answerTimes($this, $token, $termId, 'apple', 3);

    $before = DB::table('user_term_progress')->where('user_id', $user->id)->first();
    $reviewBefore = DB::table('reviews')->where('user_id', $user->id)->orderBy('answered_at')->first();

    $this->artisan('qa:time-travel', ['user' => 'qa@wt.test', '--days' => '+3', '--force' => true])
        ->assertSuccessful();

    $after = DB::table('user_term_progress')->where('user_id', $user->id)->first();
    $reviewAfter = DB::table('reviews')->where('id', $reviewBefore->id)->first();

    $shift = fn (?string $a, ?string $b): int => (int) round(
        (strtotime((string) $a) - strtotime((string) $b)) / 86400
    );

    expect($shift($before->due_at, $after->due_at))->toBe(3)
        ->and($shift($before->last_reviewed_at, $after->last_reviewed_at))->toBe(3)
        ->and($shift($before->enrolled_at, $after->enrolled_at))->toBe(3)
        ->and($shift($reviewBefore->answered_at, $reviewAfter->answered_at))->toBe(3);
});

it('ages the exposure, triage, session and daily-stats rows too, not just progress', function () {
    [$user, $token] = qaLearner();
    $termId = seedWordFor($user, 'apple', 'яблоко');
    answerTimes($this, $token, $termId, 'apple', 1);

    DB::table('term_triages')->insert([
        'id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
        'user_id' => $user->id, 'term_id' => $termId, 'verdict' => 'unknown',
        'decided_at' => now(), 'created_at' => now(), 'client_seq' => 1,
    ]);

    $triageBefore = DB::table('term_triages')->where('user_id', $user->id)->first();
    $statsBefore = DB::table('daily_user_stats')->where('user_id', $user->id)->first();

    $this->artisan('qa:time-travel', ['user' => $user->id, '--days' => '2', '--force' => true])
        ->assertSuccessful();

    $triageAfter = DB::table('term_triages')->where('user_id', $user->id)->first();
    $statsAfter = DB::table('daily_user_stats')->where('user_id', $user->id)->first();

    expect((int) round((strtotime((string) $triageBefore->decided_at) - strtotime((string) $triageAfter->decided_at)) / 86400))->toBe(2);
    // daily_user_stats.date is a DATE — the shift has to land there too, or activity and the
    // new-term quota disagree with the schedule the rest of the shift produced.
    expect($statsAfter?->date)->not->toBeNull()
        ->and((int) round((strtotime((string) $statsBefore->date) - strtotime((string) $statsAfter->date)) / 86400))->toBe(2);
});

it('refuses to time-travel an account that is not marked is_qa', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'apple', 'яблоко');
    answerTimes($this, $token, $termId, 'apple', 1);
    $before = DB::table('user_term_progress')->where('user_id', $user->id)->value('due_at');

    $this->artisan('qa:time-travel', ['user' => $user->email, '--days' => '+3', '--force' => true])
        ->assertFailed();

    expect(DB::table('user_term_progress')->where('user_id', $user->id)->value('due_at'))->toBe($before);
});

it('refuses to time-travel in production', function () {
    [$user] = qaLearner();
    $this->app->detectEnvironment(fn (): string => 'production');

    $this->artisan('qa:time-travel', ['user' => $user->email, '--days' => '+1', '--force' => true])
        ->assertFailed();
});

it('refuses a missing, zero or negative --days rather than guessing', function () {
    [$user] = qaLearner();

    $this->artisan('qa:time-travel', ['user' => $user->email, '--force' => true])->assertFailed();
    $this->artisan('qa:time-travel', ['user' => $user->email, '--days' => '0', '--force' => true])->assertFailed();
    $this->artisan('qa:time-travel', ['user' => $user->email, '--days' => '-3', '--force' => true])->assertFailed();
    $this->artisan('qa:time-travel', ['user' => $user->email, '--days' => 'soon', '--force' => true])->assertFailed();
});

it('refuses an unknown user', function () {
    $this->artisan('qa:time-travel', ['user' => 'nobody@wt.test', '--days' => '+1', '--force' => true])->assertFailed();
    $this->artisan('qa:reset', ['user' => 'nobody@wt.test', '--force' => true])->assertFailed();
});

it('resets a QA account to «never studied anything», keeping the account, profile and words', function () {
    [$user, $token] = qaLearner();
    [$collectionId, $termId] = seedCollectionWith($user, 'apple', 'яблоко');
    answerTimes($this, $token, $termId, 'apple', 3);

    expect(DB::table('reviews')->where('user_id', $user->id)->count())->toBeGreaterThan(0);

    $this->artisan('qa:reset', ['user' => 'qa@wt.test', '--force' => true])->assertSuccessful();

    foreach (['user_term_progress', 'reviews', 'term_triages', 'term_exposures', 'study_sessions', 'daily_user_stats'] as $table) {
        expect(DB::table($table)->where('user_id', $user->id)->count())->toBe(0, "{$table} should be empty");
    }

    // Kept: the account, its profile, its collection and the term itself.
    $this->assertDatabaseHas('users', ['id' => $user->id]);
    $this->assertDatabaseHas('profiles', ['user_id' => $user->id]);
    $this->assertDatabaseHas('collections', ['id' => $collectionId]);
    $this->assertDatabaseHas('terms', ['id' => $termId]);
});

it('clears per-user mode overrides on reset so the next run starts on the shipped matrix', function () {
    [$user] = qaLearner();
    DB::table('learning_mode_settings')->insert([
        'id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
        'user_id' => $user->id, 'mode' => 'typing', 'enabled' => false, 'position' => 0,
        'min_acquisition' => 'graduated', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('qa:reset', ['user' => $user->email, '--force' => true])->assertSuccessful();

    expect(DB::table('learning_mode_settings')->where('user_id', $user->id)->count())->toBe(0);
});

it('refuses to reset an account that is not marked is_qa', function () {
    [$user, $token] = learner();
    $termId = seedWordFor($user, 'apple', 'яблоко');
    answerTimes($this, $token, $termId, 'apple', 1);

    $this->artisan('qa:reset', ['user' => $user->email, '--force' => true])->assertFailed();

    expect(DB::table('reviews')->where('user_id', $user->id)->count())->toBeGreaterThan(0);
});

it('refuses to reset in production', function () {
    [$user] = qaLearner();
    $this->app->detectEnvironment(fn (): string => 'production');

    $this->artisan('qa:reset', ['user' => $user->email, '--force' => true])->assertFailed();
});

it('reports a per-user spend window, and says out loud that search is missing from the panel number', function () {
    [$user] = qaLearner();

    DB::table('search_lookups')->insert([
        'id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
        'user_id' => $user->id, 'normalized_query' => 'apple', 'lang' => 'en', 'native_lang' => 'ru',
        'payload' => json_encode(['x' => 1]), 'model' => 'gpt-4o-mini', 'prompt_version' => 'v1',
        'tokens_in' => 100, 'tokens_out' => 50, 'cost_usd' => 0.0025,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('qa:cost', ['user' => 'qa@wt.test', '--period' => 'week'])
        ->expectsOutputToContain('search_lookups')
        ->assertSuccessful();
});

it('refuses an unknown --period', function () {
    [$user] = qaLearner();

    $this->artisan('qa:cost', ['user' => $user->email, '--period' => 'fortnight'])->assertFailed();
});

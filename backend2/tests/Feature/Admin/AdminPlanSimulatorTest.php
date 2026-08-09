<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Answer a term correctly (as the user) at a given time, so grade is `good` and it schedules. */
function answerTerm(User $user, string $token, string $termId, string $answer, string $answeredAt, int $seq): void
{
    test()->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'typing',
            'response' => $answer, 'answered_at' => $answeredAt, 'client_seq' => $seq,
        ]]])
        ->assertOk();
}

it('simulates a study day and gives tomorrow a different plan than today', function () {
    $user = User::factory()->create();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 5]);
    $userToken = $user->createToken('phone')->plainTextToken;

    [, $aTerm] = adminSeedTerm($user, 'A', 'apple', 'яблоко');   // aged → due today AND tomorrow
    [, $cTerm] = adminSeedTerm($user, 'C', 'cat', 'кот');        // studied now → due only tomorrow
    [, $bTerm] = adminSeedTerm($user, 'B', 'bank', 'банк');      // never studied → new (both days)

    // aTerm: first good 5 days ago → learning, interval 1 day → due ~4 days ago (overdue today).
    answerTerm($user, $userToken, $aTerm, 'apple', now()->subDays(5)->toIso8601String(), 1);
    // cTerm: first good now → learning, next due rounds to the start of tomorrow (device-batch F19),
    // so it is NOT due today but IS due tomorrow.
    answerTerm($user, $userToken, $cTerm, 'cat', now()->toIso8601String(), 2);

    [$admin, $adminToken] = adminActor();

    $sessionsBefore = \Illuminate\Support\Facades\DB::table('study_sessions')->count();
    $progressBefore = \Illuminate\Support\Facades\DB::table('user_term_progress')->count();

    $today = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/users/{$user->id}/plan")
        ->assertOk()
        ->assertJsonStructure(['date', 'timezone', 'due_count', 'new_introduced', 'new_terms_per_day', 'entries'])
        ->json();

    $tomorrow = $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson("/admin/api/users/{$user->id}/plan?date=" . now()->addDay()->format('Y-m-d'))
        ->assertOk()
        ->json();

    $dueToday = collect($today['entries'])->where('is_new', false)->pluck('term_id')->all();
    $dueTomorrow = collect($tomorrow['entries'])->where('is_new', false)->pluck('term_id')->all();

    // aTerm is due both days; cTerm only tomorrow → the two plans genuinely differ.
    expect($dueToday)->toContain($aTerm)->not->toContain($cTerm);
    expect($dueTomorrow)->toContain($aTerm)->toContain($cTerm);
    expect($tomorrow['due_count'])->toBeGreaterThan($today['due_count']);

    // The new-term (bTerm) shows up as an introduced card, with the scheduler-picked mode present.
    $newToday = collect($today['entries'])->where('is_new', true)->pluck('term_id')->all();
    expect($newToday)->toContain($bTerm);
    expect($today['entries'][0])->toHaveKeys(['exercise_mode', 'state', 'reps', 'clozeable']);

    // The simulator is a dry run: it writes nothing (no session, no progress rows changed).
    expect(\Illuminate\Support\Facades\DB::table('study_sessions')->count())->toBe($sessionsBefore);
    expect(\Illuminate\Support\Facades\DB::table('user_term_progress')->count())->toBe($progressBefore);
});

it('404s the plan for an unknown user id', function () {
    [, $adminToken] = adminActor();

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->getJson('/admin/api/users/' . Ulid::generate() . '/plan')
        ->assertNotFound();
});

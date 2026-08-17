<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** @return array<string, string> */
function bearer(User $user): array
{
    return ['Authorization' => 'Bearer ' . $user->createToken('device')->plainTextToken];
}

it('stamps onboarded_at when the onboarding-finish flag is sent (F1)', function () {
    $user = User::factory()->create();

    $this->withHeaders(bearer($user))
        ->putJson('/api/v1/profile', ['cefr_level' => 'B1', 'daily_goal' => 20, 'onboarded' => true])
        ->assertOk()
        ->assertJsonPath('data.profile.onboarded_at', fn (?string $v): bool => $v !== null);

    expect(DB::table('profiles')->where('user_id', $user->id)->value('onboarded_at'))->not->toBeNull();
});

it('never overwrites onboarded_at on later edits (F1)', function () {
    $user = User::factory()->create();
    $headers = bearer($user);

    $this->withHeaders($headers)->putJson('/api/v1/profile', ['onboarded' => true])->assertOk();
    $first = DB::table('profiles')->where('user_id', $user->id)->value('onboarded_at');
    expect($first)->not->toBeNull();

    // A plain edit later must not move the stamp…
    $this->withHeaders($headers)->putJson('/api/v1/profile', ['daily_goal' => 30])->assertOk();
    // …nor does a second onboarded:true.
    $this->withHeaders($headers)->putJson('/api/v1/profile', ['onboarded' => true])->assertOk();

    expect(DB::table('profiles')->where('user_id', $user->id)->value('onboarded_at'))->toBe($first);
});

it('leaves onboarded_at null for a plain profile edit (new account still onboards)', function () {
    $user = User::factory()->create();

    $this->withHeaders(bearer($user))
        ->putJson('/api/v1/profile', ['daily_goal' => 25])
        ->assertOk()
        ->assertJsonPath('data.profile.onboarded_at', null);

    expect(DB::table('profiles')->where('user_id', $user->id)->value('onboarded_at'))->toBeNull();
});

// ── timezone (device-batch F19) ──────────────────────────────────────────────

it('stores the client-sent IANA timezone and returns it in the profile', function () {
    $user = User::factory()->create();

    $this->withHeaders(bearer($user))
        ->putJson('/api/v1/profile', ['timezone' => 'Europe/Kyiv'])
        ->assertOk()
        ->assertJsonPath('data.profile.timezone', 'Europe/Kyiv');

    expect(DB::table('profiles')->where('user_id', $user->id)->value('timezone'))->toBe('Europe/Kyiv');
});

it('defaults the profile timezone to UTC until the client sends one', function () {
    $user = User::factory()->create();

    $this->withHeaders(bearer($user))
        ->putJson('/api/v1/profile', ['daily_goal' => 20])
        ->assertOk()
        ->assertJsonPath('data.profile.timezone', 'UTC');

    // Stored value stays null (no client zone yet); the view fills the UTC fallback.
    expect(DB::table('profiles')->where('user_id', $user->id)->value('timezone'))->toBeNull();
});

it('rejects an invalid timezone', function () {
    $user = User::factory()->create();

    $this->withHeaders(bearer($user))
        ->putJson('/api/v1/profile', ['timezone' => 'Mars/Olympus'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('timezone');
});

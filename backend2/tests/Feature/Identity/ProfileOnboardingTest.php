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

// ── the native language (ONB-1) ──────────────────────────────────────────────
//
// The learner's own language is asked ONCE, at first run, and every other surface reads it off the
// account: a collection's pair, a generated set's support side and a plan's `support_lang` all come
// from this one column. So it has to be EDITABLE through the profile endpoint and not baked in
// anywhere — which is exactly what these two assert.

it('stores the native language chosen at onboarding and returns it', function () {
    $user = User::factory()->create();

    $this->withHeaders(bearer($user))
        ->putJson('/api/v1/profile', ['native_language' => 'uk', 'target_language' => 'de', 'onboarded' => true])
        ->assertOk()
        ->assertJsonPath('data.profile.native_language', 'uk')
        ->assertJsonPath('data.profile.target_language', 'de');

    expect(DB::table('profiles')->where('user_id', $user->id)->value('native_language'))->toBe('uk');
});

it('lets the native language be changed later — it is a setting, not a hardcoded default', function () {
    $user = User::factory()->create();
    $headers = bearer($user);

    $this->withHeaders($headers)->putJson('/api/v1/profile', ['native_language' => 'ru'])->assertOk();
    $this->withHeaders($headers)
        ->putJson('/api/v1/profile', ['native_language' => 'pl'])
        ->assertOk()
        ->assertJsonPath('data.profile.native_language', 'pl');

    expect(DB::table('profiles')->where('user_id', $user->id)->value('native_language'))->toBe('pl');
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

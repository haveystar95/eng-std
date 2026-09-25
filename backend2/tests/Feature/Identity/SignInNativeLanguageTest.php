<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Dto\GoogleIdentity;
use App\Modules\Identity\Application\Port\GoogleTokenVerifier;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Doubles\FakeGoogleTokenVerifier;

uses(RefreshDatabase::class);

/**
 * THE DEVICE'S LANGUAGE SEEDS THE LEARNER'S OWN (наряд LANG-1 §7).
 *
 * On the sign-in that CREATES the profile — Google or the QA door — `profiles.native_language` takes the
 * first `Accept-Language` entry whose primary subtag is a plan native; none (English, or no header — the
 * test client sends `en-us,en;q=0.5` by default) leaves the column's `ru`. A profile that exists already
 * is never touched: the stored native is the learner's choice, not the phone's.
 *
 * And the profile's own allow-list: any language the catalogue names (п. 85), under PUT and PATCH.
 */
function googleSignsInAs(string $sub = 'google-sub-lang'): void
{
    app()->instance(GoogleTokenVerifier::class, FakeGoogleTokenVerifier::returning(
        new GoogleIdentity($sub, "{$sub}@example.com", 'Learner', null),
    ));
}

function storedNative(): ?string
{
    $value = DB::table('profiles')->value('native_language');

    return is_string($value) ? $value : null;
}

it('seeds the native from the device on the first Google sign-in', function (string $header, string $native) {
    googleSignsInAs();

    $this->withHeader('Accept-Language', $header)
        ->postJson('/api/v1/auth/google', ['id_token' => 'valid-token'])
        ->assertOk()
        ->assertJsonPath('user.profile.native_language', $native);

    expect(storedNative())->toBe($native);
})->with([
    'a Ukrainian phone' => ['uk-UA,uk;q=0.9', 'uk'],
    'a Belarusian phone' => ['be-BY', 'be'],
    'a Polish phone, bare code' => ['pl', 'pl'],
    'English first, Romanian after it' => ['en-US,ro;q=0.8', 'ro'],
    'an English phone — English is not a plan native' => ['en-US', 'ru'],
    'a Turkish phone — named by the catalogue, read by no plan' => ['tr-TR,tr;q=0.9', 'ru'],
]);

it('leaves the column default when the client sends the framework default', function () {
    // No header of our own: the test client's `en-us,en;q=0.5`, which is what a client that sends none gets.
    googleSignsInAs();

    $this->postJson('/api/v1/auth/google', ['id_token' => 'valid-token'])
        ->assertOk()
        ->assertJsonPath('user.profile.native_language', 'ru');
});

it('never touches the native of a profile that exists, whatever the phone says later', function () {
    googleSignsInAs();
    $this->withHeader('Accept-Language', 'uk-UA')->postJson('/api/v1/auth/google', ['id_token' => 'valid-token'])->assertOk();
    expect(storedNative())->toBe('uk');

    // The learner's own choice, then a login from a phone switched to Polish.
    DB::table('profiles')->update(['native_language' => 'be']);
    $this->withHeader('Accept-Language', 'pl-PL,pl;q=0.9')
        ->postJson('/api/v1/auth/google', ['id_token' => 'valid-token'])
        ->assertOk()
        ->assertJsonPath('user.profile.native_language', 'be');

    expect(storedNative())->toBe('be');
    $this->assertDatabaseCount('profiles', 1);
});

it('seeds the native the same way at the QA door', function () {
    $this->withHeader('Accept-Language', 'uk-UA,uk;q=0.9')
        ->postJson('/api/v1/auth/dev', ['email' => 'qa-lang@wt.test'])
        ->assertOk()
        ->assertJsonPath('user.profile.native_language', 'uk');

    // A later login from another phone keeps what the account has.
    $this->withHeader('Accept-Language', 'be-BY')
        ->postJson('/api/v1/auth/dev', ['email' => 'qa-lang@wt.test'])
        ->assertOk()
        ->assertJsonPath('user.profile.native_language', 'uk');
});

it('leaves the QA door on the column default for an English device', function () {
    $this->withHeader('Accept-Language', 'en-US')
        ->postJson('/api/v1/auth/dev', ['email' => 'qa-lang@wt.test'])
        ->assertOk()
        ->assertJsonPath('user.profile.native_language', 'ru');
});

it('takes any catalogue language as the profile native, not only a plan native', function (string $method) {
    $user = User::factory()->create();
    $token = $user->createToken('test-device')->plainTextToken;

    // Turkish is no plan's native, but collections and search are read in it (п. 85).
    $this->withHeader('Authorization', "Bearer {$token}")
        ->json($method, '/api/v1/profile', ['native_language' => 'tr'])
        ->assertOk()
        ->assertJsonPath('data.profile.native_language', 'tr');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->json($method, '/api/v1/profile', ['native_language' => 'be'])
        ->assertOk()
        ->assertJsonPath('data.profile.native_language', 'be');
})->with(['PUT', 'PATCH']);

it('refuses a native the catalogue does not name', function (string $code) {
    $user = User::factory()->create();
    $token = $user->createToken('test-device')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson('/api/v1/profile', ['native_language' => $code])
        ->assertStatus(422)
        ->assertJsonValidationErrors('native_language');
})->with(['sv', 'xx', 'RU', 'en-us']);

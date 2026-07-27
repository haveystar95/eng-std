<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Dto\GoogleIdentity;
use App\Modules\Identity\Application\Port\GoogleTokenVerifier;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Doubles\FakeGoogleTokenVerifier;

uses(RefreshDatabase::class);

function fakeGoogle(?GoogleIdentity $identity = null): void
{
    $identity ??= new GoogleIdentity('google-sub-1', 'denis@example.com', 'Denis', 'https://pic.example/denis.png');
    app()->instance(GoogleTokenVerifier::class, FakeGoogleTokenVerifier::returning($identity));
}

/** Create a user with a real bearer token, returned for the Authorization header. */
function actingUser(): array
{
    $user = User::factory()->create();
    $token = $user->createToken('test-device')->plainTextToken;

    return [$user, $token];
}

it('signs in with a valid Google token, creating the user and profile', function () {
    fakeGoogle();

    $response = $this->postJson('/api/v1/auth/google', ['id_token' => 'valid-token']);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'avatar', 'profile' => ['native_language', 'target_language', 'cefr_level', 'daily_goal']]])
        ->assertJsonPath('user.email', 'denis@example.com')
        ->assertJsonPath('user.profile.native_language', 'ru');

    $this->assertDatabaseCount('users', 1);
    $this->assertDatabaseHas('users', ['google_id' => 'google-sub-1', 'name' => 'Denis']);
    $this->assertDatabaseCount('profiles', 1);
});

it('maps the same Google account to the same user on repeat sign-in', function () {
    fakeGoogle();

    $first = $this->postJson('/api/v1/auth/google', ['id_token' => 'valid-token'])->json('user.id');
    $second = $this->postJson('/api/v1/auth/google', ['id_token' => 'valid-token'])->json('user.id');

    expect($second)->toBe($first);
    $this->assertDatabaseCount('users', 1);
});

it('rejects an invalid Google token with 422', function () {
    $this->app->instance(GoogleTokenVerifier::class, FakeGoogleTokenVerifier::rejecting());

    $this->postJson('/api/v1/auth/google', ['id_token' => 'bad-token'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('id_token');
});

it('validates that id_token is required', function () {
    $this->postJson('/api/v1/auth/google', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors('id_token');
});

it('returns the authenticated user from /auth/me', function () {
    [$user, $token] = actingUser();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

it('rejects /auth/me without a token', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('revokes the current token on logout', function () {
    [$user, $token] = actingUser();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/auth/logout')
        ->assertNoContent();

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('updates the profile', function () {
    [, $token] = actingUser();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/v1/profile', ['cefr_level' => 'C1', 'daily_goal' => 30, 'target_language' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.profile.cefr_level', 'C1')
        ->assertJsonPath('data.profile.daily_goal', 30);

    $this->assertDatabaseHas('profiles', ['cefr_level' => 'C1', 'daily_goal' => 30]);
});

it('rejects an invalid cefr level', function () {
    [, $token] = actingUser();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/api/v1/profile', ['cefr_level' => 'Z9'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('cefr_level');
});

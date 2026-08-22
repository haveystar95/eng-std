<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * The QA door — and, mostly, the four ways it stays shut.
 *
 * The suite runs with DEV_LOGIN_ENABLED=true (phpunit.xml), so the happy path is reachable here;
 * every «shut» case flips one of the two locks at request time and asserts the door disappears.
 * The route-registration lock (the door is not even in a production route table) is a separate,
 * pure test — see tests/Unit/Identity/DevLoginGateTest.php.
 */
it('signs a brand-new QA account in by email alone and marks it is_qa', function () {
    $response = $this->postJson('/api/v1/auth/dev', ['email' => 'qa@wt.test', 'timezone' => 'Europe/Kyiv']);

    $response->assertOk()
        ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email', 'profile']])
        ->assertJsonPath('user.email', 'qa@wt.test')
        ->assertJsonPath('user.profile.timezone', 'Europe/Kyiv');

    $this->assertDatabaseHas('users', ['email' => 'qa@wt.test', 'is_qa' => true, 'google_id' => null]);
    $this->assertDatabaseCount('profiles', 1);
});

it('returns the same account on a repeat dev login, minting a fresh token', function () {
    $first = $this->postJson('/api/v1/auth/dev', ['email' => 'qa@wt.test'])->json();
    $second = $this->postJson('/api/v1/auth/dev', ['email' => 'qa@wt.test'])->json();

    expect($second['user']['id'])->toBe($first['user']['id']);
    expect($second['token'])->not->toBe($first['token']);
    $this->assertDatabaseCount('users', 1);
});

it('issues a token that actually authenticates the API', function () {
    $token = $this->postJson('/api/v1/auth/dev', ['email' => 'qa@wt.test'])->json('token');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'qa@wt.test');
});

it('refuses an address that belongs to a real (non-QA) account', function () {
    User::factory()->create(['email' => 'denis@example.com', 'google_id' => 'google-sub-1']);

    $this->postJson('/api/v1/auth/dev', ['email' => 'denis@example.com'])
        ->assertStatus(403)
        ->assertJsonPath('code', 'not_a_qa_account');

    $this->assertDatabaseCount('personal_access_tokens', 0);
});

it('is shut in production even with the flag on', function () {
    $this->app->detectEnvironment(fn (): string => 'production');

    $this->postJson('/api/v1/auth/dev', ['email' => 'qa@wt.test'])
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');

    $this->assertDatabaseCount('users', 0);
});

it('is shut when the flag is off', function () {
    config()->set('qa.dev_login', false);

    $this->postJson('/api/v1/auth/dev', ['email' => 'qa@wt.test'])
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');

    $this->assertDatabaseCount('users', 0);
});

it('validates the email', function () {
    $this->postJson('/api/v1/auth/dev', [])->assertStatus(422)->assertJsonValidationErrors('email');
    $this->postJson('/api/v1/auth/dev', ['email' => 'not-an-email'])->assertStatus(422)->assertJsonValidationErrors('email');
});

it('does not create an account when the gate is shut, even for a fresh address', function () {
    config()->set('qa.dev_login', false);

    $this->postJson('/api/v1/auth/dev', ['email' => 'fresh@wt.test'])->assertNotFound();

    $this->assertDatabaseMissing('users', ['email' => 'fresh@wt.test']);
});

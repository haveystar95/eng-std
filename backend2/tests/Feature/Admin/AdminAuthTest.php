<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('logs in with valid admin credentials and returns a token', function () {
    adminActor('admin@wt.test');

    $this->postJson('/admin/api/login', ['email' => 'admin@wt.test', 'password' => 'secret123'])
        ->assertOk()
        ->assertJsonStructure(['token', 'admin' => ['id', 'email', 'name']])
        ->assertJsonPath('admin.email', 'admin@wt.test');
});

it('rejects a wrong password with 401', function () {
    adminActor('admin@wt.test');

    $this->postJson('/admin/api/login', ['email' => 'admin@wt.test', 'password' => 'nope'])
        ->assertUnauthorized()
        ->assertJsonPath('code', 'invalid_credentials');
});

it('requires a token for a protected admin endpoint', function () {
    $this->getJson('/admin/api/dashboard')->assertUnauthorized();
});

it('answers 401 (never a redirect/500) on every protected route without a token, even for a browser Accept', function () {
    $id = \App\Modules\Shared\Domain\ValueObject\Ulid::generate();
    $routes = [
        ['GET', '/admin/api/me'],
        ['GET', '/admin/api/dashboard'],
        ['GET', '/admin/api/users'],
        ['GET', "/admin/api/users/{$id}"],
        ['GET', "/admin/api/users/{$id}/plan"],
        ['GET', "/admin/api/users/{$id}/collections"],
        ['GET', "/admin/api/users/{$id}/reviews"],
        ['POST', "/admin/api/users/{$id}/tier"],
        ['GET', '/admin/api/exercise-modes'],
        ['PUT', '/admin/api/exercise-modes'],
        ['GET', "/admin/api/users/{$id}/exercise-modes"],
        ['PUT', "/admin/api/users/{$id}/exercise-modes"],
        ['GET', '/admin/api/collections'],
        ['GET', "/admin/api/collections/{$id}"],
        ['GET', '/admin/api/terms'],
        ['GET', "/admin/api/terms/{$id}"],
        ['GET', '/admin/api/logs'],
        ['GET', "/admin/api/logs/{$id}"],
        ['GET', '/admin/api/request-logs'],
        ['GET', '/admin/api/practice-dialogs'],
        ['GET', "/admin/api/practice-dialogs/{$id}"],
        ['GET', '/admin/api/generations'],
    ];

    foreach ($routes as [$method, $url]) {
        // A browser (no `Accept: application/json`) must still get 401, not a redirect to `login`.
        $this->call($method, $url, [], [], [], ['HTTP_ACCEPT' => 'text/html'])
            ->assertStatus(401);
    }
});

it('does NOT accept an app-user token on an admin endpoint', function () {
    $user = User::factory()->create();
    $userToken = $user->createToken('phone')->plainTextToken;

    // A perfectly valid *user* Sanctum token must fail the admin guard (different provider).
    $this->withHeader('Authorization', "Bearer {$userToken}")
        ->getJson('/admin/api/dashboard')
        ->assertUnauthorized();
});

it('accepts a valid admin token', function () {
    [, $token] = adminActor();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/admin/api/me')
        ->assertOk()
        ->assertJsonStructure(['id', 'email', 'name']);
});

it('creates an admin via the console command and can log in with it', function () {
    $this->artisan('admin:create', ['email' => 'ops@wt.test', '--name' => 'Ops'])
        ->expectsQuestion('Password (leave blank to auto-generate)', 'hunter2hunter2')
        ->assertSuccessful();

    $this->postJson('/admin/api/login', ['email' => 'ops@wt.test', 'password' => 'hunter2hunter2'])
        ->assertOk()
        ->assertJsonPath('admin.name', 'Ops');
});

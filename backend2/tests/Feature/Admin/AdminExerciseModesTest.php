<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('lists every mode this build can deal, plus the current default', function () {
    [, $token] = adminActor();

    $response = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/admin/api/exercise-modes')
        ->assertOk()
        // `available` comes from the enum, so a newly built mode shows up in the panel the moment
        // it exists — switched off, per the release rule.
        ->assertJsonPath('available', ['multiple_choice', 'word_bank', 'typing', 'listening', 'cloze', 'scramble', 'dictation'])
        ->assertJsonPath('global', config('learning.enabled_modes'))
        ->assertJsonPath('inherits', true);

    // The release rule, stated as an assertion: the newest trainer is offerable but not on. This is
    // the pair that has to stay true for every mode that follows — listed, and off.
    expect($response->json('available'))->toContain('dictation')
        ->and($response->json('global'))->not->toContain('dictation');
});

it('sets the product default and keeps the order it was given', function () {
    [, $token] = adminActor();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/admin/api/exercise-modes', ['modes' => ['typing', 'multiple_choice']])
        ->assertOk()
        ->assertJsonPath('global', ['typing', 'multiple_choice']);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/admin/api/exercise-modes')
        ->assertJsonPath('global', ['typing', 'multiple_choice']);
});

it('refuses to leave the product default empty — there is nothing to inherit from', function () {
    [, $token] = adminActor();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/admin/api/exercise-modes', ['modes' => []])
        ->assertStatus(422);

    expect(DB::table('learning_mode_settings')->whereNull('user_id')->count())->toBe(1);
});

it('rejects an unknown mode, naming the offending entry', function () {
    [, $token] = adminActor();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/admin/api/exercise-modes', ['modes' => ['typing', 'time_travel']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('modes.1');
});

it('gives one user an override and shows it as custom, not as inherited', function () {
    [, $token] = adminActor();
    $user = User::factory()->create();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/admin/api/users/{$user->id}/exercise-modes", ['modes' => ['scramble', 'typing']])
        ->assertOk()
        ->assertJsonPath('override', ['scramble', 'typing'])
        ->assertJsonPath('effective', ['scramble', 'typing'])
        ->assertJsonPath('inherits', false);

    // The default is untouched — an override is one user's setting, not a global edit.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/admin/api/exercise-modes')
        ->assertJsonPath('global', config('learning.enabled_modes'));
});

it('clears an override with an empty set, putting the user back on the default', function () {
    [, $token] = adminActor();
    $user = User::factory()->create();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/admin/api/users/{$user->id}/exercise-modes", ['modes' => ['typing']])->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/admin/api/users/{$user->id}/exercise-modes", ['modes' => null])
        ->assertOk()
        ->assertJsonPath('override', null)
        ->assertJsonPath('inherits', true)
        ->assertJsonPath('effective', config('learning.enabled_modes'));

    expect(DB::table('learning_mode_settings')->where('user_id', $user->id)->count())->toBe(0);
});

it('audits every flip — a narrowed set is invisible to the user who reports it', function () {
    [$admin, $token] = adminActor();
    $user = User::factory()->create();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/admin/api/exercise-modes', ['modes' => ['typing']])->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/admin/api/users/{$user->id}/exercise-modes", ['modes' => ['scramble']])->assertOk();

    $entries = DB::table('admin_audit_log')->orderBy('created_at')->get();

    expect($entries)->toHaveCount(2)
        ->and($entries[0]->action)->toBe('learning.modes.global')
        ->and($entries[0]->admin_id)->toBe($admin->id)
        ->and($entries[0]->target_user_id)->toBeNull() // a global flip is aimed at no one user
        ->and($entries[1]->action)->toBe('learning.modes.user')
        ->and($entries[1]->target_user_id)->toBe($user->id)
        ->and(json_decode((string) $entries[1]->context, true))->toMatchArray(['from' => null, 'to' => ['scramble']]);
});

it('404s on a malformed user id instead of 500ing', function () {
    [, $token] = adminActor();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/admin/api/users/not-a-ulid/exercise-modes')
        ->assertNotFound();
});

it('answers 401 on every toggle route without a token, even for a browser Accept', function () {
    $id = Ulid::generate();
    $routes = [
        ['GET', '/admin/api/exercise-modes'],
        ['PUT', '/admin/api/exercise-modes'],
        ['GET', "/admin/api/users/{$id}/exercise-modes"],
        ['PUT', "/admin/api/users/{$id}/exercise-modes"],
    ];

    foreach ($routes as [$method, $uri]) {
        $this->withHeader('Accept', 'text/html')
            ->json($method, $uri)
            ->assertUnauthorized();
    }
});

it('never lets an app user token reach the panel', function () {
    $user = User::factory()->create();
    $token = $user->createToken('phone')->plainTextToken;

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/admin/api/exercise-modes')
        ->assertUnauthorized();
});

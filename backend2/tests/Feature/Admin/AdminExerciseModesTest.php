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
        ->assertJsonPath('available', ['multiple_choice', 'word_bank', 'typing', 'listening', 'cloze', 'scramble', 'dictation', 'pick_correct', 'description_match', 'speaking', 'intro'])
        ->assertJsonPath('global', config('learning.enabled_modes'))
        ->assertJsonPath('inherits', true);

    // The release rule, stated as an assertion: the newest trainer is offerable but not on. This is
    // the pair that has to stay true for every mode that follows — listed, and off.
    expect($response->json('available'))->toContain('dictation')
        ->and($response->json('global'))->not->toContain('dictation')
        ->and($response->json('available'))->toContain('pick_correct')
        ->and($response->json('global'))->not->toContain('pick_correct')
        ->and($response->json('available'))->toContain('speaking')
        ->and($response->json('global'))->not->toContain('speaking')
        ->and($response->json('available'))->toContain('intro')
        ->and($response->json('global'))->not->toContain('intro')
        // …and `intro` is flagged as producing no grade, so the panel offers it as a toggle rather
        // than as a scored trainer.
        ->and($response->json('no_grade'))->toBe(['intro']);
});

it('carries the admission matrix, so a threshold is inspectable from the panel', function () {
    [, $token] = adminActor();

    $matrix = collect($this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/admin/api/exercise-modes')
        ->assertOk()
        ->json('admission'))->keyBy('mode');

    expect($matrix['intro']['min_acquisition'])->toBe('new')
        ->and($matrix['multiple_choice']['min_acquisition'])->toBe('learning')
        ->and($matrix['multiple_choice']['min_learning_step'])->toBe(1)
        ->and($matrix['multiple_choice']['options_policy'])->toBe('distant')
        ->and($matrix['typing']['min_acquisition'])->toBe('graduated')
        ->and($matrix['typing']['min_reps'])->toBe(4);
});

it('moves one trainer down the ladder without touching the rest, and logs who did it', function () {
    [$admin, $token] = adminActor();

    $matrix = collect($this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/admin/api/exercise-modes/admission', [
            'mode' => 'dictation', 'min_acquisition' => 'graduated', 'min_reps' => 12,
        ])
        ->assertOk()
        ->json('admission'))->keyBy('mode');

    expect($matrix['dictation']['min_reps'])->toBe(12)
        ->and($matrix['typing']['min_reps'])->toBe(4); // untouched

    // Moving a threshold changes what learners are asked for weeks and is invisible to them, so it
    // leaves a trail like every other mutation the panel makes.
    $this->assertDatabaseHas('admin_audit_log', ['admin_id' => $admin->id, 'action' => 'learning.admission.global']);
});

it('refuses an admission rule the ladder cannot mean', function () {
    [, $token] = adminActor();

    // reps is counted from graduation, so it says nothing on a recognition rung.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/admin/api/exercise-modes/admission', [
            'mode' => 'typing', 'min_acquisition' => 'learning', 'min_learning_step' => 1, 'min_reps' => 3,
        ])
        ->assertStatus(422);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/admin/api/exercise-modes/admission', ['mode' => 'time_travel', 'min_acquisition' => 'new'])
        ->assertStatus(422);
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

    // One row per (scope, mode) since the admission matrix moved into this table.
    expect(DB::table('learning_mode_settings')->whereNull('user_id')->count())
        ->toBe(count(\App\Modules\Learning\Domain\ValueObject\ExerciseMode::cases()));
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

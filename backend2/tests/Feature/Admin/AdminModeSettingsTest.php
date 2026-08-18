<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('lists the global matrix, one row per mode, all tagged as the global source', function () {
    [, $token] = adminActor();

    $rows = collect($this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/admin/api/mode-settings')
        ->assertOk()
        ->json('rows'))->keyBy('mode');

    expect($rows)->toHaveCount(10)
        ->and($rows['typing']['source'])->toBe('global')
        ->and($rows['typing']['min_acquisition'])->toBe('graduated')
        ->and($rows['typing']['min_successful_reviews'])->toBe(4)
        ->and($rows['multiple_choice']['options_policy'])->toBe('distant')
        ->and($rows['intro']['enabled'])->toBeFalse(); // shipped default: intro is off
});

it('shows a user with no override inheriting every cell from the global row', function () {
    [, $token] = adminActor();
    $user = User::factory()->create();

    $rows = collect($this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/admin/api/users/{$user->id}/mode-settings")
        ->assertOk()
        ->json('rows'))->keyBy('mode');

    expect($rows['typing']['source'])->toBe('global')
        ->and($rows['typing']['min_successful_reviews'])->toBe(4);
});

it('writes one user row without touching the rest, marks it override, and logs it', function () {
    [$admin, $token] = adminActor();
    $user = User::factory()->create();

    $rows = collect($this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/admin/api/users/{$user->id}/mode-settings", [
            'mode' => 'typing', 'enabled' => true, 'position' => 3,
            'min_acquisition' => 'graduated', 'min_successful_reviews' => 1, 'options_policy' => 'standard',
        ])
        ->assertOk()
        ->json('rows'))->keyBy('mode');

    expect($rows['typing']['source'])->toBe('override')
        ->and($rows['typing']['enabled'])->toBeTrue()
        ->and($rows['typing']['position'])->toBe(3)
        ->and($rows['typing']['min_successful_reviews'])->toBe(1)
        ->and($rows['dictation']['source'])->toBe('global'); // untouched

    // The global row is unaffected — a user row is that user's setting, not a global edit.
    $global = collect($this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/admin/api/mode-settings')->assertOk()->json('rows'))->keyBy('mode');
    expect($global['typing']['min_successful_reviews'])->toBe(4);

    $entry = DB::table('admin_audit_log')->where('action', 'learning.mode_settings.user')->first();
    expect($entry)->not->toBeNull()
        ->and($entry->admin_id)->toBe($admin->id)
        ->and($entry->target_user_id)->toBe($user->id);
    $context = json_decode((string) $entry->context, true);
    expect($context['mode'])->toBe('typing')
        ->and($context['to']['min_successful_reviews'])->toBe(1);
});

it('writes the global row and logs it with no target user', function () {
    [$admin, $token] = adminActor();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/admin/api/mode-settings', [
            'mode' => 'dictation', 'enabled' => true, 'position' => 9,
            'min_acquisition' => 'graduated', 'min_successful_reviews' => 20, 'options_policy' => 'standard',
        ])
        ->assertOk()
        ->assertJsonPath('rows.0.mode', fn ($v) => true);

    $entry = DB::table('admin_audit_log')->where('action', 'learning.mode_settings.global')->first();
    expect($entry)->not->toBeNull()->and($entry->target_user_id)->toBeNull();
});

it('resets one user override cell back to the global row, and logs the reset', function () {
    [$admin, $token] = adminActor();
    $user = User::factory()->create();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson("/admin/api/users/{$user->id}/mode-settings", [
            'mode' => 'typing', 'enabled' => true, 'position' => 3,
            'min_acquisition' => 'graduated', 'min_successful_reviews' => 1,
        ])->assertOk();

    $rows = collect($this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/admin/api/users/{$user->id}/mode-settings/typing")
        ->assertOk()
        ->json('rows'))->keyBy('mode');

    expect($rows['typing']['source'])->toBe('global')
        ->and($rows['typing']['min_successful_reviews'])->toBe(4);
    expect(DB::table('learning_mode_settings')->where('user_id', $user->id)->where('mode', 'typing')->count())->toBe(0);

    $entry = DB::table('admin_audit_log')->where('action', 'learning.mode_settings.reset')->first();
    expect($entry)->not->toBeNull()->and($entry->target_user_id)->toBe($user->id);
});

it('refuses a row the ladder cannot mean, and an unknown mode', function () {
    [, $token] = adminActor();

    // A reviews threshold is counted from graduation — it says nothing on a recognition rung.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/admin/api/mode-settings', [
            'mode' => 'typing', 'enabled' => true, 'position' => 0,
            'min_acquisition' => 'learning', 'min_learning_step' => 1, 'min_successful_reviews' => 3,
        ])
        ->assertStatus(422);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/admin/api/mode-settings', [
            'mode' => 'time_travel', 'enabled' => true, 'position' => 0, 'min_acquisition' => 'new',
        ])
        ->assertStatus(422);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/admin/api/mode-settings', [
            'mode' => 'typing', 'enabled' => true, 'position' => -1, 'min_acquisition' => 'graduated',
        ])
        ->assertStatus(422);
});

it('404s on a malformed user id instead of 500ing', function () {
    [, $token] = adminActor();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/admin/api/users/not-a-ulid/mode-settings')
        ->assertNotFound();
});

it('answers 401 on every matrix route without a token', function () {
    $id = Ulid::generate();
    $routes = [
        ['GET', '/admin/api/mode-settings'],
        ['PUT', '/admin/api/mode-settings'],
        ['GET', "/admin/api/users/{$id}/mode-settings"],
        ['PUT', "/admin/api/users/{$id}/mode-settings"],
        ['DELETE', "/admin/api/users/{$id}/mode-settings/typing"],
    ];

    foreach ($routes as [$method, $uri]) {
        $this->withHeader('Accept', 'text/html')->json($method, $uri)->assertUnauthorized();
    }
});

it('carries each mode\'s constructive floor in the matrix, independent of the stored threshold', function () {
    [, $token] = adminActor();

    $rows = collect($this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/admin/api/mode-settings')
        ->assertOk()
        ->json('rows'))->keyBy('mode');

    expect($rows['intro']['floor'])->toBe('new')
        ->and($rows['multiple_choice']['floor'])->toBe('learning')
        ->and($rows['word_bank']['floor'])->toBe('graduated')
        ->and($rows['typing']['floor'])->toBe('graduated')
        ->and($rows['speaking']['floor'])->toBe('graduated');
});

it('refuses a threshold below the mode\'s passport floor, with a human reason, and accepts it at the floor', function () {
    [, $token] = adminActor();

    $belowFloor = $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/admin/api/mode-settings', [
            'mode' => 'speaking', 'enabled' => true, 'position' => 8,
            'min_acquisition' => 'learning', 'options_policy' => 'standard',
        ])
        ->assertStatus(422);
    expect($belowFloor->json('message'))->toContain('вспоминать');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/admin/api/mode-settings', [
            'mode' => 'multiple_choice', 'enabled' => true, 'position' => 1,
            'min_acquisition' => 'new', 'options_policy' => 'distant',
        ])
        ->assertStatus(422);

    // At the floor itself — the lowest passport-honest setting — the write is accepted.
    $rows = collect($this->withHeader('Authorization', "Bearer {$token}")
        ->putJson('/admin/api/mode-settings', [
            'mode' => 'speaking', 'enabled' => true, 'position' => 8,
            'min_acquisition' => 'graduated', 'options_policy' => 'standard',
        ])
        ->assertOk()
        ->json('rows'))->keyBy('mode');
    expect($rows['speaking']['min_acquisition'])->toBe('graduated');
});

it('renamed the column to min_successful_reviews without moving the /sync wire key', function () {
    [$user, $token] = learner();

    expect(DB::getSchemaBuilder()->hasColumn('learning_mode_settings', 'min_successful_reviews'))->toBeTrue()
        ->and(DB::getSchemaBuilder()->hasColumn('learning_mode_settings', 'min_reps'))->toBeFalse();

    $data = sync($this, $token);
    $byMode = collect($data['settings']['mode_admission'])->keyBy('mode');
    // The wire key is unchanged — /sync still ships `min_reps`, deliberately, for the client.
    expect($byMode['typing'])->toHaveKey('min_reps')
        ->and($byMode['typing'])->not->toHaveKey('min_successful_reviews')
        ->and($byMode['typing']['min_reps'])->toBe(4);
});

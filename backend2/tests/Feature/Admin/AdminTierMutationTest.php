<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('flips a user to premium and writes an audit row', function () {
    $user = User::factory()->create();
    Profile::create(['user_id' => $user->id, 'tier' => 'free']);
    [$admin, $adminToken] = adminActor();

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->postJson("/admin/api/users/{$user->id}/tier", ['tier' => 'premium'])
        ->assertOk()
        ->assertJsonPath('tier', 'premium');

    $this->assertDatabaseHas('profiles', ['user_id' => $user->id, 'tier' => 'premium']);

    $audit = DB::table('admin_audit_log')->where('target_user_id', $user->id)->first();
    expect($audit)->not->toBeNull();
    expect($audit->admin_id)->toBe($admin->id);
    expect($audit->action)->toBe('user.tier.change');
    $context = json_decode((string) $audit->context, true);
    expect($context['from'])->toBe('free');
    expect($context['to'])->toBe('premium');
});

it('validates the tier value', function () {
    $user = User::factory()->create();
    Profile::create(['user_id' => $user->id, 'tier' => 'free']);
    [, $adminToken] = adminActor();

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->postJson("/admin/api/users/{$user->id}/tier", ['tier' => 'gold'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('tier');
});

it('404s a tier change for a user without a profile', function () {
    [, $adminToken] = adminActor();

    $this->withHeader('Authorization', "Bearer {$adminToken}")
        ->postJson('/admin/api/users/' . Ulid::generate() . '/tier', ['tier' => 'premium'])
        ->assertNotFound();

    expect(DB::table('admin_audit_log')->count())->toBe(0);
});

it('rejects the tier mutation without an admin token', function () {
    $user = User::factory()->create();
    Profile::create(['user_id' => $user->id, 'tier' => 'free']);

    $this->postJson("/admin/api/users/{$user->id}/tier", ['tier' => 'premium'])
        ->assertUnauthorized();
});

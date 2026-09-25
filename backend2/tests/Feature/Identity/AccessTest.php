<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * THE LEARNER'S ACCESS (наряд ACC-1 §2): `GET /auth/me` → `access {plan, expires_at, source}`, the owner's commands
 * `access:grant {user} {product} {--until=}` and `access:revoke {user}`, and the admin's read of it
 * (`GET /admin/api/users/{id}/access`, for the page ADM-2). No purchase door — that is PAY-1.
 */

uses(RefreshDatabase::class);

it('tells the learner their access on /auth/me — free by default, then what the owner granted', function () {
    [$user, $token] = learner();
    $me = fn (): array => $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/auth/me')->assertOk()->json('data.access');

    expect($me())->toBe(['plan' => 'free', 'expires_at' => null, 'source' => null]);

    expect(Artisan::call('access:grant', ['user' => $user->email, 'product' => 'lifetime']))->toBe(0);
    expect($me())->toBe(['plan' => 'premium', 'expires_at' => null, 'source' => 'admin']);

    Artisan::call('access:grant', ['user' => $user->id, 'product' => 'month', '--until' => '2099-12-31']);
    expect($me())->toBe(['plan' => 'premium', 'expires_at' => '2100-01-01T00:00:00+00:00', 'source' => 'admin'])
        ->and(DB::table('entitlements')->where('user_id', $user->id)->count())->toBe(1);

    expect(Artisan::call('access:revoke', ['user' => $user->id]))->toBe(0);
    expect($me())->toBe(['plan' => 'free', 'expires_at' => null, 'source' => null]);
    $row = DB::table('entitlements')->where('user_id', $user->id)->first();
    expect($row?->status)->toBe('expired')
        ->and($row?->expires_at)->not->toBeNull();
});

it('writes a month and a year from now when no end is given, and refuses what cannot be granted', function () {
    [$user] = learner();

    Artisan::call('access:grant', ['user' => $user->id, 'product' => 'year']);
    $expires = new DateTimeImmutable((string) DB::table('entitlements')->where('user_id', $user->id)->value('expires_at'));
    expect($expires->format('Y-m-d'))->toBe(now()->addYear()->toDateString());

    expect(Artisan::call('access:grant', ['user' => $user->id, 'product' => 'forever']))->toBe(1)
        ->and(Artisan::call('access:grant', ['user' => $user->id, 'product' => 'lifetime', '--until' => '2030-01-01']))->toBe(1)
        ->and(Artisan::call('access:grant', ['user' => $user->id, 'product' => 'month', '--until' => 'soon']))->toBe(1)
        ->and(Artisan::call('access:grant', ['user' => 'nobody@wt.test', 'product' => 'month']))->toBe(1)
        ->and(Artisan::call('access:grant', ['user' => '01M3NOSUCHUSER0000000000AA', 'product' => 'month']))->toBe(1)
        ->and(Artisan::call('access:revoke', ['user' => 'nobody@wt.test']))->toBe(1)
        ->and(DB::table('entitlements')->count())->toBe(1);
});

it('shows the admin a learner\'s access, every right behind it and the paywall\'s switch — read only', function () {
    [$user] = learner();
    Artisan::call('access:grant', ['user' => $user->id, 'product' => 'lifetime']);
    [, $admin] = adminActor();

    $data = $this->withHeader('Authorization', "Bearer {$admin}")->getJson("/admin/api/users/{$user->id}/access")->assertOk()->json('data');

    expect($data['access'])->toBe(['plan' => 'premium', 'expires_at' => null, 'source' => 'admin'])
        ->and($data['paywall_enabled'])->toBeFalse()
        ->and($data['entitlements'])->toHaveCount(1)
        ->and($data['entitlements'][0])->toMatchArray(['source' => 'admin', 'product' => 'lifetime', 'status' => 'active', 'expires_at' => null, 'active' => true]);
    $this->withHeader('Authorization', "Bearer {$admin}")->getJson('/admin/api/users/01M3NOSUCHUSER0000000000AA/access')->assertNotFound();
});

it('keeps the admin\'s read behind the admin guard — a learner\'s token opens nothing', function () {
    [$user, $token] = learner();

    $this->getJson("/admin/api/users/{$user->id}/access")->assertUnauthorized();
    $this->withHeader('Authorization', "Bearer {$token}")->getJson("/admin/api/users/{$user->id}/access")->assertUnauthorized();
});

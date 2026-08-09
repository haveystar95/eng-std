<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Contract lock for the 401 body shape.
 *
 * BREAKING-401: unauthenticated `api/*` responses used to be Laravel's default
 * `{"message":"Unauthenticated."}` (application/json). Since the admin-panel 401 fix (the
 * app is API-only, so the guest redirect is nulled and AuthenticationException renders as
 * RFC 7807), BOTH `api/*` and `admin/api/*` now answer `application/problem+json`
 * `{type,title,status,code:"unauthenticated",detail}`. These tests pin that shape so any future
 * regression is caught here — and so the mobile client's 401 handler contract is documented.
 */
it('returns RFC7807 problem+json 401 for an unauthenticated mobile api/* request', function () {
    $this->getJson('/api/v1/stats')
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('status', 401)
        ->assertJsonPath('code', 'unauthenticated')
        ->assertJsonPath('title', 'Unauthenticated');
});

it('returns RFC7807 problem+json 401 for an unauthenticated admin/api/* request', function () {
    $this->getJson('/admin/api/dashboard')
        ->assertStatus(401)
        ->assertHeader('Content-Type', 'application/problem+json')
        ->assertJsonPath('code', 'unauthenticated');
});

it('answers 401 (not a 500 redirect) even without a JSON Accept header on api/*', function () {
    // Browser-style request (no Accept: application/json) must not try to redirect to `login`.
    $this->call('GET', '/api/v1/stats', [], [], [], ['HTTP_ACCEPT' => 'text/html'])
        ->assertStatus(401);
});

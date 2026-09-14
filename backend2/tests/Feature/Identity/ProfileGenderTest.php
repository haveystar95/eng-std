<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * THE LEARNER'S GENDER IN THE PROFILE (наряд GEN-2a): optional, `female` | `male`, cleared with null; given back by
 * `/auth/me` and `PUT /profile`. The lesson prompt reads it as LEARNER_GENDER — «unknown» while it is not said.
 * Catches a profile field the client cannot clear, a value outside the two, and a response that hides it.
 */
it('keeps the gender the learner gives, gives it back, and forgets it on null', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);

    expect($this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/auth/me')->assertOk()->json('data.profile.gender'))->toBeNull();

    $this->withHeader('Authorization', "Bearer {$token}")->putJson('/api/v1/profile', ['gender' => 'female'])
        ->assertOk()->assertJsonPath('data.profile.gender', 'female');
    // Another edit leaves it as it is.
    $this->withHeader('Authorization', "Bearer {$token}")->putJson('/api/v1/profile', ['daily_goal' => 12])
        ->assertOk()->assertJsonPath('data.profile.gender', 'female');
    $this->withHeader('Authorization', "Bearer {$token}")->putJson('/api/v1/profile', ['gender' => null])
        ->assertOk()->assertJsonPath('data.profile.gender', null);
    $this->withHeader('Authorization', "Bearer {$token}")->putJson('/api/v1/profile', ['gender' => 'other'])
        ->assertStatus(422)->assertJsonValidationErrors(['gender']);
});

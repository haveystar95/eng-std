<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\PlanConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * THE PLAN LANGUAGES COME FROM THE SERVER (PLAN-UI-3, owner's decision): one list in
 * `config/plan.php`, offered by `GET /plans/languages` and enforced by `POST /plans`.
 */
function planLanguagesConfigured(array $languages): void
{
    config(['plan.languages' => $languages]);
    // The provider reads the config once into a singleton; a test that changes it re-reads.
    app()->forgetInstance(PlanConfig::class);
}

it('offers English and German by default', function () {
    [, $token] = planLearner();

    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/languages')
        ->assertOk()->assertExactJson(['data' => ['targets' => ['en', 'de']]]);
});

it('offers the languages the server is configured with, in order', function () {
    // Before the first request: the router keeps the controller it built for the route.
    planLanguagesConfigured(['de', 'en', 'fr']);
    [, $token] = planLearner();
    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/languages')
        ->assertOk()->assertExactJson(['data' => ['targets' => ['de', 'en', 'fr']]]);
});

it('refuses a plan in a language outside the list', function () {
    [, $token] = planLearner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', ['goal_text' => 'Иду в банк', 'target_lang' => 'fr', 'level' => 'beginner', 'days_total' => 2])
        ->assertStatus(422)->assertJsonValidationErrors(['target_lang']);

    planLanguagesConfigured(['en', 'fr']);
    expect(planCreate($this, $token, ['target_lang' => 'fr', 'days_total' => 2])['status'])->toBe('ready');
});

it('keeps the language list behind the token', function () {
    $this->getJson('/api/v1/plans/languages')->assertUnauthorized();
});

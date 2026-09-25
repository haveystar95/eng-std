<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\PlanConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * `GET /languages` — BOTH SIDES OF A PLAN'S PAIR, NAMED (наряд LANG-1 §7).
 *
 * The targets are the deployment's effective plan targets (the same list `GET /plans/languages` gives as
 * codes), the natives are `LanguageRoles::planNatives()`; every entry carries its endonym and flag from
 * the one catalogue (`LanguageCatalog`, HYG-1). The expectations are written out, not read from the
 * catalogue: a test that reads its expectation out of the thing under test proves nothing.
 */
it('names every plan target and every plan native, in the order the screens offer them', function () {
    [, $token] = planLearner();

    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/languages')
        ->assertOk()
        ->assertExactJson(['data' => [
            'targets' => [
                ['code' => 'en', 'endonym' => 'English', 'flag' => '🇬🇧'],
                ['code' => 'pl', 'endonym' => 'Polski', 'flag' => '🇵🇱'],
                ['code' => 'ro', 'endonym' => 'Română', 'flag' => '🇷🇴'],
                ['code' => 'es', 'endonym' => 'Español', 'flag' => '🇪🇸'],
                ['code' => 'it', 'endonym' => 'Italiano', 'flag' => '🇮🇹'],
                ['code' => 'de', 'endonym' => 'Deutsch', 'flag' => '🇩🇪'],
                ['code' => 'fr', 'endonym' => 'Français', 'flag' => '🇫🇷'],
            ],
            'natives' => [
                ['code' => 'ru', 'endonym' => 'Русский', 'flag' => '🇷🇺'],
                ['code' => 'uk', 'endonym' => 'Українська', 'flag' => '🇺🇦'],
                ['code' => 'be', 'endonym' => 'Беларуская', 'flag' => '🇧🇾'],
                ['code' => 'pl', 'endonym' => 'Polski', 'flag' => '🇵🇱'],
                ['code' => 'ro', 'endonym' => 'Română', 'flag' => '🇷🇴'],
                ['code' => 'es', 'endonym' => 'Español', 'flag' => '🇪🇸'],
                ['code' => 'it', 'endonym' => 'Italiano', 'flag' => '🇮🇹'],
                ['code' => 'de', 'endonym' => 'Deutsch', 'flag' => '🇩🇪'],
                ['code' => 'fr', 'endonym' => 'Français', 'flag' => '🇫🇷'],
            ],
        ]]);
});

it('narrows the targets with PLAN_LANGUAGES and never the natives', function () {
    // Before the first request: the router keeps the controller it built for the route.
    config(['plan.languages' => ['de', 'en']]);
    app()->forgetInstance(PlanConfig::class);
    [, $token] = planLearner();

    $response = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/languages')->assertOk();

    expect(array_column($response->json('data.targets'), 'code'))->toBe(['en', 'de'])
        ->and(array_column($response->json('data.natives'), 'code'))->toBe(['ru', 'uk', 'be', 'pl', 'ro', 'es', 'it', 'de', 'fr']);
});

it('offers the same targets as the codes-only list of build (21)', function () {
    [, $token] = planLearner();

    $named = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/languages')->assertOk()->json('data.targets');
    $codes = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/languages')->assertOk()->json('data.targets');

    expect(array_column($named, 'code'))->toBe($codes);
});

it('keeps the named list behind the token', function () {
    $this->getJson('/api/v1/languages')->assertUnauthorized();
});

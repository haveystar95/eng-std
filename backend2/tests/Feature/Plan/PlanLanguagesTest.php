<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\PlanConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * THE PLAN LANGUAGES COME FROM THE SERVER (PLAN-UI-3, owner's decision): one list, offered by
 * `GET /plans/languages` and enforced by `POST /plans`.
 *
 * Since наряд LANG-1 §7 the list is `LanguageRoles::planTargets()` — seven targets, in code — and
 * `config/plan.php` → `languages` (`PLAN_LANGUAGES`) only NARROWS it. The refusal is no longer a
 * validation error on `target_lang` but the pair's own 422 `language_pair_invalid`: the native is the
 * profile's, so only the command can judge the pair.
 */
function planLanguagesConfigured(array $languages): void
{
    config(['plan.languages' => $languages]);
    // The provider reads the config once into a singleton; a test that changes it re-reads. And BEFORE the
    // first request to the route: the router keeps the controller (and its handlers) it built for it.
    app()->forgetInstance(PlanConfig::class);
}

/** POST /plans with the entry's other fields filled; the response is the caller's to assert. */
function planPost(object $ctx, string $token, string $target): Illuminate\Testing\TestResponse
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', ['goal_text' => 'Иду в банк', 'target_lang' => $target, 'level' => 'beginner', 'days_total' => 2]);
}

it('offers the seven plan targets by default, in the order of the entry screen', function () {
    [, $token] = planLearner();

    // The exact shape build (21) on the phone reads — codes only, now seven of them.
    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/languages')
        ->assertOk()->assertExactJson(['data' => ['targets' => ['en', 'pl', 'ro', 'es', 'it', 'de', 'fr']]]);
});

it('narrows the list by PLAN_LANGUAGES, keeping the order of the targets and dropping what is not one', function () {
    // Written in another order, one in capitals, one code that is not a plan target: the flag narrows, it
    // never reorders the screen and never adds a language (DECISIONS п. 82, п. 145).
    planLanguagesConfigured(['fr', 'DE', 'en', 'pt']);
    [, $token] = planLearner();

    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/languages')
        ->assertOk()->assertExactJson(['data' => ['targets' => ['en', 'de', 'fr']]]);
});

it('reads an empty narrowing as no narrowing', function () {
    planLanguagesConfigured([]);
    [, $token] = planLearner();

    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/languages')
        ->assertOk()->assertJsonCount(7, 'data.targets');
});

it('refuses a target the deployment narrowed away, as the pair it makes, and writes nothing', function () {
    planLanguagesConfigured(['en', 'de']);
    [, $token] = planLearner();

    planPost($this, $token, 'fr')
        ->assertStatus(422)
        ->assertJsonPath('code', 'language_pair_invalid')
        ->assertJsonPath('meta', ['target' => 'fr', 'native' => 'ru']);

    $this->assertDatabaseCount('plans', 0);
});

it('offers nothing when the narrowing keeps no plan target, and refuses every pair', function () {
    // `config/plan.php`: «a list that keeps none of them offers none» — a code outside the targets is dropped, and
    // what is left is not widened back to every target (only an EMPTY narrowing means «all»).
    planLanguagesConfigured(['pt']);
    [, $token] = planLearner();

    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/languages')
        ->assertOk()->assertExactJson(['data' => ['targets' => []]]);
    planPost($this, $token, 'en')
        ->assertStatus(422)
        ->assertJsonPath('code', 'language_pair_invalid');

    $this->assertDatabaseCount('plans', 0);
});

it('asks the pair before the paywall: an impossible pair is a 422, never the 402 of a spent free plan', function () {
    // The switch before the first request (the route's controller keeps the paywall it was built with).
    config(['access.paywall_enabled' => true]);
    [, $token] = planLearner();
    planCreate($this, $token, ['days_total' => 2]);

    // The free plan is spent, so a possible pair is the paywall's 402 — the paywall IS on in this test …
    planPost($this, $token, 'de')->assertStatus(402)->assertJsonPath('code', 'plan_subscription_required');
    // … and an impossible one is still the pair's 422: asked first (наряд LANG-1 §7).
    planPost($this, $token, 'pt')
        ->assertStatus(422)
        ->assertJsonPath('code', 'language_pair_invalid')
        ->assertJsonPath('meta', ['target' => 'pt', 'native' => 'ru']);

    $this->assertDatabaseCount('plans', 1);
});

it('builds a plan in a target the list offers', function () {
    planLanguagesConfigured(['en', 'fr']);
    [, $token] = planLearner();

    expect(planCreate($this, $token, ['target_lang' => 'fr', 'days_total' => 2])['status'])->toBe('ready');
});

it('refuses a target that is not a plan target at all', function (string $target) {
    [, $token] = planLearner();

    planPost($this, $token, $target)
        ->assertStatus(422)
        ->assertJsonPath('code', 'language_pair_invalid')
        ->assertJsonPath('meta.target', $target);

    $this->assertDatabaseCount('plans', 0);
})->with([
    'a catalogue language no plan teaches' => ['pt'],
    'a reference-only language' => ['zh'],
    'a code the catalogue does not know' => ['sv'],
    'a native that is not a target' => ['uk'],
]);

it('refuses a plan read in a language no plan is read in', function () {
    // The profile may hold any catalogue language (п. 85) — Turkish is one — but no plan is read in it.
    [$user, $token] = planLearner();
    DB::table('profiles')->where('user_id', $user->id)->update(['native_language' => 'tr']);

    planPost($this, $token, 'en')
        ->assertStatus(422)
        ->assertJsonPath('code', 'language_pair_invalid')
        ->assertJsonPath('meta', ['target' => 'en', 'native' => 'tr']);

    $this->assertDatabaseCount('plans', 0);
});

// Наряд LANG-1 (валидатор): `PUT /profile` took any 2–5 characters as the native before §7, so a profile may still hold a
// native that is no language code at all. CATCHES `POST /plans` answering 500 for it (the calendar's `LanguageCode`
// thrown through), a plan built for such a learner in some default language, and the learner's plan list broken by the
// same stored value (the zone and the day read with the native, and failing with it).
it('refuses a plan read in a stored native that is no language code, as the pair it makes, and still lists the learner\'s plans', function (string $stored) {
    [$user, $token] = planLearner();
    DB::table('profiles')->where('user_id', $user->id)->update(['native_language' => $stored]);

    planPost($this, $token, 'en')
        ->assertStatus(422)
        ->assertJsonPath('code', 'language_pair_invalid')
        ->assertJsonPath('meta', ['target' => 'en', 'native' => '']);
    $this->assertDatabaseCount('plans', 0);

    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans')->assertOk()->assertJsonPath('data', []);
})->with([
    'a locale' => ['en_US'],
    'three letters' => ['rus'],
]);

it('refuses a plan whose target is the learner\'s own language', function () {
    [$user, $token] = planLearner();
    DB::table('profiles')->where('user_id', $user->id)->update(['native_language' => 'de']);

    planPost($this, $token, 'de')
        ->assertStatus(422)
        ->assertJsonPath('code', 'language_pair_invalid')
        ->assertJsonPath('meta', ['target' => 'de', 'native' => 'de']);

    $this->assertDatabaseCount('plans', 0);
});

it('builds a plan for a learner who reads Belarusian', function () {
    // The native comes from the profile and nowhere else — the request names only the target (п. 159/180).
    [$user, $token] = planLearner();
    DB::table('profiles')->where('user_id', $user->id)->update(['native_language' => 'be']);

    $build = planCreate($this, $token, ['target_lang' => 'pl', 'days_total' => 2]);

    $this->assertDatabaseHas('plans', ['id' => $build['id'], 'target_lang' => 'pl', 'native_lang' => 'be']);
});

it('keeps malformed input a validation error', function (mixed $target) {
    [, $token] = planLearner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', ['goal_text' => 'Иду в банк', 'target_lang' => $target, 'level' => 'beginner', 'days_total' => 2])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['target_lang']);
})->with([
    'a name, not a code' => ['english'],
    'three letters' => ['xyz'],
    'upper case' => ['EN'],
    'a number' => [42],
]);

it('keeps the language list behind the token', function () {
    $this->getJson('/api/v1/plans/languages')->assertUnauthorized();
});

<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Service\Clock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Doubles\FixedClock;

/**
 * «ОДИН ПЛАН, ДЕНЬ 1 БЕСПЛАТНО» OVER HTTP (наряд ACC-1 §2) — the canon of the paywall:
 *
 * «первый план бесплатный, день 1 открыт, день 2 subscription; второй план — 402; grant снимает; истёкшая — снова заперто;
 * при рубильнике false — как раньше». And around it: the cap of three plans in work for a subscriber (409), another plan's
 * day 1 locked too, a day being walked never taken away, and no reminder for a day the learner cannot open.
 *
 * The switch is set BEFORE the first request of a test: a route's controller is built once per test and keeps the
 * paywall it was built with.
 */

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/** @return array<string, mixed> day `$n` of the plan as `GET /plans/{id}` has it */
function pwDay(object $ctx, string $token, string $id, int $n): array
{
    return planRead($ctx, $token, $id)['days'][$n - 1];
}

function pwStart(object $ctx, string $token, string $id): void
{
    $ctx->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
}

/** A plan asked for, and the answer as it came — a refusal is not a failure here. */
function pwCreate(object $ctx, string $token): Illuminate\Testing\TestResponse
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/plans', [
        'goal_text' => 'Иду к врачу с ребёнком, болит спина. Первый раз в местной клинике.',
        'target_lang' => 'en', 'level' => 'beginner', 'days_total' => 2,
    ]);
}

it('opens day 1 of the free plan whole, the talk with it, and locks day 2 behind the subscription', function () {
    config(['access.paywall_enabled' => true]);
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    pwStart($this, $token, $id);

    expect(pwDay($this, $token, $id, 1))->toMatchArray(['status' => 'open', 'lock_reason' => null])
        ->and(pwDay($this, $token, $id, 2))->toMatchArray(['status' => 'locked', 'lock_reason' => 'subscription']);

    // Day 1 walked to its end — the talk of the sixth stage too.
    planWalkDay($this, $token, $id, 1);
    expect(DB::table('plan_stage_passages')->where('plan_id', $id)->whereNotNull('conversation_id')->count())->toBe(1);

    // Its calendar day come, day 2 is still the subscription's: on the route, in the room, at the door.
    planShiftDay($id);
    $room = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/2")->assertOk()->json('data');
    expect(pwDay($this, $token, $id, 2))->toMatchArray(['status' => 'locked', 'lock_reason' => 'subscription'])
        ->and(array_column(pwDay($this, $token, $id, 2)['stages'], 'state'))->not->toContain('current')
        ->and($room['day'])->toMatchArray(['status' => 'locked', 'lock_reason' => 'subscription'])
        ->and($room['window']['day']['status'])->toBe('locked')
        ->and($room['window']['allowed_action'])->toBeNull();
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/2/open")
        ->assertStatus(409)
        ->assertJsonPath('code', 'plan_day_locked')
        ->assertJsonPath('meta.lock_reason', 'subscription');
});

it('refuses a plan beyond the free one without a subscription — deleting the free one buys no second', function () {
    config(['access.paywall_enabled' => true]);
    [, $token] = planLearner();
    $first = planCreate($this, $token, ['days_total' => 2])['id'];

    pwCreate($this, $token)->assertStatus(402)->assertJsonPath('code', 'plan_subscription_required');
    $this->withHeader('Authorization', "Bearer {$token}")->deleteJson("/api/v1/plans/{$first}")->assertNoContent();
    pwCreate($this, $token)->assertStatus(402)->assertJsonPath('code', 'plan_subscription_required');
    expect(DB::table('plans')->count())->toBe(1);
});

it('lifts every lock with a subscription, and locks day 2 again once it has run out — never a day being walked', function () {
    config(['access.paywall_enabled' => true]);
    [$user, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 3])['id'];
    pwStart($this, $token, $id);
    planWalkDay($this, $token, $id, 1);
    planShiftDay($id);

    Artisan::call('access:grant', ['user' => $user->email, 'product' => 'lifetime']);
    expect(pwDay($this, $token, $id, 2))->toMatchArray(['status' => 'open', 'lock_reason' => null]);
    pwCreate($this, $token)->assertStatus(202);

    // Taken back: locked again. A right that ended yesterday opens nothing either.
    Artisan::call('access:revoke', ['user' => $user->id]);
    expect(pwDay($this, $token, $id, 2))->toMatchArray(['status' => 'locked', 'lock_reason' => 'subscription']);
    Artisan::call('access:grant', ['user' => $user->id, 'product' => 'month', '--until' => now()->subDay()->toIso8601String()]);
    expect(pwDay($this, $token, $id, 2))->toMatchArray(['status' => 'locked', 'lock_reason' => 'subscription']);

    // A month in force: day 2 opens. Its end comes while it is being walked — the day stays the learner's; day 3 waits.
    Artisan::call('access:grant', ['user' => $user->id, 'product' => 'month']);
    planOpenDay($this, $token, $id, 2);
    Artisan::call('access:revoke', ['user' => $user->id]);
    expect(pwDay($this, $token, $id, 2))->toMatchArray(['status' => 'in_progress', 'lock_reason' => null])
        ->and(pwDay($this, $token, $id, 3))->toMatchArray(['status' => 'locked', 'lock_reason' => 'subscription']);
    planOpenDay($this, $token, $id, 2);
});

it('gives a subscriber three plans in work and refuses the fourth until one is gone', function () {
    config(['access.paywall_enabled' => true]);
    [$user, $token] = planLearner();
    Artisan::call('access:grant', ['user' => $user->id, 'product' => 'year']);
    $first = pwCreate($this, $token)->assertStatus(202)->json('data.id');
    pwCreate($this, $token)->assertStatus(202);
    pwCreate($this, $token)->assertStatus(202);

    pwCreate($this, $token)->assertStatus(409)
        ->assertJsonPath('code', 'plan_active_limit')
        ->assertJsonPath('meta.limit', 3)
        ->assertJsonPath('meta.plans_in_work', 3);

    $this->withHeader('Authorization', "Bearer {$token}")->deleteJson("/api/v1/plans/{$first}")->assertNoContent();
    pwCreate($this, $token)->assertStatus(202);
});

it('locks a plan that is not the free one from its day 1 once the subscription is gone', function () {
    config(['access.paywall_enabled' => true]);
    [$user, $token] = planLearner();
    Artisan::call('access:grant', ['user' => $user->id, 'product' => 'lifetime']);
    planCreate($this, $token, ['days_total' => 2]);
    $second = planCreate($this, $token, ['days_total' => 2])['id'];
    pwStart($this, $token, $second);
    Artisan::call('access:revoke', ['user' => $user->id]);

    expect(pwDay($this, $token, $second, 1))->toMatchArray(['status' => 'locked', 'lock_reason' => 'subscription']);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$second}/days/1/open")
        ->assertStatus(409)->assertJsonPath('meta.lock_reason', 'subscription');
});

it('sends no daily reminder for a day the learner cannot open, and sends it once they can', function () {
    config(['access.paywall_enabled' => true]);
    [$user, $token] = planLearner();
    Artisan::call('access:grant', ['user' => $user->id, 'product' => 'lifetime']);
    planCreate($this, $token, ['days_total' => 3]);
    $second = planCreate($this, $token, ['days_total' => 3])['id'];
    pwStart($this, $token, $second);
    Artisan::call('access:revoke', ['user' => $user->id]);
    $today = now()->utc()->toDateString();
    $tomorrow = now()->utc()->addDay()->toDateString();

    app()->instance(Clock::class, new FixedClock(new DateTimeImmutable("{$today}T19:00:00Z")));
    Artisan::call('plan:notify-tick');
    expect(DB::table('plan_notifications')->where('kind', 'daily_reminder')->count())->toBe(0);

    Artisan::call('access:grant', ['user' => $user->id, 'product' => 'lifetime']);
    app()->instance(Clock::class, new FixedClock(new DateTimeImmutable("{$tomorrow}T19:00:00Z")));
    Artisan::call('plan:notify-tick');
    expect(DB::table('plan_notifications')->where('kind', 'daily_reminder')->where('plan_id', $second)->count())->toBe(1);
});

it('locks nothing and refuses nothing while the paywall is off — as before the наряд — and still tells the access', function () {
    [$user, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    pwStart($this, $token, $id);
    expect(pwDay($this, $token, $id, 2)['lock_reason'])->toBe('date');
    planWalkDay($this, $token, $id, 1);
    planShiftDay($id);

    expect(pwDay($this, $token, $id, 2))->toMatchArray(['status' => 'open', 'lock_reason' => null]);
    planOpenDay($this, $token, $id, 2);
    foreach (range(1, 4) as $n) {
        pwCreate($this, $token)->assertStatus(202);
    }
    $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/auth/me')->assertOk()
        ->assertJsonPath('data.access', ['plan' => 'free', 'expires_at' => null, 'source' => null]);
    expect(DB::table('plans')->where('user_id', $user->id)->count())->toBe(5);
});

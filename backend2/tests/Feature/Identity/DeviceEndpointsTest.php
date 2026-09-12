<?php

declare(strict_types=1);

use App\Modules\Identity\Application\Query\GetUsualVisitTime;
use App\Modules\Identity\Application\Query\GetUsualVisitTimeHandler;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Doubles\FixedClock;

/**
 * DEVICES (PLAN-UI-3): `PUT|DELETE /devices/push-token`, `POST /devices/visit`, and the usual visit
 * time read off the visits.
 */
uses(RefreshDatabase::class);

function devicePut(object $ctx, string $token, array $body): mixed
{
    app('auth')->forgetGuards(); // the request guard remembers the last bearer within one test

    return $ctx->withHeader('Authorization', "Bearer {$token}")->putJson('/api/v1/devices/push-token', $body);
}

it('registers a push token and moves it when another account signs in on the phone — catches one phone getting two learners’ letters', function () {
    [$first, $firstToken] = learner();
    [$second, $secondToken] = learner();
    $apns = str_repeat('ab', 32);

    devicePut($this, $firstToken, ['platform' => 'ios', 'token' => $apns, 'locale' => 'ru-UA', 'timezone' => 'Europe/Kyiv'])->assertNoContent();
    devicePut($this, $firstToken, ['platform' => 'ios', 'token' => $apns, 'locale' => 'ru-UA', 'timezone' => 'Europe/Kyiv'])->assertNoContent();
    expect(DB::table('device_push_tokens')->count())->toBe(1)
        ->and(DB::table('device_push_tokens')->value('user_id'))->toBe($first->id);

    devicePut($this, $secondToken, ['platform' => 'ios', 'token' => $apns, 'locale' => 'en-US', 'timezone' => 'UTC'])->assertNoContent();

    $rows = DB::table('device_push_tokens')->get();
    expect($rows)->toHaveCount(1)
        ->and($rows[0]->user_id)->toBe($second->id)
        ->and($rows[0]->locale)->toBe('en-US')
        ->and($rows[0]->timezone)->toBe('UTC');
});

it('deletes only the caller’s own token — catches one account unregistering another’s phone', function () {
    [, $owner] = learner();
    [, $stranger] = learner();
    $apns = str_repeat('cd', 32);
    devicePut($this, $owner, ['platform' => 'ios', 'token' => $apns])->assertNoContent();

    app('auth')->forgetGuards();
    $this->withHeader('Authorization', "Bearer {$stranger}")->deleteJson('/api/v1/devices/push-token', ['platform' => 'ios', 'token' => $apns])->assertNoContent();
    expect(DB::table('device_push_tokens')->count())->toBe(1);

    app('auth')->forgetGuards();
    $this->withHeader('Authorization', "Bearer {$owner}")->deleteJson('/api/v1/devices/push-token', ['platform' => 'ios', 'token' => $apns])->assertNoContent();
    expect(DB::table('device_push_tokens')->count())->toBe(0);
});

it('validates the device endpoints and refuses a stranger — catches an Android token or a bad zone stored as iOS', function () {
    [, $token] = learner();

    devicePut($this, $token, ['platform' => 'android', 'token' => '', 'timezone' => 'Mars/Olympus'])
        ->assertStatus(422)->assertJsonValidationErrors(['platform', 'token', 'timezone']);
    app('auth')->forgetGuards();
    $this->withHeader('Authorization', "Bearer {$token}")->deleteJson('/api/v1/devices/push-token', [])
        ->assertStatus(422)->assertJsonValidationErrors(['platform', 'token']);
    app('auth')->forgetGuards();
    $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/devices/visit', ['timezone' => 'Nowhere/Land'])
        ->assertStatus(422)->assertJsonValidationErrors(['timezone']);

    app('auth')->forgetGuards();
    $this->flushHeaders()->putJson('/api/v1/devices/push-token', ['platform' => 'ios', 'token' => 'x'])->assertStatus(401);
    $this->flushHeaders()->postJson('/api/v1/devices/visit')->assertStatus(401);
});

it('records a visit at most once per half hour — catches foreground pings inflating the visit log', function () {
    [$user, $token] = learner();
    $t0 = new DateTimeImmutable('2026-09-12T19:00:00Z');
    $visit = function (string $offset) use ($token, $t0): void {
        app()->instance(Clock::class, new FixedClock($t0->modify($offset)));
        $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/devices/visit', ['timezone' => 'Europe/Kyiv'])->assertNoContent();
    };

    $visit('+0 minutes');
    $visit('+10 minutes');
    $visit('+29 minutes');
    expect(DB::table('user_visits')->where('user_id', $user->id)->count())->toBe(1);

    $visit('+30 minutes');
    $visit('+45 minutes');
    $visit('+61 minutes');
    expect(DB::table('user_visits')->where('user_id', $user->id)->count())->toBe(3);
});

it('reads the usual visit time as the median of the last seven visits in the profile zone — catches visits read in UTC', function () {
    [$user] = learner();
    profileFor($user, ['timezone' => 'Europe/Kyiv']);
    $usual = app(GetUsualVisitTimeHandler::class);

    $empty = $usual(new GetUsualVisitTime(UserId::fromString($user->id)));
    expect($empty->hhmm())->toBe('19:00')->and($empty->visitsCounted)->toBe(0);

    // Kyiv is UTC+3 in September: 17:40 UTC is 20:40 local. An old morning visit falls out of the seven.
    $utc = ['2026-09-01T05:00:00Z', '2026-09-05T17:40:00Z', '2026-09-06T17:05:00Z', '2026-09-07T18:10:00Z',
        '2026-09-08T17:25:00Z', '2026-09-09T17:50:00Z', '2026-09-10T16:55:00Z', '2026-09-11T17:30:00Z'];
    foreach ($utc as $at) {
        DB::table('user_visits')->insert(['id' => Ulid::generate(), 'user_id' => $user->id, 'visited_at' => $at]);
    }

    $view = $usual(new GetUsualVisitTime(UserId::fromString($user->id)));
    // Local: 20:40, 20:05, 21:10, 20:25, 20:50, 19:55, 20:30 → median 20:30 → 20:30.
    expect($view->hhmm())->toBe('20:30')
        ->and($view->visitsCounted)->toBe(7)
        ->and($view->timezone)->toBe('Europe/Kyiv');
});

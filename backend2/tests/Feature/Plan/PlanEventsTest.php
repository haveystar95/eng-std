<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\NotificationRecord;
use App\Modules\Plan\Application\Port\NotificationLog;
use App\Modules\Plan\Domain\Entity\PlanEvent;
use App\Modules\Plan\Domain\Repository\PlanEventRepository;
use App\Modules\Plan\Domain\ValueObject\DeliveryStatus;
use App\Modules\Plan\Domain\ValueObject\NotificationKind;
use App\Modules\Plan\Domain\ValueObject\PlanEventId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Infrastructure\Push\DryRunPushSender;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Doubles\FixedClock;

/**
 * THE PLAN'S JOURNAL AND LETTERS (PLAN-UI-3), through the real handlers, the sync queue and the
 * dry-run sender (no APNs key under test).
 */
uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/** @return list<string> */
function notifEventKinds(string $planId): array
{
    return DB::table('plan_events')->where('plan_id', $planId)->orderBy('occurred_at')->orderBy('id')->pluck('kind')->all();
}

function notifTick(string $at): void
{
    app()->instance(Clock::class, new FixedClock(new DateTimeImmutable($at)));
    Artisan::call('plan:notify-tick');
}

it('writes plan_ready when the build finishes and sends «План готов» in dry mode — catches a silent build and a letter pretending it was sent', function () {
    Log::spy();
    [$user, $token] = planLearner();

    $build = planCreate($this, $token, ['days_total' => 5]);

    expect(notifEventKinds($build['id']))->toBe(['plan_ready', 'day_ready']);
    $dayOne = DB::table('plan_events')->where('plan_id', $build['id'])->where('kind', 'day_ready')->first();
    expect($dayOne->day_number)->toBe(1)->and($dayOne->user_id)->toBe($user->id);

    // Day 1's readiness is part of the plan: one letter, not two.
    $letters = DB::table('plan_notifications')->where('plan_id', $build['id'])->get();
    expect($letters)->toHaveCount(1)
        ->and($letters[0]->kind)->toBe('plan_ready')
        ->and($letters[0]->status)->toBe('not_sent')
        ->and($letters[0]->reason)->toBe(DryRunPushSender::REASON)
        ->and($letters[0]->event_id)->not->toBeNull();

    Log::shouldHaveReceived('info')->withArgs(static fn (string $message, array $context): bool => $message === 'plan push (dry run) — letter not sent'
        && $context['title'] === 'План готов'
        && $context['body'] === '5 дней. День 1 — «Запись к врачу»'
        && $context['user_id'] === $user->id
        && $context['tokens'] === 0);
});

it('writes day_ready for day 2 when closing day 1 builds its lesson, and says «День 2 собран» — catches a day that was built with nobody told', function () {
    Log::spy();
    [, $token] = planLearner();
    $build = planCreate($this, $token, ['days_total' => 5]);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$build['id']}/start")->assertOk();

    planWalkDay($this, $token, $build['id'], 1);

    $day2 = DB::table('plan_events')->where('plan_id', $build['id'])->where('kind', 'day_ready')->where('day_number', 2)->first();
    expect($day2)->not->toBeNull()
        ->and(json_decode((string) $day2->payload, true))->toHaveKey('scene_id');
    expect(DB::table('plan_notifications')->where('plan_id', $build['id'])->where('kind', 'day_ready')->value('day_number'))->toBe(2);
    Log::shouldHaveReceived('info')->withArgs(static fn (string $m, array $c): bool => $c['title'] === 'День 2 собран' && $c['body'] === '«Приём у врача» — можно начинать');
});

it('writes day_passed from closing the day, with no letter — catches a journal that misses the walked day', function () {
    [, $token] = planLearner();
    $build = planCreate($this, $token, ['days_total' => 2]);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$build['id']}/start")->assertOk();
    $lettersBefore = DB::table('plan_notifications')->where('plan_id', $build['id'])->count();

    planWalkDay($this, $token, $build['id'], 1);

    $passed = DB::table('plan_events')->where('plan_id', $build['id'])->where('kind', 'day_passed')->get();
    expect($passed)->toHaveCount(1)
        ->and($passed[0]->day_number)->toBe(1)
        ->and($passed[0]->day_id)->not->toBeNull()
        // Opening day 1 built day 2 (one letter); closing it adds none.
        ->and(DB::table('plan_notifications')->where('plan_id', $build['id'])->count())->toBe($lettersBefore + 1);
});

it('writes days_skipped_rebuilt {from, to} only when the route shrinks, and letters it only for a live plan — catches a «rebuild» for a longer plan', function () {
    Log::spy();
    [, $token] = planLearner();
    $build = planCreate($this, $token, ['days_total' => 5]);
    $id = $build['id'];

    // In the preview: the fact is journaled, but the learner's own tap is not news.
    $this->withHeader('Authorization', "Bearer {$token}")->patchJson("/api/v1/plans/{$id}/schedule", ['days_total' => 7])->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")->patchJson("/api/v1/plans/{$id}/schedule", ['days_total' => 6])->assertOk();
    $rows = DB::table('plan_events')->where('plan_id', $id)->where('kind', 'days_skipped_rebuilt')->get();
    expect($rows)->toHaveCount(1)
        ->and(json_decode((string) $rows[0]->payload, true))->toEqual(['from' => 7, 'to' => 6]) // jsonb keeps its own key order
        ->and(DB::table('plan_notifications')->where('plan_id', $id)->where('kind', 'days_skipped_rebuilt')->count())->toBe(0);

    // Started: the same change is a letter.
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")->patchJson("/api/v1/plans/{$id}/schedule", ['days_total' => 4])->assertOk();

    expect(DB::table('plan_notifications')->where('plan_id', $id)->where('kind', 'days_skipped_rebuilt')->value('status'))->toBe('not_sent');
    Log::shouldHaveReceived('info')->withArgs(static fn (string $m, array $c): bool => $c['title'] === 'Маршрут пересобран' && $c['body'] === 'Было 6 дней, стало 4');
});

it('appends event_today once on the event day at the reminder hour and event_passed after it, however many ticks run — catches a journal that doubles, a letter every quarter hour and a second letter that day', function () {
    Log::spy();
    [, $token] = planLearner();
    $eventDate = now()->utc()->addDays(2)->startOfDay();
    $build = planCreate($this, $token, ['days_total' => 2, 'event_date' => $eventDate->toDateString()]);
    $id = $build['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    $day = $eventDate->toDateString();

    // No visits: the reminder hour is 19:00 (UTC learner) — «Сегодня разговор» waits for it.
    notifTick("{$day}T08:00:00Z");
    notifTick("{$day}T18:45:00Z");
    expect(notifEventKinds($id))->not->toContain('event_today');

    notifTick("{$day}T19:00:00Z");
    notifTick("{$day}T19:10:00Z");
    notifTick("{$day}T20:30:00Z");
    // The event day's one letter is «Сегодня …» — no daily reminder beside it.
    expect(DB::table('plan_notifications')->where('plan_id', $id)->where('kind', 'daily_reminder')->count())->toBe(0);

    expect(array_count_values(notifEventKinds($id))['event_today'] ?? 0)->toBe(1)
        ->and(DB::table('plan_notifications')->where('plan_id', $id)->where('kind', 'event_today')->count())->toBe(1);
    Log::shouldHaveReceived('info')->withArgs(static fn (string $m, array $c): bool => $c['title'] === 'Сегодня приём' && $c['body'] === 'Скажи сам перед разговором — прогони его вслух');

    $after = $eventDate->addDay()->toDateString();
    notifTick("{$after}T00:15:00Z");
    notifTick("{$after}T00:30:00Z");
    $kinds = array_count_values(notifEventKinds($id));
    expect($kinds['event_passed'] ?? 0)->toBe(1)
        ->and($kinds['event_today'])->toBe(1)
        // event_passed is journal only.
        ->and(DB::table('plan_notifications')->where('plan_id', $id)->whereNotIn('kind', ['plan_ready', 'day_ready', 'event_today'])->count())->toBe(0);
});

it('sends the daily reminder at most once a day — «уведомление не чаще раза в сутки» — two ticks in the window, one row', function () {
    Log::spy();
    [$user, $token] = planLearner();
    $build = planCreate($this, $token, ['days_total' => 3]);
    $id = $build['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    $today = now()->utc()->toDateString();

    // No visits: the usual time is 19:00 (UTC learner). Before the window, nothing.
    notifTick("{$today}T18:45:00Z");
    expect(DB::table('plan_notifications')->where('kind', 'daily_reminder')->count())->toBe(0);

    notifTick("{$today}T19:00:00Z");
    notifTick("{$today}T19:10:00Z"); // an overlapping or repeated tick inside the same window
    notifTick("{$today}T19:15:00Z"); // window closed

    $reminders = DB::table('plan_notifications')->where('kind', 'daily_reminder')->get();
    expect($reminders)->toHaveCount(1)
        ->and($reminders[0]->user_id)->toBe($user->id)
        ->and($reminders[0]->day_number)->toBe(1)
        ->and((string) $reminders[0]->local_date)->toStartWith($today)
        ->and($reminders[0]->status)->toBe('not_sent');
    Log::shouldHaveReceived('info')->withArgs(static fn (string $m, array $c): bool => $c['title'] === 'День 1 ждёт' && $c['body'] === '«Запись к врачу» — начни с того места, где остановился');

    // And the schema holds the rule even for a writer that skipped the check.
    $second = app(NotificationLog::class)->record(new NotificationRecord(
        Ulid::generate(), $user->id, $id, null, NotificationKind::DailyReminder, 1, $today, DeliveryStatus::NotSent, 'race',
    ));
    expect($second)->toBeFalse()
        ->and(DB::table('plan_notifications')->where('kind', 'daily_reminder')->count())->toBe(1);
});

it('aims the reminder at the learner’s usual visit time in their zone — catches a reminder at 19:00 UTC for a morning learner in Kyiv', function () {
    [$user, $token] = planLearner('Europe/Kyiv');
    $build = planCreate($this, $token, ['days_total' => 3]);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$build['id']}/start")->assertOk();

    // Tomorrow in Kyiv — day 1 is still open and waiting. Visits at 08:20 Kyiv (05:20 UTC in September).
    $kyivTomorrow = (new DateTimeImmutable('tomorrow', new DateTimeZone('Europe/Kyiv')));
    foreach (range(1, 5) as $n) {
        DB::table('user_visits')->insert(['id' => Ulid::generate(), 'user_id' => $user->id, 'visited_at' => $kyivTomorrow->modify("-{$n} days")->setTime(8, 20)->format(DATE_ATOM)]);
    }

    notifTick($kyivTomorrow->setTime(19, 5)->format(DATE_ATOM));
    expect(DB::table('plan_notifications')->where('kind', 'daily_reminder')->count())->toBe(0);

    // 08:20 visits → the reminder hour 08:00 (whole hour, never earlier than 08:00).
    notifTick($kyivTomorrow->setTime(8, 5)->format(DATE_ATOM));
    expect(DB::table('plan_notifications')->where('kind', 'daily_reminder')->value('local_date'))->toStartWith($kyivTomorrow->format('Y-m-d'));
});

it('keeps the journal and the delivery log append-only — catches an update or delete path sneaking into the ports', function () {
    $methods = static fn (string $interface): array => array_map(
        static fn (ReflectionMethod $m): string => $m->getName(),
        (new ReflectionClass($interface))->getMethods(),
    );

    expect($methods(PlanEventRepository::class))->toEqualCanonicalizing(['append', 'has', 'forPlan'])
        ->and($methods(NotificationLog::class))->toEqualCanonicalizing(['record', 'hasDailyReminder']);

    // And a repeated once-only fact is refused by the schema, not overwritten.
    [, $token] = planLearner();
    $build = planCreate($this, $token, ['days_total' => 1]);
    $events = app(PlanEventRepository::class);
    $first = $events->forPlan(PlanId::fromString($build['id']))[0];
    $again = PlanEvent::record(
        PlanEventId::generate(), $first->userId, $first->planId, $first->kind, new DateTimeImmutable,
    );
    expect($first->kind->value)->toBe('plan_ready')
        ->and($events->append($again))->toBeFalse()
        ->and(DB::table('plan_events')->where('plan_id', $build['id'])->where('kind', 'plan_ready')->count())->toBe(1);
});

it('erases the journal and the delivery log with the account — catches letters outliving their learner', function () {
    [$user, $token] = planLearner();
    $build = planCreate($this, $token, ['days_total' => 2]);
    expect(DB::table('plan_events')->where('user_id', $user->id)->count())->toBeGreaterThan(0)
        ->and(DB::table('plan_notifications')->where('user_id', $user->id)->count())->toBeGreaterThan(0);

    $this->withHeader('Authorization', "Bearer {$token}")->deleteJson('/api/v1/auth/me')->assertSuccessful();

    expect(DB::table('plan_events')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('plan_notifications')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('plans')->where('id', $build['id'])->exists())->toBeFalse();
});

it('sends one letter now from plan:notify-test through the same handler — catches a QA door that bypasses the log', function () {
    [$user, $token] = planLearner();
    planCreate($this, $token, ['days_total' => 2]);

    $exit = Artisan::call('plan:notify-test', ['user' => $user->id, 'kind' => 'plan_ready']);

    expect($exit)->toBe(0)
        ->and(Artisan::output())->toContain('plan_ready: not_sent')
        ->and(DB::table('plan_notifications')->where('user_id', $user->id)->where('kind', 'plan_ready')->count())->toBe(2);
});

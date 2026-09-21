<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Command\StartPlan;
use App\Modules\Plan\Application\Command\StartPlanHandler;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * WHAT THE PLAN OWES THE SCREEN (наряд PLAN-API-FIX-1). The helpers live in PlanApiTest.php.
 *
 * Four things the golden frames and the live run asked for and the contract did not give: a plan
 * that survives «Начать» pressed mid-build, a built-but-unstarted plan that is visible at all, a
 * day room that knows its shape before the day opens, numbers that move while the day is walked,
 * and «завтра» worn by one day.
 */

/** "Начать" pressed from inside the model call — the job is holding the plan it read before it. */
function planStartDuringLessonBuild(string $userId): void
{
    $planId = DB::table('plans')->where('user_id', $userId)->where('status', 'ready')->value('id');
    if (! is_string($planId)) {
        return;
    }
    app(StartPlanHandler::class)(new StartPlan(PlanId::fromString($planId), UserId::fromString($userId)));
}

it('keeps a plan started while its lesson was still building: active, dated, day one open', function () {
    [$user, $token] = planLearner();

    $pressed = false;
    app()->instance(PlanModelPort::class, new FakePlanModel(lesson: function (object $request) use ($user, &$pressed): array {
        // The claim is written, the model is «answering», and the learner presses «Начать».
        if (! $pressed) {
            $pressed = true;
            planStartDuringLessonBuild($user->id);
        }

        return FakePlanModel::lessonPayload($request);
    }));

    // The photos run after the lesson lands — the second writer of the live run, in the same order.
    $build = planCreate($this, $token, ['days_total' => 2]);
    expect($build['status'])->toBe('ready');

    $plan = planRead($this, $token, $build['id']);
    expect($plan['status'])->toBe('active')
        ->and($plan['started_at'])->not->toBeNull()
        ->and($plan['days'][0]['status'])->toBe('open')
        ->and($plan['days'][0]['slot']['code'])->toBe('today')
        // The lesson and the photo the jobs went for are there — nothing was traded for the start.
        ->and($plan['days'][0]['lesson_status'])->toBe('ready')
        ->and($plan['scenes'][0]['image'])->not->toBeNull()
        ->and($plan['cover_image'])->not->toBeNull();

    $row = DB::table('plans')->where('id', $build['id'])->first();
    expect($row->status)->toBe('active')->and($row->started_at)->not->toBeNull();

    // Day 1 really opens — the calendar the job could have overwritten is intact.
    expect(planOpenDay($this, $token, $build['id'], 1)['status'])->toBe('in_progress');
});

it('shows a built plan that was never started in the tab, and the same plan active after «Начать»', function () {
    [, $token] = planLearner();
    $build = planCreate($this, $token, ['days_total' => 3]);

    $current = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/current')->assertOk()->json('data');
    expect($current)->not->toBeNull()
        ->and($current['id'])->toBe($build['id'])
        ->and($current['status'])->toBe('ready')
        ->and($current['started_at'])->toBeNull()
        ->and(array_column($current['days'], 'status'))->toBe(['locked', 'locked', 'locked'])
        ->and(array_column($current['days'], 'opens_on'))->toBe([null, null, null])
        ->and($current['current_day']['number'])->toBe(1);

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$build['id']}/start")->assertOk();

    $after = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/current')->assertOk()->json('data');
    expect($after['id'])->toBe($build['id'])->and($after['status'])->toBe('active');
});

it('knows the day’s shape before it is opened: five stages not started, the programme filled', function () {
    [, $token] = planLearner();
    $build = planCreate($this, $token, ['days_total' => 2]);
    $id = $build['id'];

    $room = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/1")->assertOk()->json('data');
    $total = array_sum(array_column($room['stages'], 'total'));

    // Eight rows: the five card stages of a scene day, «Вспомнить» and «Повторение» (наряд BACK-TAILS-2 §3) absent, the talk.
    expect(array_column($room['stages'], 'state'))->toBe(['current', 'locked', 'locked', 'locked', 'locked', 'absent', 'absent', 'locked'])
        ->and(array_column($room['stages'], 'done'))->toBe([0, 0, 0, 0, 0, 0, 0, 0])
        ->and($total)->toBeGreaterThan(60)
        // D-04: форма дня — это план раздачи, а его карточки носят id, которых нет ни в одной строке: до открытия
        // они не уходят клиенту, иначе на такую карточку можно ответить только 404.
        ->and(array_column($room['stages'], 'cards'))->toBe([[], [], [], [], [], [], [], []])
        ->and($room['program'])->not->toBeEmpty()
        ->and(array_unique(array_column($room['program'], 'state')))->toBe(['pending'])
        ->and($room['metrics'])->toBeNull()
        ->and($room['day']['status'])->toBe('locked');

    // Day 2's lesson is not written until day 1 opens: no lesson, no shape — that is what `absent` is for.
    $two = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/2")->assertOk()->json('data');
    expect(array_unique(array_column($two['stages'], 'state')))->toBe(['absent'])->and($two['program'])->toBe([]);

    // The outline is not a guess: opening the day deals exactly the cards it promised.
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    expect(count(planOpenDay($this, $token, $id, 1)['cards']))->toBe($total);
});

it('counts the day while it is being walked, not only when it closes', function () {
    [, $token] = planLearner();
    $build = planCreate($this, $token, ['days_total' => 2]);
    $id = $build['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();

    $cards = planOpenDay($this, $token, $id, 1)['cards'];
    $answered = 5;
    foreach (array_slice($cards, 0, $answered) as $card) {
        planAnswer($this, $token, $id, 1, $card['id'], 'passed');
    }

    $room = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/1")->assertOk()->json('data');
    expect($room['day']['cards_done'])->toBe($answered)
        ->and($room['day']['cards_total'])->toBe(count($cards))
        ->and($room['day']['minutes_spent'])->toBeGreaterThan(0)
        ->and($room['metrics'])->not->toBeNull()
        ->and($room['metrics']['cards_total'])->toBe(count($cards))
        ->and($room['metrics']['minutes_spent'])->toBeGreaterThan(0);

    // The tab shows the same live count without opening the room.
    $tab = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/current')->assertOk()->json('data');
    expect($tab['current_day']['cards_done'])->toBe($answered);
});

it('gives «завтра» to exactly one day after a day is closed', function () {
    [, $token] = planLearner();
    $build = planCreate($this, $token, ['days_total' => 5]);
    $id = $build['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();

    $tomorrows = static fn (array $plan): array => array_values(array_map(
        static fn (array $d): int => $d['number'],
        array_filter($plan['days'], static fn (array $d): bool => $d['slot']['code'] === 'tomorrow'),
    ));

    planWalkDay($this, $token, $id, 1);
    $after = planRead($this, $token, $id);
    expect($tomorrows($after))->toBe([2])
        ->and($after['days'][2]['slot']['code'])->toBe('date')
        ->and($after['days'][2]['slot']['date'])->toBe(now()->addDays(2)->toDateString());

    // The calendar moves on; day 2 is walked and day 3 takes the slot, alone.
    planShiftDay($id);
    planWalkDay($this, $token, $id, 2);
    $later = planRead($this, $token, $id);
    expect($tomorrows($later))->toBe([3])
        ->and(array_column($later['days'], 'slot'))->toHaveCount(5);
});

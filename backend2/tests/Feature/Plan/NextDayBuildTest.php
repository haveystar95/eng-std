<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Port\NextDayAccess;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Infrastructure\Job\BuildLessonJob;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * THE LESSON OF DAY N+1 IS ASKED FOR WHEN DAY N CLOSES (наряд GEN-3 §11.2, `docs/plan-v2.md` §3): the one trigger after the plan
 * is built; once; only for a learner who may have the next day; a review or the rehearsal asks for the scene day after it. A
 * day next in line whose lesson is not back yet is `building` on the wire and cannot be opened.
 */

/** A started plan of `$days` days on a fake that counts its lesson calls. */
function ndStarted(object $ctx, FakePlanModel $fake, int $days = 2): array
{
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();
    $id = planCreate($ctx, $token, ['days_total' => $days])['id'];
    $ctx->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();

    return [$token, $id];
}

/** The scene id each lesson call was for, in order — read back through the day each scene is laid on. */
function ndAskedDays(FakePlanModel $fake, string $planId): array
{
    $dayOf = DB::table('plan_days')->where('plan_id', $planId)->whereNotNull('scene_id')->pluck('number', 'scene_id')->all();
    $titles = DB::table('plan_scenes')->where('plan_id', $planId)->pluck('id', 'title_native')->all();

    return array_map(static fn ($request): int => $dayOf[$titles[$request->topic]], $fake->lessonRequests);
}

// Наряд GEN-3 §11.2: «единственный триггер: ЗАКРЫТИЕ дня N; идемпотентно: одна сборка на день; повторное закрытие / 409 plan_day_not_open
// ничего не ставит; старый триггер (открытие дня N) снести»; F: «закрытие дня N → ровно одна сборка N+1», «повторное закрытие →
// ничего», «открытие дня N сборку не ставит». Catches a lesson bought when a day is merely opened (and bought again when the
// learner walks away without passing it), a second build for one day, and a close replayed into another paid call.
it('asks for the next day\'s lesson once, when the day closes — not when it opens, and not again', function () {
    $fake = new FakePlanModel;
    [$token, $id] = ndStarted($this, $fake);

    expect(ndAskedDays($fake, $id))->toBe([1]);
    planOpenDay($this, $token, $id, 1);
    expect(ndAskedDays($fake, $id))->toBe([1]);

    planWalkDay($this, $token, $id, 1);
    expect(ndAskedDays($fake, $id))->toBe([1, 2]);

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/close")
        ->assertStatus(409)->assertJsonPath('code', 'plan_day_not_open');
    expect(ndAskedDays($fake, $id))->toBe([1, 2]);
});

// Наряд GEN-3 §11.2: «перед постановкой сборки сервер спрашивает доступ ученика к следующему дню в одном месте; до PAY-1 ответ всегда
// „да“»; F: «доступа нет → сборки нет (подмена ответа)». Catches a build queued past the access check — the day PAY-1 puts
// behind the paywall paid for by the house.
it('asks for no lesson of a next day the learner may not have', function () {
    app()->instance(NextDayAccess::class, new class implements NextDayAccess
    {
        public function nextDayAllowed(Plan $plan, PlanDay $next): bool
        {
            return false;
        }
    });
    $fake = new FakePlanModel;
    [$token, $id] = ndStarted($this, $fake);

    planWalkDay($this, $token, $id, 1);

    expect(ndAskedDays($fake, $id))->toBe([1])
        ->and(planRead($this, $token, $id)['days'][1]['lesson_status'])->toBe('pending');
});

// Наряд GEN-3 §11.2: «дни повторения и репетиции не собираются, но их закрытие ставит сборку следующего содержательного дня по тому же
// правилу». Catches a scene day built a day early (when the scene day before the review closed) and one never built (the review's
// close asked for nothing).
it('asks for the scene day after a review when the review closes, not before', function () {
    $fake = new FakePlanModel;
    [$token, $id] = ndStarted($this, $fake, 5);

    planWalkDay($this, $token, $id, 1);
    planShiftDay($id);
    planWalkDay($this, $token, $id, 2);
    expect(ndAskedDays($fake, $id))->toBe([1, 2]);

    planShiftDay($id);
    planWalkDay($this, $token, $id, 3);
    expect(ndAskedDays($fake, $id))->toBe([1, 2, 4]);
});

// Наряд GEN-3 §11.2: «будущий день без готового урока виден в GET /plans как building (не locked, не failed), без allowed_action;
// открыть → 409 plan_day_building». Catches the day whose lesson is on its way shown as locked or failed, a start button over
// it, and an open that deals an empty day.
it('shows the next day building while its lesson is on its way, with no button, and refuses to open it', function () {
    $fake = new FakePlanModel;
    [$token, $id] = ndStarted($this, $fake);
    Queue::fake();

    planWalkDay($this, $token, $id, 1);
    Queue::assertPushed(BuildLessonJob::class, 1);
    $plan = planRead($this, $token, $id);
    $room = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/2")->assertOk()->json('data');

    expect($plan['days'][1]['status'])->toBe('building')
        ->and($plan['days'][1]['lesson_status'])->toBe('pending')
        ->and($room['day']['status'])->toBe('building')
        ->and($room['window']['day']['status'])->toBe('building')
        ->and($room['window']['allowed_action'])->toBeNull();
    planShiftDay($id);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/2/open")
        ->assertStatus(409)->assertJsonPath('code', 'plan_day_building');
});

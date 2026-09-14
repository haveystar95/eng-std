<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Check\LessonChecker;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

// A walked day is eighty answers in a row; the API's per-minute throttle is not what is under test.
beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

it('builds the plan and day one at creation, and reports the versions on every answer', function () {
    [$user, $token] = planLearner();

    $build = planCreate($this, $token);
    expect($build['status'])->toBe('ready')
        ->and($build['scenes_count'])->toBe(3)
        ->and($build['attempts'])->toBe(1)
        ->and($build['cost_usd'])->toBe('0.000000')
        ->and($build['versions']['prompt_plan'])->toBe('plan-builder-v2')
        ->and($build['versions']['prompt_lesson'])->toBe('lesson-v4')
        ->and($build['versions']['build'])->not->toBe('');

    $plan = planRead($this, $token, $build['id']);
    expect($plan['status'])->toBe('ready')
        ->and($plan['days_total'])->toBe(5)
        ->and($plan['route_summary'])->toBe('5 дней · 3 ситуации, 1 повторение, репетиция')
        ->and($plan['title_native'])->toBe('Приём у врача')
        ->and(array_column($plan['days'], 'type'))->toBe(['scene', 'scene', 'review', 'scene', 'rehearsal'])
        ->and(array_column($plan['days'], 'status'))->toBe(['locked', 'locked', 'locked', 'locked', 'locked'])
        // Day 1's lesson is written before «Начать»; the others wait.
        ->and($plan['days'][0]['lesson_status'])->toBe('ready')
        ->and($plan['days'][1]['lesson_status'])->toBe('pending')
        ->and(count($plan['rescue_kit']))->toBe(5)
        ->and($plan['scenes'][0]['image'])->not->toBeNull()
        ->and($plan['cover_image'])->not->toBeNull();

    $row = DB::table('plans')->where('id', $build['id'])->first();
    expect($row->prompt_version_plan)->toBe('plan-builder-v2')
        ->and($row->build_version)->not->toBeNull()
        ->and(DB::table('plan_scenes')->where('plan_id', $build['id'])->where('lesson_status', 'ready')->value('prompt_version_lesson'))->toBe('lesson-v4')
        ->and(DB::table('plan_terms')->where('user_id', $user->id)->count())->toBe(14);

    $versions = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/versions')->assertOk()->json('data');
    expect($versions)->toHaveKeys(['build', 'prompt_plan', 'prompt_lesson']);
});

it('returns unclear for a goal that is not a situation, and lets the learner retry', function () {
    [, $token] = planLearner();

    $build = planCreate($this, $token, ['goal_text' => 'хочу подтянуть английский']);
    expect($build['status'])->toBe('unclear')
        ->and($build['unclear_reason'])->not->toBeNull()
        ->and($build['scenes_count'])->toBe(0);

    $retried = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$build['id']}/build/retry")->assertStatus(202)->json('data');
    expect($retried['status'])->toBe('unclear')->and($retried['attempts'])->toBe(1);

    // A plan that is not built cannot be started.
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$build['id']}/start")->assertStatus(409)->assertJsonPath('code', 'plan_state');
});

it('validates the entry and hides other learners’ plans', function () {
    [, $token] = planLearner();
    [, $other] = planLearner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', ['goal_text' => '', 'target_lang' => 'english', 'level' => 'expert', 'days_total' => 11])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['goal_text', 'target_lang', 'level', 'days_total']);

    $build = planCreate($this, $token);
    app('auth')->forgetGuards(); // the request guard remembers the last bearer within one test
    $this->withHeader('Authorization', "Bearer {$other}")->getJson("/api/v1/plans/{$build['id']}")->assertStatus(404);
    $this->withHeader('Authorization', "Bearer {$other}")->postJson("/api/v1/plans/{$build['id']}/start")->assertStatus(404);
    app('auth')->forgetGuards();
    $this->flushHeaders()->getJson("/api/v1/plans/{$build['id']}")->assertStatus(401);
});

it('shortens the plan to the event date and says so', function () {
    [, $token] = planLearner();
    $eventDate = now()->addDays(3)->toDateString();

    $build = planCreate($this, $token, ['days_total' => 8, 'event_date' => $eventDate]);
    $plan = planRead($this, $token, $build['id']);

    expect($plan['days_total'])->toBe(3)
        ->and($plan['days_requested'])->toBe(8)
        ->and($plan['days_shortened_from'])->toBe(8)
        ->and($plan['days_left'])->toBe(3)
        ->and($plan['until_phrase'])->toBe('До приёма · 3 дня')
        ->and($build['scenes_count'])->toBe(2);
});

it('lets a scene be removed from the preview: its day becomes a review, the core stays', function () {
    [, $token] = planLearner();
    $build = planCreate($this, $token);
    $plan = planRead($this, $token, $build['id']);
    $core = array_values(array_filter($plan['scenes'], static fn (array $s): bool => $s['priority'] === 1))[0];
    $other = array_values(array_filter($plan['scenes'], static fn (array $s): bool => $s['priority'] !== 1))[0];

    $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/plans/{$build['id']}/scenes/{$core['id']}")
        ->assertStatus(409)->assertJsonPath('code', 'plan_core_scene');

    $after = $this->withHeader('Authorization', "Bearer {$token}")
        ->deleteJson("/api/v1/plans/{$build['id']}/scenes/{$other['id']}")
        ->assertOk()->json('data');

    expect(count($after['scenes']))->toBe(2)
        ->and($after['days_total'])->toBe(5)
        ->and($after['days'][$other['day_number'] - 1]['type'])->toBe('review');
});

it('walks day one with two errors and a skip, closes it, and opens day two tomorrow with the returns', function () {
    [$user, $token] = planLearner();
    $build = planCreate($this, $token);
    $id = $build['id'];

    $started = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk()->json('data');
    expect($started['status'])->toBe('active')
        ->and($started['current_day']['number'])->toBe(1)
        ->and($started['current_day']['slot']['code'])->toBe('today')
        ->and($started['current_day']['slot']['label_native'])->toBe('сегодня');

    // A second live plan is refused while this one runs.
    $second = planCreate($this, $token);
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$second['id']}/start")->assertStatus(409)->assertJsonPath('code', 'plan_already_active');

    $day = planOpenDay($this, $token, $id, 1);
    expect($day['status'])->toBe('in_progress')
        ->and(count($day['cards']))->toBeGreaterThan(60)
        ->and($day['cards'][0]['kind'])->toBe('word_intro')
        ->and($day['cards'][0]['payload']['image'])->not->toBeNull();

    // Opening day 1 wrote day 2's lesson (§4).
    expect(planRead($this, $token, $id)['days'][1]['lesson_status'])->toBe('ready');

    // The room: five stages, the first one current.
    $room = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/1")->assertOk()->json('data');
    expect(array_column($room['stages'], 'state'))->toBe(['current', 'locked', 'locked', 'locked', 'locked'])
        ->and($room['window']['day']['goals'])->toHaveCount(3)
        ->and($room['program'])->not->toBeEmpty()
        // The old day room's own keys went with it (DAY-UI-2): the goals live in the window now.
        ->and(array_keys($room))->toBe(['plan_id', 'day', 'scene', 'stages', 'metrics', 'program', 'window'])
        ->and(array_keys($room['program'][0]))->toBe(['unit_kind', 'source', 'state']);

    // Closing a stage with cards still open is refused.
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/stages/words/close")->assertStatus(409)->assertJsonPath('code', 'plan_stage_incomplete');

    // Two errors on a word (choose), one skip on a spoken card.
    $choose = array_values(array_filter($day['cards'], static fn (array $c): bool => $c['kind'] === 'word_choose'))[0];
    $say = array_values(array_filter($day['cards'], static fn (array $c): bool => $c['kind'] === 'word_say'))[0];
    $first = planAnswer($this, $token, $id, 1, $choose['id'], 'failed');
    expect($first['card']['result'])->toBe('failed')
        ->and($first['requeued'])->not->toBeNull()
        ->and($first['requeued']['retry_of'])->toBe($choose['id'])
        ->and($first['requeued']['stage'])->toBe('words');
    $again = planAnswer($this, $token, $id, 1, $first['requeued']['id'], 'failed', 2);
    expect($again['card']['returns'])->toBeTrue()->and($again['requeued'])->toBeNull();
    $skipped = planAnswer($this, $token, $id, 1, $say['id'], 'skipped', 2);
    expect($skipped['card']['result'])->toBe('skipped')->and($skipped['requeued'])->toBeNull();

    // An answer cannot be replayed.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$id}/days/1/cards/{$say['id']}/answer", ['result' => 'passed', 'attempts' => 1])
        ->assertStatus(409)->assertJsonPath('code', 'plan_card_answered');

    $closed = planWalkDay($this, $token, $id, 1);
    expect($closed['day']['status'])->toBe('closed')
        ->and($closed['day']['cards_done'])->toBe($closed['metrics']['cards_total'])
        ->and($closed['metrics'])->toBe(['cards_total' => $closed['metrics']['cards_total'], 'minutes_spent' => $closed['metrics']['minutes_spent']])
        ->and($closed['metrics']['minutes_spent'])->toBeGreaterThanOrEqual(1)
        // The word failed twice is the one that returns — on the tab's plate and in the window's brow.
        ->and(array_count_values(array_column($closed['program'], 'state'))['failed'])->toBe(1)
        ->and($closed['window']['program']['words']['summary']['returns'])->toBe(1);

    // The words went to the plan's collection, hidden from «Мои коллекции».
    $tab = planRead($this, $token, $id);
    expect($tab['collection_id'])->not->toBeNull()
        ->and(DB::table('collection_items')->where('collection_id', $tab['collection_id'])->count())->toBe(14)
        ->and(DB::table('collections')->where('id', $tab['collection_id'])->value('origin'))->toBe('plan');
    $mine = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/collections')->assertOk()->json('data');
    expect(array_column($mine, 'id'))->not->toContain($tab['collection_id']);

    // Day 2 waits for tomorrow.
    expect($tab['days'][1]['slot']['code'])->toBe('tomorrow')
        ->and($tab['days'][1]['slot']['label_native'])->toBe('завтра');
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/2/open")
        ->assertStatus(409)->assertJsonPath('code', 'plan_day_locked');

    planShiftDay($id);
    $two = planOpenDay($this, $token, $id, 2);
    $returned = array_values(array_filter($two['cards'], static fn (array $c): bool => $c['source'] === 'returned'));
    expect($returned)->toHaveCount(1)
        ->and($returned[0]['kind'])->toBe('word_choose')
        ->and($returned[0]['unit_ref'])->toBe($choose['unit_ref'])
        ->and($returned[0]['source_day_id'])->toBe($closed['day']['id'])
        // The returned card sits at the end of its stage, after today's words.
        ->and($returned[0]['position'])->toBe(count(array_filter($two['cards'], static fn (array $c): bool => $c['stage'] === 'words')));

    // The window lists the day's words and phrases — today's and the one returned from yesterday.
    $window = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/2")->assertOk()->json('data.window');
    expect($window['program']['words']['items'])->toHaveCount(9)
        ->and($window['program']['phrases']['items'])->toHaveCount(6);
});

it('walks a three-day plan through to the rehearsal, which is every scene said aloud', function () {
    [, $token] = planLearner('Europe/Kyiv');
    $build = planCreate($this, $token, ['days_total' => 3, 'level' => 'intermediate']);
    $id = $build['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();

    $one = planWalkDay($this, $token, $id, 1);
    expect($one['day']['status'])->toBe('closed');
    planShiftDay($id);
    $two = planWalkDay($this, $token, $id, 2);
    expect($two['day']['status'])->toBe('closed');
    planShiftDay($id);

    $rehearsal = planOpenDay($this, $token, $id, 3);
    expect(array_unique(array_column($rehearsal['cards'], 'stage')))->toBe(['speak'])
        ->and(count($rehearsal['cards']))->toBe(16)
        ->and(count(array_unique(array_column(array_column($rehearsal['cards'], 'payload'), 'scene_id'))))->toBe(2);

    $room = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/3")->assertOk()->json('data');
    expect(array_column($room['stages'], 'state'))->toBe(['absent', 'absent', 'absent', 'absent', 'current'])
        ->and($room['window']['program']['words']['items'])->toBe([])
        ->and($room['window']['program']['dialogue']['items'])->toHaveCount(16);

    $closed = planWalkDay($this, $token, $id, 3);
    expect($closed['day']['status'])->toBe('closed');

    $tab = planRead($this, $token, $id);
    expect($tab['current_day'])->toBeNull()->and($tab['status'])->toBe('active');
    $finished = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/finish")->assertOk()->json('data');
    expect($finished['status'])->toBe('finished');

    $list = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans')->assertOk()->json('data');
    expect($list)->toHaveCount(1)->and($list[0]['status'])->toBe('finished');
    expect($this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/current')->assertOk()->json('data'))->toBeNull();
});

it('reschedules: a later date keeps the plan, more days ask the model for more scenes, fewer days drop by the rule', function () {
    [, $token] = planLearner();
    $build = planCreate($this, $token, ['days_total' => 5]);
    $id = $build['id'];

    $longer = $this->withHeader('Authorization', "Bearer {$token}")->patchJson("/api/v1/plans/{$id}/schedule", ['days_total' => 8])->assertOk()->json('data');
    expect($longer['days_total'])->toBe(8)->and(count($longer['scenes']))->toBe(5)
        ->and(array_column($longer['days'], 'type'))->toBe(['scene', 'scene', 'review', 'scene', 'scene', 'review', 'scene', 'rehearsal']);

    $shorter = $this->withHeader('Authorization', "Bearer {$token}")->patchJson("/api/v1/plans/{$id}/schedule", ['days_total' => 3])->assertOk()->json('data');
    expect($shorter['days_total'])->toBe(3)->and(count($shorter['scenes']))->toBe(2)
        ->and(array_column($shorter['scenes'], 'priority'))->toContain(1);

    $dated = $this->withHeader('Authorization', "Bearer {$token}")->patchJson("/api/v1/plans/{$id}/schedule", ['event_date' => now()->addDays(20)->toDateString()])->assertOk()->json('data');
    expect($dated['days_left'])->toBe(20)->and($dated['until_phrase'])->toBe('До приёма · 20 дней');

    $cleared = $this->withHeader('Authorization', "Bearer {$token}")->patchJson("/api/v1/plans/{$id}/schedule", ['event_date' => null])->assertOk()->json('data');
    expect($cleared['event_date'])->toBeNull()->and($cleared['until_phrase'])->toBeNull();
});

it('shows an active plan whose date has passed as overdue, with the prompt’s own sentence', function () {
    [, $token] = planLearner();
    $build = planCreate($this, $token, ['days_total' => 2, 'event_date' => now()->addDays(2)->toDateString()]);
    $id = $build['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();

    planShiftDay($id, 3);

    $tab = $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/current')->assertOk()->json('data');
    expect($tab['status'])->toBe('overdue')->and($tab['overdue_native'])->toBe('Приём был вчера');
});

it('marks a lesson failed after two refused answers and lets the learner retry it', function () {
    [, $token] = planLearner();
    config(['plan.checks.lesson.counts' => 'gate']);
    $fake = new FakePlanModel(lesson: static function ($request, int $attempt): array {
        $p = FakePlanModel::lessonPayload($request);
        array_pop($p['phrases']);

        return $p;
    });
    app()->instance(PlanModelPort::class, $fake);
    app()->forgetInstance(LessonChecker::class);

    $build = planCreate($this, $token, ['days_total' => 1]);
    $plan = planRead($this, $token, $build['id']);
    expect($plan['scenes'][0]['lesson_status'])->toBe('failed')
        ->and($plan['scenes'][0]['lesson_fail_reason'])->toContain('counts')
        ->and($fake->lessonCalls)->toBe(2);

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$build['id']}/start")->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$build['id']}/days/1/open")
        ->assertStatus(409)->assertJsonPath('code', 'plan_lesson_not_ready');

    // The retry asks again; the same broken answer fails again — explicitly, and counted.
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$build['id']}/scenes/{$plan['scenes'][0]['id']}/lesson/retry")->assertStatus(202);
    expect($fake->lessonCalls)->toBe(4)
        ->and(DB::table('plan_check_counters')->where('check_name', 'counts')->where('action', 'gated')->value('hits'))->toBe(4);
});

it('counts a check in observe and shows the counters to the admin by prompt version', function () {
    [, $token] = planLearner();
    app()->instance(PlanModelPort::class, new FakePlanModel(lesson: static function ($request): array {
        $p = FakePlanModel::lessonPayload($request);
        $p['dialogue'][0]['messages'][1]['speaking_key'] = 'describe main pain';

        return $p;
    }));

    $build = planCreate($this, $token, ['days_total' => 1]);
    expect(planRead($this, $token, $build['id'])['scenes'][0]['lesson_status'])->toBe('ready');

    [, $admin] = adminActor();
    $rows = $this->withHeader('Authorization', "Bearer {$admin}")->getJson('/admin/api/plans/checks')->assertOk()->json('data');
    expect($rows)->toBe([['prompt_version' => 'lesson-v4', 'check' => 'speaking_key_substring', 'action' => 'counted', 'hits' => 1]]);

    $checks = DB::table('plan_scenes')->where('plan_id', $build['id'])->value('checks_json');
    expect(json_decode((string) $checks, true)[0]['check'])->toBe('speaking_key_substring');
});

it('deletes a plan and forgets it everywhere', function () {
    [, $token] = planLearner();
    $build = planCreate($this, $token, ['days_total' => 2]);

    $this->withHeader('Authorization', "Bearer {$token}")->deleteJson("/api/v1/plans/{$build['id']}")->assertNoContent();
    $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$build['id']}")->assertStatus(404);
    expect($this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans')->assertOk()->json('data'))->toBe([]);
});

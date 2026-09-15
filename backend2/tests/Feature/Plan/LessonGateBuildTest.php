<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * THE GATE OF THE LESSON BUILD (решение архитектора после GEN-2a, docs/plan-v2.md §4): «фатальные — день не
 * раздаётся до P2R по адресу, вызов автоматический, не больше двух карточек на день, дальше день failed с кодом;
 * остальные — предупреждения».
 */

/** @return array<string, array{action: string, hits: int}> «check|action» → hits, of the lesson prompt */
function lgCounters(): array
{
    $out = [];
    foreach (DB::table('plan_check_counters')->where('prompt_version', 'lesson_day.v4.4')->get() as $row) {
        $out["{$row->check_name}|{$row->action}"] = (int) $row->hits;
    }
    ksort($out);

    return $out;
}

// Catches a day dealt with a broken card — the gate off, the repair asked for the wrong card, or the repaired
// answer not the one stored — and a repair whose time is lost from the lesson.
it('holds a lesson with a fatal finding, repairs the card at its address and gives the repaired day', function () {
    $fake = new FakePlanModel(
        lesson: static function ($request): array {
            $p = FakePlanModel::lessonPayload($request);
            $p['phrases'][0]['slot']['fillers'][1]['target'] = 'his neck';

            return $p;
        },
        repair: static function ($request): array {
            $card = $request->card;
            $card['slot']['fillers'][1]['target'] = 'neck';

            return ['card' => $card];
        },
    );
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();

    $id = planCreate($this, $token, ['days_total' => 1])['id'];
    $scene = DB::table('plan_scenes')->where('plan_id', $id)->first();

    expect($fake->repairCalls)->toBe(1)
        ->and($fake->repairRequests[0]->address)->toBe('p1')
        ->and(array_column($fake->repairRequests[0]->findings, 'code'))->toBe(['filler.ungrammatical'])
        ->and(planRead($this, $token, $id)['scenes'][0]['lesson_status'])->toBe('ready')
        ->and(json_decode((string) $scene->lesson_json, true)['phrases'][0]['slot']['fillers'][1]['target'])->toBe('neck')
        ->and(json_decode((string) $scene->checks_json, true))->toBe([])
        ->and((int) $scene->latency_ms_lesson)->toBe(7 + 3)
        ->and((int) $scene->attempts_lesson)->toBe(1)
        ->and(lgCounters())->toBe(['filler.ungrammatical|counted' => 1, 'filler.ungrammatical|gated' => 1]);
});

// Catches a gate that keeps paying for repairs past two cards, and one that deals or silently drops a day it could
// not put right instead of failing it with the code.
it('asks for two cards at most and then fails the lesson with its fatal codes, which the learner can retry', function () {
    $fake = new FakePlanModel(lesson: static function ($request): array {
        $p = FakePlanModel::lessonPayload($request);
        $p['phrases'][0]['slot']['fillers'][1]['target'] = 'his neck';
        array_pop($p['dialogue'][1]['check']['options']);
        $p['listening']['questions'][2]['options_native'] = ['Завтра', 'Если через неделю ещё болит'];
        $p['listening']['questions'][2]['correct_option_index'] = 1;

        return $p;
    });
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();

    $id = planCreate($this, $token, ['days_total' => 1])['id'];
    $plan = planRead($this, $token, $id);
    $findings = json_decode((string) DB::table('plan_scenes')->where('plan_id', $id)->value('checks_json'), true);

    expect($fake->repairCalls)->toBe(2)
        ->and(array_map(static fn ($r): string => $r->address, $fake->repairRequests))->toBe(['p1', 'x2.check'])
        ->and($plan['scenes'][0]['lesson_status'])->toBe('failed')
        ->and($plan['scenes'][0]['lesson_fail_reason'])->toBe('fatal: check.shape, listening.shape, filler.ungrammatical')
        ->and(array_map(static fn (array $f): string => "{$f['code']}@{$f['address']}", $findings))
        ->toEqualCanonicalizing(['check.shape@x2.check', 'listening.shape@L3', 'filler.ungrammatical@p1.f2'])
        ->and(lgCounters())->toBe([
            'check.shape|counted' => 1, 'check.shape|failed' => 1, 'check.shape|gated' => 1,
            'filler.ungrammatical|counted' => 1, 'filler.ungrammatical|failed' => 1, 'filler.ungrammatical|gated' => 1,
            'listening.shape|counted' => 1, 'listening.shape|failed' => 1, 'listening.shape|gated' => 1,
        ]);

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/scenes/{$plan['scenes'][0]['id']}/lesson/retry")->assertStatus(202);
    expect($fake->lessonCalls)->toBe(2);
});

// Catches a day dealt with an exchange said by the wrong speakers — a fatal code that stands at no card, since the
// whole exchange is broken — and a repair asked for it.
it('fails a lesson whose fatal finding stands at no card without asking for a repair', function () {
    $fake = new FakePlanModel(lesson: static function ($request): array {
        $p = FakePlanModel::lessonPayload($request);
        $p['dialogue'][0]['initiator'] = 'B';

        return $p;
    });
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();

    $id = planCreate($this, $token, ['days_total' => 1])['id'];

    expect($fake->repairCalls)->toBe(0)
        ->and(planRead($this, $token, $id)['scenes'][0]['lesson_status'])->toBe('failed')
        ->and(planRead($this, $token, $id)['scenes'][0]['lesson_fail_reason'])->toBe('fatal: exchange.shape');
});

<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * THE GATE OF THE LESSON BUILD (решения архитектора после GEN-2a, в GEN-2b и в GEN-3, docs/plan-v2.md §4): «фатальные — день
 * не раздаётся до P2R по адресу, вызов автоматический, не больше двух карточек на день, дальше день failed с кодом;
 * остальные — предупреждения». The lessons are the fake's told apart ({@see planCleanLesson()}): no finding but a test's own.
 */

/** @return array<string, array{action: string, hits: int}> «check|action» → hits, of the lesson prompt */
function lgCounters(): array
{
    $out = [];
    foreach (DB::table('plan_check_counters')->where('prompt_version', 'lesson_day.v4.6')->get() as $row) {
        $out["{$row->check_name}|{$row->action}"] = (int) $row->hits;
    }
    ksort($out);

    return $out;
}

/** @return array<string, mixed> a frame of the clean lesson, as the model writes it */
function lgFrame(int $index): array
{
    return FakePlanModel::lessonPayload(new LessonRequest('x', 'x', 'English', 'Russian', PlanLevel::Beginner, null, 8, 8, FakePlanModel::roles(), new EarlierDays))['phrases'][$index];
}

// Catches a day dealt with a broken card — the gate off, the repair asked for the wrong card, or the repaired
// answer not the one stored — and a repair whose time is lost from the lesson.
it('holds a lesson with a fatal finding, repairs the card at its address and gives the repaired day', function () {
    $fake = new FakePlanModel(
        lesson: static function ($request): array {
            $p = planCleanLesson($request);
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
        ->and((int) $scene->latency_ms_lesson)->toBe(7 + 3 + 2) // the lesson, the repair, the seam judge
        ->and((int) $scene->attempts_lesson)->toBe(1)
        ->and(lgCounters())->toBe(['filler.ungrammatical|counted' => 1, 'filler.ungrammatical|gated' => 1]);
});

// Catches a gate that keeps paying for repairs past two cards, and one that deals or silently drops a day it could
// not put right instead of failing it with the code.
it('asks for two cards at most and then fails the lesson with its fatal codes, which the learner can retry', function () {
    $fake = new FakePlanModel(lesson: static function ($request): array {
        $p = planCleanLesson($request);
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

// Canon GEN-2b: «P2R получает новый вид карточки exchange и поле frame_update — сборка применяет его атомарно (обмен +
// каркас)»; «exchange.repeats — повтор каркаса с тем же наполнением» is fatal. Доработка: «поле filler реплики в ответе
// модели не читается никем: ни сборкой, ни валидатором, ни контекстом P2R». Catches a day dealt with an exchange that
// asks what the visit already asked, a repair that takes the line alone (the repeat stays), an exchange stored without
// the frame it came with (its new filler said and never marked), a pair that does not fit put in by halves — and a
// repair shown the model's own filler field instead of the filler its line says.
it('holds a repeated exchange, repairs the whole exchange and stores it together with the frame it came with', function () {
    $repeat = static function ($request): array {
        $p = planCleanLesson($request);
        // Exchange 8 says «an X-ray» again; its field still names «a follow-up appointment», exchange 4's names «a sick note».
        $p['dialogue'][7]['messages'][0]['text_target'] = 'Do we need an X-ray?';
        $p['dialogue'][3]['messages'][0]['filler'] = 'a sick note';
        $p['phrases'][5]['slot']['fillers'][1]['in_dialogue'] = false;

        return $p;
    };
    $fits = new FakePlanModel(lesson: $repeat, repair: static function ($request): array {
        $card = $request->card;
        $card['messages'][0]['filler'] = 'a sick note';
        $card['messages'][0]['text_target'] = 'Do we need a sick note?';
        $frame = lgFrame(5);
        $frame['slot']['fillers'][1]['in_dialogue'] = false;
        $frame['slot']['fillers'][2]['in_dialogue'] = true;

        return ['card' => $card, 'frame_update' => $frame];
    });
    app()->instance(PlanModelPort::class, $fits);
    [, $token] = planLearner();

    $id = planCreate($this, $token, ['days_total' => 1])['id'];
    $scene = DB::table('plan_scenes')->where('plan_id', $id)->first();
    $lesson = json_decode((string) $scene->lesson_json, true);

    expect($fits->repairCalls)->toBe(1)
        ->and($fits->repairRequests[0]->kind)->toBe('exchange')
        ->and($fits->repairRequests[0]->address)->toBe('x8')
        ->and(array_column($fits->repairRequests[0]->findings, 'code'))->toBe(['exchange.repeats'])
        // The repair is shown the part of the lesson it needs, never the whole answer — and as the server reads it: the
        // filler each line says, not what the model wrote in the field.
        ->and(array_keys($fits->repairRequests[0]->context))->toBe(['frames', 'words', 'exchanges'])
        ->and($fits->repairRequests[0]->card['messages'][0]['filler'])->toBe('an X-ray')
        ->and(array_column(array_column($fits->repairRequests[0]->context['exchanges'], null, 'step')[4]['messages'], 'filler', 'speaker'))->toBe(['B' => 'an X-ray'])
        ->and(planRead($this, $token, $id)['scenes'][0]['lesson_status'])->toBe('ready')
        ->and($lesson['dialogue'][7]['messages'][0]['filler'])->toBe('a sick note')
        ->and(array_column($lesson['phrases'][5]['slot']['fillers'], 'in_dialogue'))->toBe([true, false, true])
        ->and(json_decode((string) $scene->checks_json, true))->toBe([])
        ->and(lgCounters())->toBe(['exchange.repeats|counted' => 1, 'exchange.repeats|gated' => 1]);

    // The same exchange with a frame its line does not stand on: the card is spent, nothing of it is put in, and the
    // repeat fails the day.
    $alien = new FakePlanModel(lesson: $repeat, repair: static function ($request): array {
        $card = $request->card;
        $card['messages'][0]['filler'] = 'a sick note';
        $card['messages'][0]['text_target'] = 'Do we need a sick note?';

        return ['card' => $card, 'frame_update' => lgFrame(4)];
    });
    app()->instance(PlanModelPort::class, $alien);
    [, $second] = planLearner();

    $failed = planRead($this, $second, planCreate($this, $second, ['days_total' => 1])['id']);

    expect($alien->repairCalls)->toBe(1)
        ->and($failed['scenes'][0]['lesson_status'])->toBe('failed')
        ->and($failed['scenes'][0]['lesson_fail_reason'])->toBe('fatal: exchange.repeats');
});

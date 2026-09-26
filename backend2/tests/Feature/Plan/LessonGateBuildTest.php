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
    foreach (DB::table('plan_check_counters')->where('prompt_version', 'lesson_day.v4.10')->get() as $row) {
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

/** @return array<string, mixed> the fake's clean lesson broken on three cards no repair of the fake puts right */
function lgBrokenThrice(LessonRequest $request): array
{
    $p = planCleanLesson($request);
    $p['phrases'][0]['slot']['fillers'][1]['target'] = 'his neck';
    array_pop($p['dialogue'][1]['check']['options']);
    $p['listening']['questions'][2]['options_native'] = ['Завтра', 'Если через неделю ещё болит'];
    $p['listening']['questions'][2]['correct_option_index'] = 1;

    return $p;
}

// Catches a gate that keeps paying for repairs past two cards, and one that deals or silently drops a day it could
// not put right instead of failing it with the code. Наряд LANG-1b §1: «день, упавший на воротах после двух починок,
// сервер пересобирает сам ОДИН раз (новый ответ модели), и только второй провал — failed» — the fake answers the same
// broken lesson twice, so the day fails after two builds of two cards each, and the rebuild is counted with its failure.
it('asks for two cards at most a build, builds a failed lesson anew once, and only then fails it — the learner can retry', function () {
    $fake = new FakePlanModel(lesson: static fn ($request): array => lgBrokenThrice($request));
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();

    $id = planCreate($this, $token, ['days_total' => 1])['id'];
    $plan = planRead($this, $token, $id);
    $scene = DB::table('plan_scenes')->where('plan_id', $id)->first();
    $findings = json_decode((string) $scene->checks_json, true);

    expect($fake->lessonCalls)->toBe(2)
        ->and($fake->repairCalls)->toBe(4)
        ->and(array_map(static fn ($r): string => $r->address, $fake->repairRequests))->toBe(['p1', 'x2.check', 'p1', 'x2.check'])
        ->and($plan['scenes'][0]['lesson_status'])->toBe('failed')
        ->and($plan['scenes'][0]['lesson_fail_reason'])->toBe('fatal: check.shape, listening.shape, filler.ungrammatical')
        ->and(array_map(static fn (array $f): string => "{$f['code']}@{$f['address']}", $findings))
        ->toEqualCanonicalizing(['check.shape@x2.check', 'listening.shape@L3', 'filler.ungrammatical@p1.f2'])
        // Both builds on the scene: two lesson calls, four repairs, no seam judge (a failed lesson is not judged).
        ->and((int) $scene->attempts_lesson)->toBe(2)
        ->and((int) $scene->latency_ms_lesson)->toBe(2 * (7 + 3 + 3))
        ->and(lgCounters())->toBe([
            'check.shape|counted' => 2, 'check.shape|failed' => 2, 'check.shape|gated' => 2,
            'filler.ungrammatical|counted' => 2, 'filler.ungrammatical|failed' => 2, 'filler.ungrammatical|gated' => 2,
            'lesson.auto_rebuild|counted' => 1, 'lesson.auto_rebuild|failed' => 1,
            'listening.shape|counted' => 2, 'listening.shape|failed' => 2, 'listening.shape|gated' => 2,
        ]);

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/scenes/{$plan['scenes'][0]['id']}/lesson/retry")->assertStatus(202);
    expect($fake->lessonCalls)->toBe(4);
});

// Наряд LANG-1b §1: the rebuild is a NEW answer of the model, validated and gated from scratch with its own two cards; a
// clean one is dealt. Catches a rebuild that is not asked, a day failed on the first answer, a rebuilt day that loses the
// first build's time or lesson call, and a rebuild not counted.
it('deals the lesson the server built anew when the first failed the gate', function () {
    $fake = new FakePlanModel(lesson: static fn ($request, int $call): array => $call === 1 ? lgBrokenThrice($request) : planCleanLesson($request));
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();

    $id = planCreate($this, $token, ['days_total' => 1])['id'];
    $scene = DB::table('plan_scenes')->where('plan_id', $id)->first();

    expect($fake->lessonCalls)->toBe(2)
        ->and($fake->repairCalls)->toBe(2)
        ->and(planRead($this, $token, $id)['scenes'][0]['lesson_status'])->toBe('ready')
        ->and($scene->fail_reason)->toBeNull()
        ->and(json_decode((string) $scene->checks_json, true))->toBe([])
        ->and((int) $scene->attempts_lesson)->toBe(2)
        ->and((int) $scene->latency_ms_lesson)->toBe((7 + 3 + 3) + (7 + 2)) // the first build and its repairs, the second and its judge
        ->and(lgCounters())->toBe([
            'check.shape|counted' => 1, 'check.shape|failed' => 1, 'check.shape|gated' => 1,
            'filler.ungrammatical|counted' => 1, 'filler.ungrammatical|failed' => 1, 'filler.ungrammatical|gated' => 1,
            'lesson.auto_rebuild|counted' => 1,
            'listening.shape|counted' => 1, 'listening.shape|failed' => 1, 'listening.shape|gated' => 1,
        ]);
});

// Наряд LANG-1b §1: only a lesson the GATE failed is built anew. Catches a rebuild bought for an answer off the schema: the
// model refused twice, and its one retry is the canon's.
it('does not build anew a lesson the model answered off the schema', function () {
    $refused = new FakePlanModel(lesson: static fn (): array => ['not' => 'a lesson']);
    app()->instance(PlanModelPort::class, $refused);
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 1])['id'];

    expect($refused->lessonCalls)->toBe(2)
        ->and(planRead($this, $token, $id)['scenes'][0]['lesson_status'])->toBe('failed')
        ->and(array_filter(array_keys(lgCounters()), static fn (string $k): bool => str_starts_with($k, 'lesson.auto_rebuild')))->toBe([]);
});

// Наряд LANG-1b §1 and GEN-3: a call that got no answer is not bought again. Catches a rebuild that got no answer and so
// lost the day its reason (the gate's codes) — or took the first build's price and lesson call away from the scene.
it('keeps the gate\'s reason and the first build when the rebuild gets no answer', function () {
    $silent = new FakePlanModel(lesson: static function ($request, int $call): array {
        if ($call === 2) {
            throw App\Modules\Plan\Application\Exception\PlanModelUnavailable::because('no answer');
        }

        return lgBrokenThrice($request);
    });
    app()->instance(PlanModelPort::class, $silent);
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 1])['id'];
    $scene = DB::table('plan_scenes')->where('plan_id', $id)->first();

    expect($silent->lessonCalls)->toBe(2)
        ->and($scene->lesson_status)->toBe('failed')
        ->and($scene->fail_reason)->toBe('fatal: check.shape, listening.shape, filler.ungrammatical')
        ->and((int) $scene->attempts_lesson)->toBe(1)
        ->and((int) $scene->latency_ms_lesson)->toBe(7 + 3 + 3)
        ->and(lgCounters()['lesson.auto_rebuild|counted'] ?? 0)->toBe(1)
        ->and(lgCounters()['lesson.auto_rebuild|failed'] ?? 0)->toBe(0);
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
        // Наряд GEN-3 §5: «для вида exchange в вызов уходят NEIGHBOURS: обмен до и обмен после (или none)» — exchange 8 is the last.
        ->and($fits->repairRequests[0]->neighbours['before']['step'] ?? null)->toBe(7)
        ->and($fits->repairRequests[0]->neighbours)->toHaveKey('after')
        ->and($fits->repairRequests[0]->neighbours['after'] ?? null)->toBeNull()
        ->and($fits->repairRequests[0]->card['messages'][0]['filler'])->toBe('an X-ray')
        ->and(array_column(array_column($fits->repairRequests[0]->context['exchanges'], null, 'step')[4]['messages'], 'filler', 'speaker'))->toBe(['B' => 'an X-ray'])
        ->and(planRead($this, $token, $id)['scenes'][0]['lesson_status'])->toBe('ready')
        ->and($lesson['dialogue'][7]['messages'][0]['filler'])->toBe('a sick note')
        ->and(array_column($lesson['phrases'][5]['slot']['fillers'], 'in_dialogue'))->toBe([true, false, true])
        ->and(json_decode((string) $scene->checks_json, true))->toBe([])
        ->and(lgCounters())->toBe(['exchange.repeats|counted' => 1, 'exchange.repeats|gated' => 1]);

    // The same exchange with a frame its line does not stand on: the card is spent, nothing of it is put in, and the
    // repeat fails the day — after the one rebuild the server makes on its own (наряд LANG-1b §1), whose answer is the
    // same and whose repair is as wrong: a card spent in each build.
    $alien = new FakePlanModel(lesson: $repeat, repair: static function ($request): array {
        $card = $request->card;
        $card['messages'][0]['filler'] = 'a sick note';
        $card['messages'][0]['text_target'] = 'Do we need a sick note?';

        return ['card' => $card, 'frame_update' => lgFrame(4)];
    });
    app()->instance(PlanModelPort::class, $alien);
    [, $second] = planLearner();

    $failed = planRead($this, $second, planCreate($this, $second, ['days_total' => 1])['id']);

    expect($alien->repairCalls)->toBe(2)
        ->and($alien->lessonCalls)->toBe(2)
        ->and($failed['scenes'][0]['lesson_status'])->toBe('failed')
        ->and($failed['scenes'][0]['lesson_fail_reason'])->toBe('fatal: exchange.repeats');
});

// Наряд LANG-1b §10: «vocab.definition_language — ФАТАЛЬНЫЙ, починка P2R вид term: перевести определение на язык цели (одна
// карточка)». The owner's ru→ro day defined every Romanian word in English; here an English word is defined in Russian, which
// the letters tell as surely. Catches the definition dealt on the word's card, a repair asked for another card or another
// kind, a repair that replaced the word instead of its definition, and the repaired definition not the one stored.
it('holds a word defined in another language than the target, has its definition written anew on the word\'s card and deals the day', function () {
    $fake = new FakePlanModel(
        lesson: static function ($request): array {
            $p = planCleanLesson($request);
            $p['vocabulary'][1]['definition_target'] = 'внезапная и сильная, как порез';

            return $p;
        },
        repair: static function ($request): array {
            $card = $request->card;
            $card['definition_target'] = 'sudden and strong, like a cut';

            return ['card' => $card];
        },
    );
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();

    $id = planCreate($this, $token, ['days_total' => 1])['id'];
    $scene = DB::table('plan_scenes')->where('plan_id', $id)->first();
    $word = json_decode((string) $scene->lesson_json, true)['vocabulary'][1];

    expect($fake->repairCalls)->toBe(1)
        ->and($fake->repairRequests[0]->address)->toBe('v2')
        ->and($fake->repairRequests[0]->kind)->toBe('term')
        ->and(array_column($fake->repairRequests[0]->findings, 'code'))->toBe(['vocab.definition_language'])
        ->and(planRead($this, $token, $id)['scenes'][0]['lesson_status'])->toBe('ready')
        ->and([$word['id'], $word['term_target'], $word['translation_native']])->toBe(['v2', 'sharp', 'острая'])
        ->and($word['definition_target'])->toBe('sudden and strong, like a cut')
        ->and(json_decode((string) $scene->checks_json, true))->toBe([])
        ->and(lgCounters())->toBe(['vocab.definition_language|counted' => 1, 'vocab.definition_language|gated' => 1]);
});

// The server refuses a word a repair PUT IN whose `used_in` is off (P2R v1.2) — not a word the repair KEPT, its definition
// written anew (P2R v1.4): what the day said of that word before the repair is no reason to throw the fixed definition away
// and fail the day on it. Catches the refusal of GEN-3 applied to a word that did not change.
it('keeps a word whose definition the repair wrote anew, though the word carries a warning of its own', function () {
    $fake = new FakePlanModel(
        lesson: static function ($request): array {
            $p = planCleanLesson($request);
            $p['vocabulary'][1]['definition_target'] = 'внезапная и сильная, как порез';
            $p['vocabulary'][1]['used_in'] = ['p3', 'A5'];

            return $p;
        },
        repair: static function ($request): array {
            $card = $request->card;
            $card['definition_target'] = 'sudden and strong, like a cut';

            return ['card' => $card];
        },
    );
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();

    $id = planCreate($this, $token, ['days_total' => 1])['id'];
    $scene = DB::table('plan_scenes')->where('plan_id', $id)->first();

    expect($fake->repairCalls)->toBe(1)
        ->and($fake->lessonCalls)->toBe(1)
        ->and(planRead($this, $token, $id)['scenes'][0]['lesson_status'])->toBe('ready')
        ->and(json_decode((string) $scene->lesson_json, true)['vocabulary'][1]['definition_target'])->toBe('sudden and strong, like a cut')
        ->and(array_map(static fn (array $f): string => "{$f['code']}@{$f['address']}", json_decode((string) $scene->checks_json, true)))
        ->toBe(['vocab.used_in_wrong@v2']);
});

// A definition is a card of its own, and a day has two cards: three words defined in another language hold the day past its
// repairs, the server builds it anew once, and a second answer as foreign fails it with the code — the rule of §10 does not
// lean on the repair, the prompt's own rule (v4.10, VOCABULARY) is what keeps such days apart. Catches a third word card
// repaired past the limit, and a day dealt with a foreign definition left.
it('fails a day with three words defined in another language — two word cards a build, the rebuild, then failed', function () {
    $fake = new FakePlanModel(
        lesson: static function ($request): array {
            $p = planCleanLesson($request);
            foreach ([1, 2, 3] as $i) {
                $p['vocabulary'][$i]['definition_target'] = 'слово, объяснённое по-русски';
            }

            return $p;
        },
        repair: static function ($request): array {
            $card = $request->card;
            $card['definition_target'] = 'a word explained in English';

            return ['card' => $card];
        },
    );
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();

    $id = planCreate($this, $token, ['days_total' => 1])['id'];
    $plan = planRead($this, $token, $id);

    expect($fake->lessonCalls)->toBe(2)
        ->and(array_map(static fn ($r): string => $r->address, $fake->repairRequests))->toBe(['v2', 'v3', 'v2', 'v3'])
        ->and($plan['scenes'][0]['lesson_status'])->toBe('failed')
        ->and($plan['scenes'][0]['lesson_fail_reason'])->toBe('fatal: vocab.definition_language');
});

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * ЭТАПЫ ДНЯ — наряд DAY-GATE-1, решения 291–295.
 *
 * Живой прогон владельца 07.09 дал один симптом на три причины: экран говорил «идёт», «Продолжить»
 * вело в пустую сессию, а прогон голосом не предлагали ни разу. Общего у них было то, что у дня не
 * было ЭТАПОВ — только посадка (два потолка бюджета) и лестница карточек (A/B/C), и ни то ни другое
 * не отвечало на вопрос «что мне осталось до конца дня».
 *
 * Этот файл судит день так, как его проходит человек: этап за этапом, через тот же API.
 */
beforeEach(function (): void {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

/** Состояния этапов дня, как их читает экран: `material` => `done` … */
function stagesOfDay(object $ctx, string $token, string $planId, int $dayIndex): array
{
    $plan = $ctx->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$planId}")->assertOk()->json('data');

    $day = collect($plan['days'])->firstWhere('index', $dayIndex);

    return collect($day['stages'])->pluck('state', 'stage')->all();
}

it('ведёт день тремя этапами по порядку и закрывает его только после «Скажи сам»', function () {
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    // НЕ НАЧАТ: текущий — «Слова и фразы», остальные заперты, и каждый знает, после чего откроется.
    expect(stagesOfDay($this, $token, $planId, 1))
        ->toBe(['material' => 'current', 'conversation' => 'locked', 'rehearsal' => 'locked']);

    $plan = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$planId}")->assertOk()->json('data');
    $stages = collect(collect($plan['days'])->firstWhere('index', 1)['stages'])->keyBy('stage');
    expect($stages['material']['opens_after'])->toBeNull()
        ->and($stages['conversation']['opens_after'])->toBe('material')
        ->and($stages['rehearsal']['opens_after'])->toBe('conversation');

    // МАТЕРИАЛ — и день переходит к разговору, а не закрывается.
    $material = planSession($this, $token, $planId, 1);
    expect($material['stage'])->toBe('material');
    $seq = answerTasks($this, $token, $material);

    expect(stagesOfDay($this, $token, $planId, 1))
        ->toBe(['material' => 'done', 'conversation' => 'current', 'rehearsal' => 'locked']);

    // РАЗГОВОР — и только теперь открывается «Скажи сам».
    $conversation = planSession($this, $token, $planId, 1);
    expect($conversation['stage'])->toBe('conversation');
    $seq = answerTasks($this, $token, $conversation, $seq);

    expect(stagesOfDay($this, $token, $planId, 1))
        ->toBe(['material' => 'done', 'conversation' => 'done', 'rehearsal' => 'current']);

    // «СКАЖИ САМ» — прогон голосом, и день закрывается ИМ, а не материалом.
    $rehearsal = planSession($this, $token, $planId, 1);
    expect($rehearsal['stage'])->toBe('rehearsal');
    answerTasks($this, $token, $rehearsal, $seq);
    recordSceneRun($this, $token, $planId, 1, $rehearsal);

    expect(stagesOfDay($this, $token, $planId, 1))
        ->toBe(['material' => 'done', 'conversation' => 'done', 'rehearsal' => 'done']);
});

it('доводит день до «Скажи сам» и до «пройден», даже когда реплики отвечены неверно', function () {
    // ЗАМОК (а) НАРЯДА. Ровно случай владельца: три реплики промахнулись в разговоре. По старому
    // правилу день вставал намертво — лестница им сегодня больше ничего не должна, посадка пуста, а
    // «пройден» требовал закрытой ступени. Теперь этап пройден НАСКВОЗЬ: все карточки показаны.
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $seq = answerTasks($this, $token, planSession($this, $token, $planId, 1));

    $conversation = planSession($this, $token, $planId, 1);
    $turns = array_values(array_filter(
        $conversation['tasks'],
        static fn (array $t): bool => in_array($t['card']['exercise_mode'], ['situational_say', 'situational_ask'], true),
    ));
    expect(count($turns))->toBeGreaterThanOrEqual(3);

    // Три хода — мимо, остальное верно.
    $missed = array_slice($turns, 0, 3);
    $missedIds = array_column(array_column($missed, 'card'), 'term_id');
    $rest = array_values(array_filter(
        $conversation['tasks'],
        static fn (array $t): bool => ! in_array($t['card']['term_id'], $missedIds, true),
    ));
    $seq = answerTasks($this, $token, ['session_id' => $conversation['session_id'], 'tasks' => $rest], $seq);
    $seq = answerTasksWrong($this, $token, ['session_id' => $conversation['session_id'], 'tasks' => $missed], $seq);

    // РАЗГОВОР ПРОЙДЕН НАСКВОЗЬ — и «Скажи сам» открыт. Рядом появилась строка «Повторить ошибки»:
    // промахи не потеряны, но и день они не держат.
    expect(stagesOfDay($this, $token, $planId, 1))
        ->toBe([
            'material' => 'done',
            'conversation' => 'done',
            'rehearsal' => 'current',
            'retrain' => 'current',
        ]);

    $rehearsal = planSession($this, $token, $planId, 1);
    answerTasks($this, $token, $rehearsal, $seq);
    recordSceneRun($this, $token, $planId, 1, $rehearsal);

    $plan = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$planId}")->assertOk()->json('data');
    expect(collect($plan['days'])->firstWhere('index', 1)['day_state'])->toBe('done');
});

it('собирает прогон при нулевой зрелости сцены — ни один ход не был выбран верно', function () {
    // ЗАМОК (г). Зрелость сцены прогон больше не гейтит (решение владельца 07.09, п. 3): просить
    // сказать по памяти реплику, которую человек ни разу не выбрал, страшно — но «Пропустить» и
    // спасатель на месте, а НЕ предложить прогон тому, кто промахнулся, значит не предложить его
    // никому, кому он нужен.
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $seq = answerTasks($this, $token, planSession($this, $token, $planId, 1));
    $conversation = planSession($this, $token, $planId, 1);
    $seq = answerTasksWrong($this, $token, $conversation, $seq);

    $rehearsal = planSession($this, $token, $planId, 1);
    expect($rehearsal['stage'])->toBe('rehearsal')
        ->and($rehearsal['tasks'])->not->toBeEmpty();

    foreach ($rehearsal['tasks'] as $task) {
        expect($task['card']['exercise_mode'])->toBe('speaking')
            ->and($task['turn_level'])->toBe('say');
    }
});

it('возвращает промахнувшиеся реплики в разогрев следующего дня', function () {
    // ЗАМОК (б). Несказанное не теряется: день оно не держит, но завтра приходит.
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $seq = answerTasks($this, $token, planSession($this, $token, $planId, 1));
    $conversation = planSession($this, $token, $planId, 1);
    $missedIds = array_column(array_column($conversation['tasks'], 'card'), 'term_id');
    $seq = answerTasksWrong($this, $token, $conversation, $seq);

    $rehearsal = planSession($this, $token, $planId, 1);
    answerTasks($this, $token, $rehearsal, $seq);
    recordSceneRun($this, $token, $planId, 1, $rehearsal);

    ageHistory($user->id, days: 1);

    // Завтрашний день несёт их — своим ходом в разговоре или разогревом, но несёт.
    [$sittings] = walkDaySittings($this, $token, $planId, 2);
    $dealt = array_column(array_column(tasksOfSittings($sittings), 'card'), 'term_id');

    expect(array_intersect($missedIds, $dealt))->not->toBeEmpty();
});

it('«Повторить ошибки» показывает промах ещё раз — и только один раз за день', function () {
    // ЗАМОК (в). Единственное исключение из показа раз в день, и оно по явному нажатию.
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $seq = answerTasks($this, $token, planSession($this, $token, $planId, 1));
    $conversation = planSession($this, $token, $planId, 1);
    $missed = array_values(array_filter(
        $conversation['tasks'],
        static fn (array $t): bool => in_array($t['card']['exercise_mode'], ['situational_say', 'situational_ask'], true),
    ));
    expect($missed)->not->toBeEmpty();

    $seq = answerTasksWrong($this, $token, $conversation, $seq);

    // Строка «Повторить ошибки» есть на экране дня, и она не запирает день.
    $states = stagesOfDay($this, $token, $planId, 1);
    expect($states['retrain'] ?? null)->toBe('current')
        ->and($states['rehearsal'])->toBe('current');

    $retrain = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$planId}/days/1/session", ['stage' => 'retrain'])
        ->assertOk()->json('data');

    expect($retrain['stage'])->toBe('retrain')
        ->and($retrain['tasks'])->not->toBeEmpty();

    // ВТОРОЙ РАЗ СЕГОДНЯ — НЕТ: дверь открыта один раз, и строка со дня ушла.
    expect(stagesOfDay($this, $token, $planId, 1))->not->toHaveKey('retrain');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$planId}/days/1/session", ['stage' => 'retrain'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'plan_sitting_empty');
});

it('даёт реплике, собранной сегодня на знакомстве, её ход в разговоре сегодня же', function () {
    // РЕШЕНИЕ 292: показ раз в день считается ПО СТУПЕНИ, а не по карточке. Пока считали по
    // карточке, реплика, собранная на знакомстве, приходила к разговору «уже показанной», и этап
    // «Разговор» закрывался, не показав ни одной собственной реплики человека.
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $material = planSession($this, $token, $planId, 1);
    $metToday = [];
    foreach ($material['tasks'] as $task) {
        if ($task['stage'] === 'a' && in_array($task['shelf'] ?? null, ['say', 'ask'], true)
            && $task['card']['exercise_mode'] !== 'intro') {
            $metToday[] = $task['card']['term_id'];
        }
    }
    expect($metToday)->not->toBeEmpty();

    $seq = answerTasks($this, $token, $material);

    $conversation = planSession($this, $token, $planId, 1);
    $spokenToday = [];
    foreach ($conversation['tasks'] as $task) {
        if ($task['stage'] === 'b' && in_array($task['shelf'] ?? null, ['say', 'ask'], true)) {
            $spokenToday[] = $task['card']['term_id'];
        }
    }

    // КАЖДАЯ реплика, собранная сегодня, получает сегодня же свой ход в разговоре.
    expect(array_diff($metToday, $spokenToday))->toBe([]);

    // …а ВТОРОГО хода на той же ступени сегодня нет — правило само по себе цело.
    answerTasks($this, $token, $conversation, $seq);
    $next = planSession($this, $token, $planId, 1);
    foreach ($next['tasks'] as $task) {
        expect($task['stage'])->not->toBe('b');
    }
});

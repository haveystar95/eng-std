<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanSessionSections as S;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * ПРОГОН СЦЕНЫ — наряд SCENE-RUN, Ч.2.
 *
 * Ступень C: реплики на экране нет, есть подсказка на языке поддержки и микрофон. Здесь прибито
 * то, что решает СЕРВЕР — когда секция собирается, из чего она состоит и что остаётся после неё в
 * базе; голос и сторож живут на экране и проверяются там.
 */
beforeEach(function (): void {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

/** Ходы прогона этой посадки, в порядке цепочки. */
function sceneRunTasks(array $session): array
{
    return array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['section_code'] === S::SCENE_RUN,
    ));
}

it('не собирает прогон, пока сцена не прошла ступень B ни одним выбором', function () {
    // День знакомства: человек только встретил реплики. «C никогда не первый: страшно» — просить
    // произнести по памяти фразу, которую ни разу не выбрал в разговоре, это неотвечаемая карточка.
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $session = planSession($this, $token, $planId);

    expect(sceneRunTasks($session))->toBeEmpty()
        // И никакой заглушки «недоступно»: секции просто нет.
        ->and(array_column($session['tasks'], 'section_code'))->not->toContain(S::SCENE_RUN);
});

it('собирает прогон последним этапом дня — своими ходами и голосом', function () {
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    // «СКАЖИ САМ» — ТРЕТИЙ ЭТАП ДНЯ (наряд DAY-GATE-1, Ч.1.1), и открывается он ровно тогда, когда
    // «Разговор» пройден насквозь. Зрелость сцены его больше не гейтит: несказанная реплика в
    // прогоне — законный ход, там есть «Пропустить» и спасатель (решение владельца 07.09).
    [$session] = stageSession($this, $token, $planId, 1, 'rehearsal');
    $run = sceneRunTasks($session);

    expect($run)->not->toBeEmpty()
        // ЭТАП СОСТОИТ ТОЛЬКО ИЗ ПРОГОНА: ни слов, ни диалога в нём нет.
        ->and(array_values(array_unique(array_column($session['tasks'], 'section_code'))))->toBe([S::SCENE_RUN]);

    foreach ($run as $task) {
        // Тренажёр — говорение; уровень — «сам»; реплики на экране нет.
        expect($task['card']['exercise_mode'])->toBe('speaking')
            ->and($task['turn_level'])->toBe('say')
            ->and($task['card']['options'])->toBeNull()
            ->and($task['card']['chips'])->toBeNull()
            // Только свои ходы: пузырь роли звучит, но ходом не является.
            ->and($task['shelf'])->toBeIn(['say', 'ask']);
    }
});

it('записывает прогон одной строкой и считает числа сам', function () {
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    [$session] = stageSession($this, $token, $planId, 1, 'rehearsal');
    $run = sceneRunTasks($session);
    expect(count($run))->toBeGreaterThanOrEqual(3);

    // Один «сразу», один обычный, один пропуск, остальные спасателем — ровно та раскладка, которую
    // наряд просит увидеть живьём.
    $outcomes = ['said_fast', 'said', 'skipped'];
    $turns = [];
    foreach ($run as $i => $task) {
        $turns[] = [
            'term_id' => $task['card']['term_id'],
            'outcome' => $outcomes[$i] ?? 'rescued',
        ];
    }

    $body = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$planId}/scene-runs", [
            'scene_index' => 1,
            'day_index' => 1,
            'turns' => $turns,
        ])->assertCreated()->json('data');

    expect($body['total'])->toBe(count($turns))
        ->and($body['said'])->toBe(2)
        ->and($body['said_fast'])->toBe(1)
        ->and($body['skipped'])->toBe(1)
        ->and($body['rescued'])->toBe(count($turns) - 3);

    expect(DB::table('learning_plan_scene_runs')->where('plan_id', $planId)->count())->toBe(1);

    // …и на паре осталась ступень C: «сказал сам» у двух, «сразу» у одного.
    $stages = DB::table('learning_plan_term_stages')->where('plan_id', $planId)->get();
    expect($stages->where('said_in_run', true)->count())->toBe(2)
        ->and($stages->where('said_fast', true)->count())->toBe(1);
});

it('не предлагает прогон второй раз в тот же день — этап пройден и день закрыт', function () {
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    [$session, $seq] = stageSession($this, $token, $planId, 1, 'rehearsal');
    $run = sceneRunTasks($session);
    expect($run)->not->toBeEmpty();

    answerTasks($this, $token, $session, $seq);
    recordSceneRun($this, $token, $planId, 1, $session);

    // ПРОГОН ЗАПИСАН — этап пройден, день пройден, и второй раз сегодня его никто не предлагает.
    $plan = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$planId}")->assertOk()->json('data');
    $day1 = collect($plan['days'])->firstWhere('index', 1);

    expect($day1['day_state'])->toBe('done')
        ->and(collect($day1['stages'])->firstWhere('stage', 'rehearsal')['state'])->toBe('done');
});

it('отказывает в прогоне чужого плана так же, как в чужом плане', function () {
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);
    // Гвардия Sanctum кэширует пользователя на весь тест-метод: без сброса второй запрос
    // авторизуется первым человеком, и «чужой план» перестаёт быть чужим.
    app('auth')->forgetGuards();
    [, $other] = learner();

    $this->withHeader('Authorization', "Bearer {$other}")
        ->postJson("/api/v1/plans/{$planId}/scene-runs", [
            'scene_index' => 1,
            'day_index' => 1,
            'turns' => [['term_id' => (string) Ulid::generate(), 'outcome' => 'said']],
        ])->assertNotFound();
});

<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanSessionSections as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * B+ · СБОРКА — наряд SCENE-RUN, Ч.1, на собранном ответе API.
 *
 * Правило после вердикта владельца: сборка привязана не к счётчику удач, а к ПОВТОРНОМУ ПОЯВЛЕНИЮ
 * реплики. Выбор закрывается ОДНИМ верным ответом; реплика, вернувшись — в хвост присеста, в шов
 * следующего дня, — показывается сборкой. Ошибка на сборке ступень не открывает: реплика вернётся
 * сборкой ещё раз.
 *
 * Замки стоят на пейлоаде, а не рядом с правилом: уровень рождается из чек-листа, чек-лист из
 * журнала, а карточка из сборщика, и увидеть их согласие можно только там, где стоял бы человек.
 */
beforeEach(function (): void {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

/**
 * Ходы человека в диалоге этой посадки, в порядке раздачи.
 *
 * @return list<array<string, mixed>>
 */
function dialogueTurns(array $session): array
{
    return array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['section_code'] === S::DIALOGUE
            && in_array($t['card']['exercise_mode'], ['situational_say', 'situational_ask'], true),
    ));
}

/** Ходы одной реплики, в порядке раздачи. */
function turnsOfTerm(array $session, string $termId): array
{
    return array_values(array_filter(
        dialogueTurns($session),
        static fn (array $t): bool => $t['card']['term_id'] === $termId,
    ));
}

/**
 * Ответить на один ход посадки.
 *
 * `$seq` продолжает нумерацию устройства и НЕ начинается с единицы: журнал читается по `client_seq`
 * (устройства расходятся часами, и партия, пришедшая не в том порядке, обязана сложиться одинаково).
 * Ответ с меньшим номером встаёт в журнал ПЕРЕД ступенью A — и ступень B его не увидит вовсе.
 */
function answerTurn(object $ctx, string $token, array $session, array $task, string $response, int $seq): void
{
    $ctx->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/reviews/batch', ['reviews' => [[
        'id' => (string) \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
        'term_id' => $task['card']['term_id'],
        'exercise_mode' => $task['card']['exercise_mode'],
        'response' => $response,
        'answered_at' => now()->toIso8601String(),
        'client_seq' => $seq,
        'session_id' => $session['session_id'],
        'ladder_step' => $task['card']['ladder_step'],
    ]]])->assertOk();
}

it('пока выбор не закрыт — ход раздаётся выбором', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    $session = planSession($this, $token, $planId);
    $turns = dialogueTurns($session);
    expect($turns)->not->toBeEmpty();

    // Ступень B только открылась: первое касание каждой реплики — выбор.
    $termId = (string) $turns[0]['card']['term_id'];
    $ofTerm = turnsOfTerm($session, $termId);

    expect($ofTerm[0]['turn_level'])->toBe('choose')
        ->and($ofTerm[0]['card']['options'])->toBeArray()
        ->and($ofTerm[0]['card']['chips'])->toBeNull();
});

it('закрытый выбор возвращает реплику СБОРКОЙ на следующем показе', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $seq = walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    $session = planSession($this, $token, $planId);
    $turns = dialogueTurns($session);
    expect($turns)->not->toBeEmpty();

    $termId = (string) $turns[0]['card']['term_id'];
    $first = turnsOfTerm($session, $termId)[0];
    // ОДИН верный выбор — и он закрыт.
    answerTurn($this, $token, $session, $first, (string) $first['card']['answer'], $seq++);

    $again = planSession($this, $token, $planId);
    $back = turnsOfTerm($again, $termId);

    expect($back)->not->toBeEmpty()
        ->and($back[0]['turn_level'])->toBe('assemble')
        // Вариантов нет вовсе, блоки есть, и среди них — все слова самой реплики.
        ->and($back[0]['card']['options'])->toBeNull()
        ->and($back[0]['card']['chips'])->toBeArray();

    foreach (preg_split('/\s+/u', trim((string) $back[0]['card']['answer']), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $block) {
        expect($back[0]['card']['chips'])->toContain($block);
    }
});

it('ошибка на сборке не откатывает ход в выбор — он вернётся сборкой ещё раз', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $seq = walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    $session = planSession($this, $token, $planId);
    $turns = dialogueTurns($session);
    expect($turns)->not->toBeEmpty();

    $termId = (string) $turns[0]['card']['term_id'];
    $first = turnsOfTerm($session, $termId)[0];
    answerTurn($this, $token, $session, $first, (string) $first['card']['answer'], $seq++);

    // Сборка ПРОВАЛЕНА.
    $second = planSession($this, $token, $planId);
    $assemble = turnsOfTerm($second, $termId)[0];
    expect($assemble['turn_level'])->toBe('assemble');
    answerTurn($this, $token, $second, $assemble, 'not the line at all', $seq++);

    // Ступень не открылась и в выбор не откатилась.
    $third = planSession($this, $token, $planId);
    $back = turnsOfTerm($third, $termId);

    expect($back)->not->toBeEmpty()
        ->and($back[0]['turn_level'])->toBe('assemble');
});

it('не хранит про строгость хода ни строки — она выводится из чек-листа', function () {
    // Решение 254 отозвано: счётчик выборов удалён целиком. Лестница плана снова целиком проекция
    // append-only журнала, и таблица пары хранит только то, что доказано голосом.
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $seq = walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    $session = planSession($this, $token, $planId);
    $turns = dialogueTurns($session);
    $termId = (string) $turns[0]['card']['term_id'];
    answerTurn($this, $token, $session, turnsOfTerm($session, $termId)[0], (string) $turns[0]['card']['answer'], $seq);

    expect(DB::table('learning_plan_term_stages')->where('plan_id', $planId)->count())->toBe(0);
    expect(collect(DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'learning_plan_term_stages'"))
        ->pluck('column_name')->all())
        ->not->toContain('choice_streak');
});

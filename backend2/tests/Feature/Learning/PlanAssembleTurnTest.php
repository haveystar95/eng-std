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

/** Ходы ВЧЕРАШНЕЙ сцены — те, что пришли швом, а не сегодняшняя сцена (она играется выбором). */
function seamTurns(array $session): array
{
    return array_values(array_filter(
        dialogueTurns($session),
        static fn (array $t): bool => $t['section'] === 'review',
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
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    // Ступень B открывается В ТОТ ЖЕ день, что и знакомство (канон DAY-FIX-2, приведено к нему
    // нарядом DAY-GATE-1): разговор сцены — присест «Разговор» первого дня, и первое касание каждой
    // реплики там — выбор.
    [$session] = stageSession($this, $token, $planId, 1, 'conversation');
    $turns = dialogueTurns($session);
    expect($turns)->not->toBeEmpty();
    $termId = (string) $turns[0]['card']['term_id'];
    $ofTerm = turnsOfTerm($session, $termId);

    expect($ofTerm[0]['turn_level'])->toBe('choose')
        ->and($ofTerm[0]['card']['options'])->toBeArray()
        ->and($ofTerm[0]['card']['chips'])->toBeNull();
})->todo(
    'Ждёт ложных реплик от P2 (решение 296): у дня 1 чужих сцен нет — план пишется по одному дню, — '
    . 'и пул вариантов хода меньше пола, поэтому выбор честно откатывается в сборку. Оживёт, когда '
    . 'день будет приносить свои decoys.',
);

it('при голодном пуле ход раздаётся СБОРКОЙ и говорит, что именно сказать', function () {
    // ДЕНЬ ОБЯЗАН БЫТЬ САМОДОСТАТОЧНЫМ (решение владельца 07.09). План на один день существует по
    // канону, и чужих сцен у него нет: пул законных «неправильных» реплик собирается из своей сцены
    // и после отсева «не отвечает на тот же вопрос» может не дотянуть до пола вариантов. Тогда
    // карточка не отбивается и не превращается в монетку из двух — она приходит СБОРКОЙ
    // (DAY-FIX-2, Ч.1.7), и на ней стоит строка-намерение: без неё человек смотрит на россыпь блоков
    // и не понимает, чего от него хотят (живой прогон 07.09, скрины 5–7).
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    [$session] = stageSession($this, $token, $planId, 1, 'conversation');
    $assembled = array_values(array_filter(
        dialogueTurns($session),
        static fn (array $t): bool => $t['turn_level'] === 'assemble',
    ));

    expect($assembled)->not->toBeEmpty();
    foreach ($assembled as $task) {
        expect($task['card']['options'])->toBeNull()
            ->and($task['card']['chips'])->toBeArray()
            // ЧТО СКАЗАТЬ — на языке поддержки, и это перевод именно этой реплики.
            ->and($task['intent'])->toBeString()
            ->and($task['intent'])->not->toBe('');
    }

    // …и на карточке ВЫБОРА этой строки нет никогда: там перевод назвал бы правильный вариант.
    foreach (dialogueTurns($session) as $task) {
        if ($task['turn_level'] === 'choose') {
            expect($task['intent'])->toBeNull();
        }
    }
});

it('закрытый выбор возвращает реплику СБОРКОЙ на следующем показе', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    // День 1 пройден целиком — каждая реплика сцены выбрана верно ОДИН раз. Второго показа в тот же
    // день нет (DAY-FIX-2, Ч.2.4: одно касание ступени в день); назавтра реплика возвращается сборкой.
    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    [$session] = stageSession($this, $token, $planId, 2, 'conversation');
    $turns = seamTurns($session);
    expect($turns)->not->toBeEmpty();

    $termId = (string) $turns[0]['card']['term_id'];
    $back = turnsOfTerm($session, $termId);

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

    [$session, $seq] = stageSession($this, $token, $planId, 2, 'conversation', $seq);
    $turns = seamTurns($session);
    expect($turns)->not->toBeEmpty();

    // Выбор закрыт ещё вчера (в день знакомства); сегодня реплика приходит сборкой — и ПРОВАЛЕНА.
    $termId = (string) $turns[0]['card']['term_id'];
    $assemble = turnsOfTerm($session, $termId)[0];
    expect($assemble['turn_level'])->toBe('assemble');
    answerTurn($this, $token, $session, $assemble, 'not the line at all', $seq++);
    ageHistory($user->id, days: 1);

    // Ступень не открылась и в выбор не откатилась: назавтра реплике снова должна сборка. Читается
    // с экрана дня (`next_step`), а не из посадки: посадка режется бюджетом в 40 карточек, и шов
    // дня 1 в неё может и не влезть — а что реплике ДОЛЖНО, лестница знает без посадки.
    $terms = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$planId}/days/1")->assertOk()->json('data.terms');
    $row = collect($terms)->firstWhere('id', $termId);

    expect($row)->not->toBeNull()
        ->and($row['next_step'])->toBe('assemble');
});

it('не хранит про строгость хода ни строки — она выводится из чек-листа', function () {
    // Решение 254 отозвано: счётчик выборов удалён целиком. Лестница плана снова целиком проекция
    // append-only журнала, и таблица пары хранит только то, что доказано голосом.
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $seq = walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    [$session] = stageSession($this, $token, $planId, 2, 'conversation', $seq);
    $turns = dialogueTurns($session);
    $termId = (string) $turns[0]['card']['term_id'];

    // СЧИТАЕТСЯ ДЕЛЬТА, а не ноль: с наряда DAY-GATE-1 день закрывается прогоном, и прогон пишет в
    // эту таблицу то, что доказано ГОЛОСОМ. Вопрос теста другой — добавляет ли сюда что-нибудь ХОД
    // в разговоре, — и ответ обязан быть «ни строки».
    $before = DB::table('learning_plan_term_stages')->where('plan_id', $planId)->count();
    answerTurn($this, $token, $session, turnsOfTerm($session, $termId)[0], (string) $turns[0]['card']['answer'], $seq);

    expect(DB::table('learning_plan_term_stages')->where('plan_id', $planId)->count())->toBe($before);
    expect(collect(DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'learning_plan_term_stages'"))
        ->pluck('column_name')->all())
        ->not->toContain('choice_streak');
});

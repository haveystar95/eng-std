<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanSessionSections as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * B+ · СБОРКА — наряд SCENE-RUN, Ч.1, на собранном ответе API.
 *
 * Уровень строгости не виден по коду одной функции: он рождается из хранимого счётчика, проекции
 * этого счётчика на посадку и того, что из него собрал сборщик карточек. Поэтому замки стоят там же,
 * где стоял бы человек, — на пейлоаде посадки.
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

it('раздаёт ход дважды выбором, а третий раз — сборкой', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    $session = planSession($this, $token, $planId);
    $turns = dialogueTurns($session);
    expect($turns)->not->toBeEmpty();

    $termId = (string) $turns[0]['card']['term_id'];
    $ofTerm = turnsOfTerm($session, $termId);

    // Три касания ступени B в одной посадке — «ступень проходят за одну посадку». Первые два —
    // выбор, третье — сборка: иначе B+ не наступил бы никогда.
    expect($ofTerm)->toHaveCount(3)
        ->and(array_column($ofTerm, 'turn_level'))->toBe(['choose', 'choose', 'assemble']);
});

it('кладёт на сборку блоки вместо вариантов — и клавиатуры не просит', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    $session = planSession($this, $token, $planId);
    $assembles = array_values(array_filter(
        dialogueTurns($session),
        static fn (array $t): bool => $t['turn_level'] === 'assemble',
    ));
    expect($assembles)->not->toBeEmpty();

    foreach ($assembles as $task) {
        $card = $task['card'];
        // Вариантов нет вовсе, блоки есть, и среди блоков — все слова самой реплики.
        expect($card['options'])->toBeNull()
            ->and($card['chips'])->toBeArray()
            ->and($card['chips'])->not->toBeEmpty();

        foreach (preg_split('/\s+/u', trim((string) $card['answer']), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $block) {
            expect($card['chips'])->toContain($block);
        }
    }
});

it('не откатывает ход в выбор после неверной сборки, и откатывает после неверного выбора', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    $session = planSession($this, $token, $planId);
    $turns = dialogueTurns($session);
    expect($turns)->not->toBeEmpty();

    $termId = (string) $turns[0]['card']['term_id'];
    $ofTerm = turnsOfTerm($session, $termId);
    $sessionId = (string) $session['session_id'];

    // Два верных выбора и ПРОВАЛЕННАЯ сборка.
    $reviews = [];
    $seq = 1;
    foreach ($ofTerm as $task) {
        $reviews[] = [
            'id' => (string) \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
            'term_id' => $termId,
            'exercise_mode' => $task['card']['exercise_mode'],
            'response' => $task['turn_level'] === 'assemble' ? 'not the line at all' : $task['card']['answer'],
            'answered_at' => now()->toIso8601String(),
            'client_seq' => $seq++,
            'session_id' => $sessionId,
            'ladder_step' => $task['card']['ladder_step'],
        ];
    }
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => $reviews])->assertOk();

    // Счётчик дозрел и ошибка сборки его не сбросила: ход возвращается СБОРКОЙ.
    expect((int) DB::table('learning_plan_term_stages')
        ->where('plan_id', $planId)->where('term_id', $termId)->value('choice_streak'))->toBe(2);

    $again = planSession($this, $token, $planId);
    $back = turnsOfTerm($again, $termId);
    expect($back)->not->toBeEmpty()
        ->and($back[0]['turn_level'])->toBe('assemble');
});

it('оставляет ход на выборе, если один ответ верный, а другой нет', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    $session = planSession($this, $token, $planId);
    $turns = dialogueTurns($session);
    expect($turns)->not->toBeEmpty();

    $termId = (string) $turns[0]['card']['term_id'];
    $ofTerm = turnsOfTerm($session, $termId);
    $mode = (string) $ofTerm[0]['card']['exercise_mode'];

    $this->withHeader('Authorization', "Bearer {$token}")->postJson('/api/v1/reviews/batch', ['reviews' => [
        [
            'id' => (string) \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
            'term_id' => $termId, 'exercise_mode' => $mode,
            'response' => $ofTerm[0]['card']['answer'],
            'answered_at' => now()->toIso8601String(), 'client_seq' => 1,
            'session_id' => $session['session_id'], 'ladder_step' => $ofTerm[0]['card']['ladder_step'],
        ],
        [
            'id' => (string) \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
            'term_id' => $termId, 'exercise_mode' => $mode,
            'response' => 'nothing like the line',
            'answered_at' => now()->toIso8601String(), 'client_seq' => 2,
            'session_id' => $session['session_id'], 'ladder_step' => $ofTerm[1]['card']['ladder_step'],
        ],
    ]])->assertOk();

    expect((int) DB::table('learning_plan_term_stages')
        ->where('plan_id', $planId)->where('term_id', $termId)->value('choice_streak'))->toBe(0);

    $again = planSession($this, $token, $planId);
    $back = turnsOfTerm($again, $termId);
    expect($back)->not->toBeEmpty()
        ->and($back[0]['turn_level'])->toBe('choose');
});

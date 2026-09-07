<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanSessionSections as S;
use App\Modules\Learning\Domain\Service\PlanSittings;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * БЮДЖЕТ ДНЯ — наряд DAY-FIX-2, Ч.2 и DAY-FIX-3, Ч.4, на собранном ответе API.
 *
 *   день = два присеста: «Материал» ≤ 45 карточек, «Разговор» ≤ 25;
 *   спасателей в разогреве ≤ 5;
 *   реплика сцены — один показ на ступень B в день; ступень A — интро и упражнение в один день;
 *   шов — одним касанием, сборкой; выбор в шве запрещён;
 *   «Ты спросишь» — никогда не выбор;
 *   системной клавиатуры в плане нет.
 */
beforeEach(function (): void {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

/** @return list<array<string, mixed>> */
function tasksIn(array $session, string $code, ?int $day = null): array
{
    return array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['section_code'] === $code && ($day === null || $t['from_day_index'] === $day),
    ));
}

function typedModes(): array
{
    return array_map(
        static fn (ExerciseMode $m): string => $m->value,
        array_values(array_filter(ExerciseMode::cases(), static fn (ExerciseMode $m): bool => $m !== ExerciseMode::Intro && $m->forgivesTypos())),
    );
}

it('deals day 1 as TWO sittings within their ceilings — the material, then the scene spoken', function () {
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    // ДВА ПРИСЕСТА — ЭТО ДВА ЗАХОДА (наряд DAY-GATE-1, Ч.1.4). До него оба ехали одной посадкой и
    // резались на экране; теперь «Слова и фразы» и «Разговор» — разные этапы дня и разные сессии,
    // и потолок у каждого свой.
    $material = planSession($this, $token, $planId, 1);
    expect($material['stage'])->toBe('material')
        ->and($material['sittings'])->toBe([count($material['tasks'])])
        ->and(count($material['tasks']))->toBeLessThanOrEqual(PlanSittings::MATERIAL_MAX_CARDS)
        ->and(count(tasksIn($material, S::WARMUP)))->toBeLessThanOrEqual(5)
        ->and(tasksIn($material, S::DIALOGUE))->toBeEmpty();

    answerTasks($this, $token, $material);
    $conversation = planSession($this, $token, $planId, 1);
    expect($conversation['stage'])->toBe('conversation')
        ->and(count($conversation['tasks']))->toBeLessThanOrEqual(PlanSittings::CONVERSATION_MAX_CARDS)
        ->and($conversation['dialogues'])->toHaveCount(1);

    // THE SCENE IS SPOKEN THE DAY IT IS MET (решение владельца 05.09): знакомство стоит в материале,
    // разговор — следующим этапом ТОГО ЖЕ дня.
    expect(array_column($material['tasks'], 'section_code'))->toContain(S::DIALOGUE_INTRO);
    foreach ($conversation['tasks'] as $task) {
        expect($task['section_code'])->toBe(S::DIALOGUE);
    }

    // ONE SHOW PER STAGE PER DAY in the conversation: no line of the scene is dealt twice on the
    // same rung there. The introduction's own exercise stands in the material, on stage A.
    $seen = [];
    foreach (tasksIn($conversation, S::DIALOGUE) as $task) {
        $key = $task['card']['term_id'] . '#' . $task['stage'];
        expect($seen)->not->toContain($key)
            ->and($task['stage'])->toBe('b');
        $seen[] = $key;
    }
    foreach (tasksIn($material, S::DIALOGUE_INTRO) as $task) {
        expect($task['stage'])->toBe('a');
    }
});

it('never deals a typed trainer in a plan sitting — the keyboard is not a plan exercise', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $day1 = planSession($this, $token, $planId);
    foreach ($day1['tasks'] as $task) {
        expect($task['card']['exercise_mode'])->not->toBeIn(typedModes());
    }

    $seq = walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    $day2 = planSession($this, $token, $planId);
    foreach ($day2['tasks'] as $task) {
        expect($task['card']['exercise_mode'])->not->toBeIn(typedModes());
    }
    expect($seq)->toBeGreaterThan(1);
});

it('never lets «Ты спросишь» be a choice — blocks or the voice, on the day and in the seam', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $check = static function (array $session): void {
        $asked = 0;
        foreach ($session['tasks'] as $task) {
            if ($task['card']['exercise_mode'] !== 'situational_ask') {
                continue;
            }
            $asked++;
            expect($task['turn_level'])->toBeIn(['assemble', 'say'])
                ->and($task['card']['options'])->toBeNull()
                ->and($task['card']['chips'])->toBeArray();
        }
        expect($asked)->toBeGreaterThan(0);

        foreach ($session['dialogues'] as $dialogue) {
            foreach ($dialogue['turns'] as $turn) {
                if ($turn['shelf'] === 'ask') {
                    expect($turn['level'])->not->toBe('choose');
                }
            }
        }
    };

    [$conversation] = stageSession($this, $token, $planId, 1, 'conversation');
    $check($conversation);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);
    [$seam] = stageSession($this, $token, $planId, 2, 'conversation');
    $check($seam);
});

it('deals the seam as ONE touch by assembly, keeps the day under the ceiling, and runs the scene second', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    [$session] = stageSession($this, $token, $planId, 2, 'conversation');
    expect($session['day_index'])->toBe(2);

    // THE SEAM: every line of scene 1 comes back once, by assembly — choose is forbidden there.
    $seam = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['section'] === 'review' && in_array($t['card']['exercise_mode'], ['situational_say', 'situational_ask'], true),
    ));
    expect($seam)->not->toBeEmpty();
    $seen = [];
    foreach ($seam as $task) {
        expect($task['turn_level'])->toBe('assemble')
            ->and($task['card']['chips'])->toBeArray()
            ->and($task['card']['options'])->toBeNull();
        expect($seen)->not->toContain($task['card']['term_id']);
        $seen[] = $task['card']['term_id'];
    }

    // ПОТОЛОК ВЯЖЕТ КАЖДЫЙ ПРИСЕСТ, а присесты теперь — этапы дня (наряд DAY-GATE-1, Ч.1.4):
    // в «Разговоре» только диалог и шов, прогон отдельным этапом после него.
    expect(count($session['tasks']))->toBeLessThanOrEqual(PlanSittings::CONVERSATION_MAX_CARDS)
        ->and(tasksIn($session, S::SCENE_RUN))->toBeEmpty();
    foreach ($session['tasks'] as $task) {
        expect($task['section_code'])->toBe(S::DIALOGUE);
    }

    // …И ПРОГОН ЗАКРЫВАЕТ ДЕНЬ — следующим этапом, а не хвостом того же присеста.
    answerTasks($this, $token, $session);
    $rehearsal = planSession($this, $token, $planId, 2);
    expect($rehearsal['stage'])->toBe('rehearsal')
        ->and(tasksIn($rehearsal, S::SCENE_RUN))->not->toBeEmpty();
    foreach ($rehearsal['tasks'] as $task) {
        expect($task['section_code'])->toBe(S::SCENE_RUN);
    }
});

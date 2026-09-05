<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanSessionSections as S;
use App\Modules\Learning\Domain\Service\PlanSittings;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * БЮДЖЕТ ДНЯ — наряд DAY-FIX-2, Ч.2, на собранном ответе API.
 *
 *   день = один присест ≤ 40 карточек, второй — только прогон сцены;
 *   слова и связки за посадку ≤ 12;
 *   спасателей в разогреве ≤ 5;
 *   реплика сцены — один показ на ступень в день;
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

it('deals day 1 as ONE sitting within the ceiling, with the whole scene met AND spoken', function () {
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    $session = planSession($this, $token, $planId);

    expect($session['sittings'])->toBe([count($session['tasks'])])
        ->and(count($session['tasks']))->toBeLessThanOrEqual(PlanSittings::MAX_TASKS_PER_SITTING)
        ->and(count(tasksIn($session, S::WARMUP)))->toBeLessThanOrEqual(5)
        ->and(count(tasksIn($session, S::WORDS)))->toBeLessThanOrEqual(12);

    // THE SCENE IS SPOKEN THE DAY IT IS MET (решение владельца 05.09): the dialogue section is in
    // day 1's own sitting, after the introduction.
    $codes = array_values(array_unique(array_column($session['tasks'], 'section_code')));
    expect($codes)->toContain(S::DIALOGUE_INTRO)
        ->and($codes)->toContain(S::DIALOGUE)
        ->and(array_search(S::DIALOGUE_INTRO, $codes, true))->toBeLessThan(array_search(S::DIALOGUE, $codes, true))
        ->and($session['dialogues'])->toHaveCount(1);

    // ONE SHOW PER STAGE PER DAY: no line of the scene is dealt twice on the same rung.
    $seen = [];
    foreach (tasksIn($session, S::DIALOGUE) as $task) {
        $key = $task['card']['term_id'] . '#' . $task['stage'];
        expect($seen)->not->toContain($key);
        $seen[] = $key;
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

    $check(planSession($this, $token, $planId));

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);
    $check(planSession($this, $token, $planId));
});

it('deals the seam as ONE touch by assembly, keeps the day under the ceiling, and runs the scene second', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    $session = planSession($this, $token, $planId);
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

    // THE CEILING binds the day part; the прогон of scene 1 is the second sitting.
    $run = tasksIn($session, S::SCENE_RUN);
    expect($run)->not->toBeEmpty()
        ->and($session['sittings'])->toBe([count($session['tasks']) - count($run), count($run)])
        ->and($session['sittings'][0])->toBeLessThanOrEqual(PlanSittings::MAX_TASKS_PER_SITTING)
        ->and(count(tasksIn($session, S::WORDS)))->toBeLessThanOrEqual(12)
        ->and(count(tasksIn($session, S::WARMUP)))->toBeLessThanOrEqual(10);

    // …and the day part comes BEFORE the прогон, whole.
    $last = count($session['tasks']) - 1;
    expect($session['tasks'][$last]['section_code'])->toBe(S::SCENE_RUN);
});

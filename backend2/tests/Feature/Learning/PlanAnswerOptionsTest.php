<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanSessionSections as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * ВАРИАНТЫ ТВОЕГО ХОДА — наряд DAY-2-FIX, Ч.1.5.
 *
 * Живой прогон владельца нашёл два дефекта в одном списке, и оба делают выбор бессмысленным:
 * реплика собеседника среди вариантов ответа, и второй вариант, отвечающий на тот же вопрос, что и
 * правильный. Ни один из них не виден по коду — оба видны только на собранной карточке, поэтому
 * проверка идёт по ответу API, а не по юниту рядом с правилом.
 */
beforeEach(function (): void {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

/**
 * Полка и умение каждого варианта карточки — по тексту, потому что на проводе варианты это тексты.
 *
 * @return list<array{text: string, shelf: ?string, skill: ?string}>
 */
function optionFacts(array $card): array
{
    $rows = DB::table('terms')
        ->whereIn('text', $card['options'] ?? [])
        ->get(['text', 'shelf', 'skill_ref']);

    $out = [];
    foreach ($card['options'] ?? [] as $option) {
        $row = $rows->firstWhere('text', $option);
        $out[] = [
            'text' => (string) $option,
            'shelf' => $row?->shelf === null ? null : (string) $row->shelf,
            'skill' => $row?->skill_ref === null ? null : (string) $row->skill_ref,
        ];
    }

    return $out;
}

it('никогда не предлагает реплику собеседника как твой ответ', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    // ДИАЛОГ ЖИВЁТ В ПРИСЕСТЕ «РАЗГОВОР» (наряд DAY-GATE-1): до него надо дойти, пройдя материал.
    [$session] = stageSession($this, $token, $planId, 2, 'conversation');
    $turns = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['section_code'] === S::DIALOGUE
            && in_array($t['card']['exercise_mode'], ['situational_say', 'situational_ask'], true),
    ));

    expect($turns)->not->toBeEmpty();

    foreach ($turns as $task) {
        foreach (optionFacts($task['card']) as $option) {
            // `hear` — ярус «понимаю» (канон §3): эти реплики человек не произносит никогда, и
            // предлагать их как его ответ значит предлагать ему чужую роль.
            expect($option['shelf'])->not->toBe('hear', "«{$option['text']}» — реплика собеседника");
        }
    }
});

it('на каждом ходу сцены ровно один вариант отвечает на вопрос этого хода', function () {
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    // ДИАЛОГ ЖИВЁТ В ПРИСЕСТЕ «РАЗГОВОР» (наряд DAY-GATE-1): до него надо дойти, пройдя материал.
    [$session] = stageSession($this, $token, $planId, 2, 'conversation');
    $turns = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['section_code'] === S::DIALOGUE
            && in_array($t['card']['exercise_mode'], ['situational_say', 'situational_ask'], true),
    ));

    expect($turns)->not->toBeEmpty();

    foreach ($turns as $task) {
        // Правило про варианты — про КАРТОЧКУ ВЫБОРА. У сборки вариантов нет вовсе (наряд
        // SCENE-RUN, Ч.1), и «ровно один вариант с этим умением» там не про что спрашивать.
        if ($task['turn_level'] !== 'choose') {
            continue;
        }
        $card = $task['card'];
        $own = DB::table('terms')->where('id', $card['term_id'])->value('skill_ref');
        // Карточка без умения — это карточное нарушение, и день его уже считает; здесь проверять
        // нечего, потому что «тот же вопрос» без умения не определён.
        if ($own === null) {
            continue;
        }

        $sameSkill = array_values(array_filter(
            optionFacts($card),
            static fn (array $o): bool => $o['skill'] === $own,
        ));

        // Ровно один — сам ответ. Второй вариант с тем же умением отвечает на тот же вопрос, а
        // значит тоже верен, и карточка превращается в монетку, записанную в append-only лог.
        expect($sameSkill)->toHaveCount(1)
            ->and($sameSkill[0]['text'])->toBe($card['answer']);
    }
});

it('добирает из соседних сцен, а не из каталога, когда своя сцена дала мало', function () {
    // Правило пула — суждение, а не предпочтение: вариант вне плана это фраза не из этого
    // разговора. Проверяется тем, что КАЖДЫЙ вариант хода принадлежит какому-то дню этого плана.
    [$user, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    walkDay($this, $token, $planId, 1);
    ageHistory($user->id, days: 1);

    $session = planSession($this, $token, $planId);
    $planTexts = DB::table('learning_plan_days as d')
        ->join('collection_items as ci', 'ci.collection_id', '=', 'd.collection_id')
        ->join('terms as t', 't.id', '=', 'ci.term_id')
        ->where('d.plan_id', $planId)
        ->whereNull('ci.deleted_at')
        ->pluck('t.text')
        ->all();

    foreach ($session['tasks'] as $task) {
        if ($task['section_code'] !== S::DIALOGUE
            || ! in_array($task['card']['exercise_mode'], ['situational_say', 'situational_ask'], true)) {
            continue;
        }
        foreach ($task['card']['options'] ?? [] as $option) {
            expect($planTexts)->toContain($option);
        }
    }
});

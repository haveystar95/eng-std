<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanSessionSections as S;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * ХОД БЕЗ ВАРИАНТОВ СТАНОВИТСЯ СБОРКОЙ, А НЕ ВЫПАДАЕТ — наряд DAY-FIX-2, Ч.1.7 / DECISIONS п. 268.
 *
 * Пул вариантов твоего хода — реплики ЭТОГО плана, минус уже сказанное в разговоре, минус реплики
 * того же умения (Ч.1.5). Третья-четвёртая реплика сцены законно остаётся без чужих ответов, и до
 * живого прогона 05.09 такая карточка просто не раздавалась: реплика без ступени B, день без
 * «пройден». Замок — на пейлоаде посадки: каждая реплика полок say/ask имеет ход, и ход, который
 * пришёл блоками, назван сборкой, чтобы подпись над карточкой и слово на экране дня не разошлись.
 */
beforeEach(function (): void {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

it('deals EVERY spoken line of the scene a turn when the pool runs dry — by assembly, named so', function () {
    [, $token, $planId] = startedPlan($this, ['event_date' => now()->addDays(10)->format('Y-m-d')]);

    // Starve the pool: the plan's other days are gone, so a reply has only its own scene to draw
    // wrong answers from — and its own scene shrinks by what the conversation has already said.
    $day1 = DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->value('collection_id');
    $others = DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', '>', 1)
        ->whereNotNull('collection_id')->pluck('collection_id')->all();
    DB::table('collection_items')->whereIn('collection_id', $others)->update(['deleted_at' => now()]);

    $spoken = DB::table('collection_items as ci')
        ->join('terms as t', 't.id', '=', 'ci.term_id')
        ->where('ci.collection_id', $day1)
        ->whereIn('t.shelf', ['say', 'ask'])
        ->pluck('t.id')
        ->all();
    expect($spoken)->not->toBeEmpty();

    $session = planSession($this, $token, $planId);
    $turns = array_values(array_filter(
        $session['tasks'],
        static fn (array $t): bool => $t['section_code'] === S::DIALOGUE
            && in_array($t['card']['exercise_mode'], ['situational_say', 'situational_ask'], true),
    ));

    $dealt = array_map(static fn (array $t): string => $t['card']['term_id'], $turns);
    foreach ($spoken as $termId) {
        expect($dealt)->toContain($termId);
    }

    $assembled = 0;
    foreach ($turns as $task) {
        $card = $task['card'];
        $byBlocks = ($card['chips'] ?? null) !== null;
        // A card of blocks is an assembly, whatever the ladder meant to deal; a card of options is a
        // choice. The level on the wire says which, and nothing else.
        expect($task['turn_level'])->toBe($byBlocks ? 'assemble' : 'choose', $card['answer']);
        if ($byBlocks) {
            $assembled++;
        }
    }
    // The pool of one scene cannot furnish three wrong answers for every reply of that scene.
    expect($assembled)->toBeGreaterThan(0);
});

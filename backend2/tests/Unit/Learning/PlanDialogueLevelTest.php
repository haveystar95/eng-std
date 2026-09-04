<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\PlanDialogueLevel;
use App\Modules\Learning\Domain\ValueObject\PlanTermStage;
use App\Modules\Learning\Domain\ValueObject\PlanTurnLevel;

/**
 * ТЕСТ-ЗАМКИ Ч.1 наряда SCENE-RUN — четыре предложения из наряда, слово в слово.
 *
 * «Два верных выбора → следующий показ assemble; ошибка на assemble → остаётся assemble; один
 * верный + один неверный → остаётся choose.»
 */
function walk(array $answers): PlanTermStage
{
    $stage = new PlanTermStage('01PLAN', '01TERM');
    foreach ($answers as $correct) {
        $stage = $stage->afterChoice($correct);
    }

    return $stage;
}

it('starts every turn on the choice — nothing proved is not something proved', function () {
    expect(walk([])->level())->toBe(PlanTurnLevel::Choose);
});

it('opens the assembly after two error-free choices in a row', function () {
    expect(walk([true])->level())->toBe(PlanTurnLevel::Choose)
        ->and(walk([true, true])->level())->toBe(PlanTurnLevel::Assemble);
});

it('keeps the turn on the choice after one right and one wrong', function () {
    // Порядок не важен: подряд — значит подряд.
    expect(walk([true, false])->level())->toBe(PlanTurnLevel::Choose)
        ->and(walk([false, true])->level())->toBe(PlanTurnLevel::Choose);
});

it('does NOT drop a failed assembly back to the choice', function () {
    // Ход уходит в хвост секции и возвращается снова СБОРКОЙ. Иначе одна ошибка отбирала бы у
    // человека уровень, который он уже заслужил, — и он бы заслуживал его снова и снова.
    $earned = walk([true, true]);

    expect($earned->afterChoice(false)->level())->toBe(PlanTurnLevel::Assemble)
        ->and($earned->afterChoice(false)->afterChoice(false)->level())->toBe(PlanTurnLevel::Assemble)
        // И верная сборка ничего не ломает: счётчик уже сделал своё дело.
        ->and($earned->afterChoice(true)->level())->toBe(PlanTurnLevel::Assemble);
});

it('names two as the number of choices the canon asks for', function () {
    expect(PlanDialogueLevel::CHOICES_FOR_ASSEMBLY)->toBe(2);
});

it('leaves what the run proved alone when a choice is answered', function () {
    // Три факта в одной строке, и они не про одно и то же: выбор не отменяет «сказал сам».
    $said = (new PlanTermStage('01PLAN', '01TERM'))->afterRun(said: true, fast: true);

    expect($said->afterChoice(false)->saidInRun)->toBeTrue()
        ->and($said->afterChoice(false)->saidFast)->toBeTrue();
});

it('remembers the BEST run result and never a worse one', function () {
    $stage = (new PlanTermStage('01PLAN', '01TERM'))->afterRun(said: true, fast: true);

    expect($stage->afterRun(said: false, fast: false)->saidInRun)->toBeTrue()
        ->and($stage->afterRun(said: true, fast: false)->saidFast)->toBeTrue()
        // «Сразу» — разновидность «сказал»: быстрым пропуск не бывает.
        ->and((new PlanTermStage('01PLAN', '01TERM'))->afterRun(said: false, fast: true)->saidFast)
        ->toBeFalse();
});

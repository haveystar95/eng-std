<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\ValueObject\PlanTurnLevel;

/**
 * СТРОГОСТЬ ХОДА — наряд SCENE-RUN, Ч.1 (правило после вердикта владельца).
 *
 * Сборка привязана не к счётчику удач, а к ПОВТОРНОМУ ПОЯВЛЕНИЮ реплики: выбор закрывается одним
 * верным ответом, и следующий показ той же реплики — сборка. Ничего для этого не хранится: место
 * шага в чек-листе и есть ответ, а чек-лист — проекция append-only журнала.
 */
it('makes the first touch of stage B a choice and the next one an assembly', function () {
    expect(PlanTurnLevel::forStep(1))->toBe(PlanTurnLevel::Choose)
        ->and(PlanTurnLevel::forStep(2))->toBe(PlanTurnLevel::Assemble);
});

it('keeps a repeated appearance on the assembly, however many times it comes back', function () {
    // Ошибка на сборке ступень не открывает и в выбор не откатывает: шаг остаётся незакрытым, и
    // реплика возвращается — в хвост присеста, в шов следующего дня — снова сборкой.
    expect(PlanTurnLevel::forStep(3))->toBe(PlanTurnLevel::Assemble)
        ->and(PlanTurnLevel::forStep(9))->toBe(PlanTurnLevel::Assemble);
});

it('reads a nonsensical ordinal as the first touch rather than as an assembly', function () {
    // Ноль и отрицательное — это «шаг неизвестен», и честный ответ здесь выбор: сборка без выбора
    // была бы ступенью C под другим именем, а «C никогда не первый: страшно».
    expect(PlanTurnLevel::forStep(0))->toBe(PlanTurnLevel::Choose)
        ->and(PlanTurnLevel::forStep(-1))->toBe(PlanTurnLevel::Choose);
});

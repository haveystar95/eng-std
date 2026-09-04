<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\SceneCensus;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
use App\Modules\Learning\Domain\ValueObject\PlanStage;
use App\Modules\Learning\Domain\ValueObject\PlanTermStage;
use App\Modules\Learning\Domain\ValueObject\PlanTermStanding;
use App\Modules\Learning\Domain\ValueObject\SceneMaturity;

/**
 * ЗРЕЛОСТЬ СЦЕНЫ ТРЕМЯ СЛОВАМИ — наряд SCENE-RUN, Ч.3.
 *
 * До этого наряда состояний было два из трёх, и третьего не было не по забывчивости: переписи
 * ступени C на проводе не существовало, и выводить вердикт из данных, которых нет, — это ровно тот
 * процент, который владелец три дня читал нулём.
 */
function standing(PlanStage $stage, bool $chosen): PlanTermStanding
{
    return new PlanTermStanding(
        stage: $stage,
        checklist: [['mode' => ExerciseMode::SituationalSay->value, 'ordinal' => 1, 'done' => $chosen]],
        nextMode: $chosen ? null : ExerciseMode::SituationalSay,
        stageComplete: $chosen,
        waitingForNight: false,
        finished: false,
        softened: false,
    );
}

function proof(bool $said, bool $fast = false): PlanTermStage
{
    return (new PlanTermStage('01PLAN', '01TERM'))->afterRun($said, $fast);
}

it('calls a scene MET while the learner has only been introduced to it', function () {
    $turns = [standing(PlanStage::A, chosen: false), standing(PlanStage::A, chosen: false)];

    expect(SceneCensus::maturityOf($turns, [proof(false), proof(false)]))->toBe(SceneMaturity::Met);
});

it('calls it APPLYING once every turn has been chosen in the conversation at least once', function () {
    $turns = [standing(PlanStage::B, chosen: true), standing(PlanStage::B, chosen: true)];

    expect(SceneCensus::maturityOf($turns, [proof(false), proof(false)]))->toBe(SceneMaturity::Applying);
});

it('does NOT call a scene «говоришь сам» when one turn was skipped', function () {
    // Наряд Ч.3.4, замок первый. Пропуск это «не сказал», спасатель — «сказал не сам»; ни один из
    // них не доказывает того, что доказывает голос.
    $turns = [standing(PlanStage::B, chosen: true), standing(PlanStage::B, chosen: true)];

    expect(SceneCensus::maturityOf($turns, [proof(true, true), proof(false)]))
        ->toBe(SceneMaturity::Applying);
});

it('calls it SPEAKING when every turn has sounded in a run', function () {
    $turns = [standing(PlanStage::B, chosen: true), standing(PlanStage::B, chosen: true)];

    expect(SceneCensus::maturityOf($turns, [proof(true), proof(true)]))->toBe(SceneMaturity::Speaking);
});

it('is «говоришь сам» and still NOT ready when the speed is below the threshold', function () {
    // Наряд Ч.3.4, замок второй, и он же канон §4: «медленный успех остаётся успехом, но в
    // готовность не идёт, пока не повторён быстро». На стойке отвечают за три секунды.
    $turns = [standing(PlanStage::B, chosen: true), standing(PlanStage::B, chosen: true)];
    $stages = [proof(true, true), proof(true)];

    expect(SceneCensus::maturityOf($turns, $stages))->toBe(SceneMaturity::Speaking)
        // Половина «сразу» при пороге 0.7 — не готова.
        ->and(SceneCensus::isReady($turns, $stages, 0.7))->toBeFalse()
        // …и та же сцена при пороге 0.5 — готова. Порог это продуктовое суждение, не свойство сцены.
        ->and(SceneCensus::isReady($turns, $stages, 0.5))->toBeTrue();
});

it('never calls a scene ready while it is not spoken at all', function () {
    $turns = [standing(PlanStage::B, chosen: true)];

    expect(SceneCensus::isReady($turns, [proof(false)], 0.0))->toBeFalse();
});

it('has nothing to say about a scene with no turns of the learner’s own', function () {
    expect(SceneCensus::maturityOf([], []))->toBe(SceneMaturity::Met)
        ->and(SceneCensus::isReady([], [], 0.0))->toBeFalse();
});

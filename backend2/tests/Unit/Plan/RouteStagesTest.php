<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Service\RouteStages;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\RouteStage;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\StageState;

/**
 * THE STAGES OF A DAY ON THE ROUTE (PLAN-UI-3): only the stages the day has, each `done`,
 * `current` or `locked` — and the client treats any other word as an error.
 */

/** @return list<array{0: string, 1: string}> */
function routeStagesOf(array $stages): array
{
    return array_map(static fn (RouteStage $s): array => [$s->stage->value, $s->state->value], $stages);
}

it('a stage with no cards is not on the route', function () {
    $stages = RouteStages::of(DayType::Scene, [
        'words' => ['total' => 4, 'answered' => 4],
        'speak' => ['total' => 3, 'answered' => 0],
    ], closed: false, availableToday: true);

    expect(routeStagesOf($stages))->toBe([['words', 'done'], ['speak', 'current']]);
});

it('a closed day has every present stage done', function () {
    $stages = RouteStages::of(DayType::Scene, [
        'words' => ['total' => 8, 'answered' => 8],
        'phrases' => ['total' => 6, 'answered' => 5],
        'speak' => ['total' => 4, 'answered' => 0],
    ], closed: true, availableToday: false);

    expect(routeStagesOf($stages))->toBe([['words', 'done'], ['phrases', 'done'], ['speak', 'done']]);
});

it('only the first unfinished stage is current', function () {
    $stages = RouteStages::of(DayType::Scene, [
        'words' => ['total' => 32, 'answered' => 32],
        'phrases' => ['total' => 18, 'answered' => 7],
        'dialogue' => ['total' => 1, 'answered' => 0],
        'listen' => ['total' => 8, 'answered' => 0],
        'speak' => ['total' => 4, 'answered' => 0],
    ], closed: false, availableToday: true);

    expect(routeStagesOf($stages))->toBe([
        ['words', 'done'], ['phrases', 'current'], ['dialogue', 'locked'], ['listen', 'locked'], ['speak', 'locked'],
    ]);
});

it('a dealt day is read off its cards, not off its type', function () {
    // A review day whose returns include a phrase deals a phrases stage the type does not name.
    $stages = RouteStages::of(DayType::Review, [
        'phrases' => ['total' => 1, 'answered' => 0],
        'speak' => ['total' => 6, 'answered' => 0],
    ], closed: false, availableToday: true);

    expect(routeStagesOf($stages))->toBe([['phrases', 'current'], ['speak', 'locked']]);
});

it('the rehearsal has one stage', function () {
    expect(routeStagesOf(RouteStages::of(DayType::Rehearsal, [], closed: false, availableToday: false)))
        ->toBe([['speak', 'locked']]);
});

it('a review day not dealt yet has words and speak', function () {
    expect(routeStagesOf(RouteStages::of(DayType::Review, [], closed: false, availableToday: false)))
        ->toBe([['words', 'locked'], ['speak', 'locked']]);
});

it('a locked day has no current stage', function () {
    $stages = RouteStages::of(DayType::Scene, [], closed: false, availableToday: false);

    expect(array_column(routeStagesOf($stages), 1))->toBe(['locked', 'locked', 'locked', 'locked', 'locked'])
        ->and(array_column(routeStagesOf($stages), 0))->toBe(['words', 'phrases', 'dialogue', 'listen', 'speak']);
});

it('the day that may be started today begins at its first stage', function () {
    expect(routeStagesOf(RouteStages::of(DayType::Scene, [], closed: false, availableToday: true)))->toBe([
        ['words', 'current'], ['phrases', 'locked'], ['dialogue', 'locked'], ['listen', 'locked'], ['speak', 'locked'],
    ]);
});

it('the dealer’s outline decides a not-dealt day’s stages over its type', function () {
    // A scene whose lesson has no two-message exchange deals no listening and no speaking.
    $stages = RouteStages::of(DayType::Scene, [], closed: false, availableToday: true, outline: [Stage::Dialogue, Stage::Words, Stage::Phrases]);

    expect(routeStagesOf($stages))->toBe([['words', 'current'], ['phrases', 'locked'], ['dialogue', 'locked']]);
});

it('a closed day with nothing dealt still reads done', function () {
    expect(array_column(routeStagesOf(RouteStages::of(DayType::Rehearsal, [], closed: true, availableToday: false)), 1))->toBe(['done']);
});

it('says a stage’s state with exactly three words', function () {
    expect(array_map(static fn (StageState $s): string => $s->value, StageState::cases()))->toBe(['done', 'current', 'locked']);
});

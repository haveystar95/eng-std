<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Service\RouteStages;
use App\Modules\Plan\Domain\ValueObject\ConversationState;
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

it('the rehearsal has «Вспомнить», and the talk after it', function () {
    expect(routeStagesOf(RouteStages::of(DayType::Rehearsal, [], closed: false, availableToday: false)))
        ->toBe([['recall', 'locked']])
        ->and(routeStagesOf(RouteStages::of(DayType::Rehearsal, [], closed: false, availableToday: false, hasConversation: true)))
        ->toBe([['recall', 'locked'], ['conversation', 'locked']]);
});

/**
 * THE SIXTH NODE (наряд CONV-1). It has no cards, so it cannot be read off the tallies: it is walked when its journal
 * says the talk ended, and it is the stage being walked once the cards are done. The defect this catches is a route
 * that draws «Разговор» as current while «Говорю сам» is still open — two current nodes on one day.
 */
it('draws the talk as the last node and never as a second current one', function () {
    $walked = ['words' => ['total' => 2, 'answered' => 2], 'speak' => ['total' => 2, 'answered' => 1]];

    expect(routeStagesOf(RouteStages::of(DayType::Scene, $walked, closed: false, availableToday: true, hasConversation: true)))
        ->toBe([['words', 'done'], ['speak', 'current'], ['conversation', 'locked']])
        ->and(routeStagesOf(RouteStages::of(
            DayType::Scene,
            ['words' => ['total' => 2, 'answered' => 2], 'speak' => ['total' => 2, 'answered' => 2]],
            closed: false, availableToday: true, hasConversation: true,
        )))->toBe([['words', 'done'], ['speak', 'done'], ['conversation', 'current']])
        // A talk that is over is a walked node even when a card stage is not: the learner may start it
        // early, and the route says what each stage is, not what order they were walked in.
        ->and(routeStagesOf(RouteStages::of(
            DayType::Scene, $walked, closed: false, availableToday: true,
            hasConversation: true, conversation: ConversationState::Ended,
        )))->toBe([['words', 'done'], ['speak', 'current'], ['conversation', 'done']]);
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

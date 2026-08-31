<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\DayCapacity;

/**
 * The one table, and the three numbers that come out of it.
 *
 * Both used to live in more than one place — the table in the scheduler, in the offline double and
 * in the prompt; the split as a formula here and a band in the validator. Every duplicate was a
 * chance for two halves of the same rule to disagree, and one of them did: the prompt was told to
 * aim at a band whose bottom the scheduler treated as the number, so a generous day came back
 * «не влезает» on a plan nobody had changed.
 */
it('reads the capacity table: 10 → 7, 20 → 14, 40 → 24', function () {
    expect(DayCapacity::forMinutes(10))->toBe(7)
        ->and(DayCapacity::forMinutes(20))->toBe(14)
        ->and(DayCapacity::forMinutes(40))->toBe(24);
});

it('draws a straight line between the anchors, and past them', function () {
    expect(DayCapacity::forMinutes(30))->toBe(19)        // 14 + 10 × 0.5
        ->and(DayCapacity::forMinutes(15))->toBe(11)     // 7 + 5 × 0.7 = 10.5
        ->and(DayCapacity::forMinutes(60))->toBe(34)     // the 20–40 slope, extended
        ->and(DayCapacity::forMinutes(1))->toBeGreaterThanOrEqual(1);
});

it('splits a day into lines, connectors and words that add up exactly', function (int $budget, array $want) {
    expect(DayCapacity::split($budget))->toBe($want)
        ->and(array_sum(DayCapacity::split($budget)))->toBe($budget);
})->with([
    [14, ['phrases' => 8, 'chunks' => 2, 'words' => 4]],
    [7, ['phrases' => 4, 'chunks' => 1, 'words' => 2]],
    [24, ['phrases' => 14, 'chunks' => 3, 'words' => 7]],
]);

it('keeps at least one connector, and gives up the connector before the line', function () {
    // A day is a conversation before it is anything else, so the line is the last thing to go.
    expect(DayCapacity::split(3))->toBe(['phrases' => 1, 'chunks' => 1, 'words' => 1])
        ->and(DayCapacity::split(2))->toBe(['phrases' => 1, 'chunks' => 1, 'words' => 0])
        ->and(DayCapacity::split(1))->toBe(['phrases' => 1, 'chunks' => 0, 'words' => 0]);
});

it('never returns a split that does not add up, at any budget a learner could ask for', function () {
    for ($minutes = 1; $minutes <= 120; $minutes++) {
        $budget = DayCapacity::forMinutes($minutes);
        $split = DayCapacity::split($budget);

        expect(array_sum($split))->toBe($budget)
            ->and($split['phrases'])->toBeGreaterThanOrEqual(1)
            ->and($split['words'])->toBeGreaterThanOrEqual(0);
    }
});

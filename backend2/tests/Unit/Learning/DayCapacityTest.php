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

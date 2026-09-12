<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Service\UsualVisitTime;
use App\Modules\Identity\Domain\Service\VisitThrottle;

/**
 * THE USUAL VISIT TIME (PLAN-UI-3) — the hour the daily reminder is aimed at. Pure rule, no database.
 */
function uvtAt(string $hhmm): int
{
    [$h, $m] = array_map('intval', explode(':', $hhmm));

    return $h * 60 + $m;
}

it('answers 19:00 for a learner with no visits — catches a reminder at midnight for a new account', function () {
    expect(UsualVisitTime::of([]))->toBe(19 * 60);
});

it('takes the median of seven visits — catches a mean dragged by one odd morning visit', function () {
    $visits = array_map('uvtAt', ['20:10', '07:05', '20:40', '21:00', '20:20', '19:55', '20:35']);

    expect(UsualVisitTime::of($visits))->toBe(uvtAt('20:15')); // median 20:20 → quarter 20:15
});

it('reads only the newest seven visits — catches a habit that never updates', function () {
    $newest = array_fill(0, 7, uvtAt('08:30'));
    $old = array_fill(0, 5, uvtAt('21:00'));

    expect(UsualVisitTime::of([...$newest, ...$old]))->toBe(uvtAt('08:30'));
});

it('rounds down to the quarter hour — catches a reminder window that no 15-minute tick lands in', function (array $visits, string $expected) {
    expect(UsualVisitTime::of(array_map('uvtAt', $visits)))->toBe(uvtAt($expected));
})->with([
    'exact quarter stays' => [['18:45'], '18:45'],
    '18:59 goes down' => [['18:59'], '18:45'],
    '19:14 goes down' => [['19:14'], '19:00'],
    'even count: lower of the middle average' => [['19:00', '19:20'], '19:00'], // median 19:10 → 19:00
]);

it('keeps evening visits across midnight in the evening — catches a median that jumps to the morning', function () {
    // A plain sort puts 00:10 and 00:20 first; the median of five would be 23:40 by luck or a
    // morning hour by bad luck. Visits before 04:00 are the tail of the previous evening.
    $visits = array_map('uvtAt', ['23:40', '00:10', '23:55', '00:20', '22:00']);
    expect(UsualVisitTime::of($visits))->toBe(uvtAt('23:45')); // 22:00, 23:40, 23:55, 24:10, 24:20 → 23:55 → 23:45

    // Past midnight folds back: 23:50, 00:10, 00:20 → median 24:10 → 24:00 → 00:00.
    expect(UsualVisitTime::of(array_map('uvtAt', ['23:50', '00:10', '00:20'])))->toBe(0);

    // A night owl at 01:30 every day is 01:30, not «late evening».
    expect(UsualVisitTime::of(array_fill(0, 3, uvtAt('01:30'))))->toBe(uvtAt('01:30'));
});

it('refuses a time of day outside the day — catches a UTC offset added twice', function () {
    UsualVisitTime::of([1440]);
})->throws(InvalidArgumentException::class);

it('counts one visit per half hour, measured from the last recorded one — catches a fidgeting session counted twelve times', function () {
    $t0 = new DateTimeImmutable('2026-09-12T19:00:00Z');

    expect(VisitThrottle::shouldRecord(null, $t0))->toBeTrue()
        ->and(VisitThrottle::shouldRecord($t0, $t0->modify('+29 minutes +59 seconds')))->toBeFalse()
        ->and(VisitThrottle::shouldRecord($t0, $t0->modify('+30 minutes')))->toBeTrue();
});

<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\Service\PlanCalendar;
use App\Modules\Plan\Domain\ValueObject\DayType;

/**
 * THE CALENDAR FORMULA (docs/plan-v2.md §7) — every length from one to ten, spelled out. The
 * server counts the days; the model is only ever told how many scenes that makes.
 */
it('lays out every plan length by the one rule', function (int $days, array $expected, int $scenes) {
    $layout = array_map(static fn (DayType $t): string => $t->value, PlanCalendar::layout($days));

    expect($layout)->toBe($expected)
        ->and(PlanCalendar::scenesCount($days))->toBe($scenes);
})->with([
    [1, ['scene'], 1],
    [2, ['scene', 'scene'], 2],
    [3, ['scene', 'scene', 'rehearsal'], 2],
    [4, ['scene', 'scene', 'review', 'rehearsal'], 2],
    [5, ['scene', 'scene', 'review', 'scene', 'rehearsal'], 3],
    [6, ['scene', 'scene', 'review', 'scene', 'scene', 'rehearsal'], 4],
    [7, ['scene', 'scene', 'review', 'scene', 'scene', 'review', 'rehearsal'], 4],
    [8, ['scene', 'scene', 'review', 'scene', 'scene', 'review', 'scene', 'rehearsal'], 5],
    [9, ['scene', 'scene', 'review', 'scene', 'scene', 'review', 'scene', 'scene', 'rehearsal'], 6],
    [10, ['scene', 'scene', 'review', 'scene', 'scene', 'review', 'scene', 'scene', 'review', 'rehearsal'], 6],
]);

it('refuses a plan outside one to ten days', function () {
    expect(fn () => PlanCalendar::layout(0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => PlanCalendar::layout(11))->toThrow(InvalidArgumentException::class);
});

it('counts the days until the event and clamps them to the plan range', function () {
    $today = new DateTimeImmutable('2026-09-10');

    expect(PlanCalendar::daysUntil($today, new DateTimeImmutable('2026-09-13')))->toBe(3)
        // The event's own day is not a study day; today alone is still one day.
        ->and(PlanCalendar::daysUntil($today, new DateTimeImmutable('2026-09-10')))->toBe(1)
        ->and(PlanCalendar::daysUntil($today, new DateTimeImmutable('2026-09-01')))->toBe(1)
        ->and(PlanCalendar::daysUntil($today, new DateTimeImmutable('2026-12-01')))->toBe(10);
});

it('prints the route summary with agreed numbers, in the learner language', function () {
    $ru = new NativeStrings('ru');

    expect($ru->routeSummary(PlanCalendar::layout(5)))->toBe('5 дней · 3 ситуации, 1 повторение, репетиция')
        ->and($ru->routeSummary(PlanCalendar::layout(1)))->toBe('1 день · 1 ситуация')
        ->and($ru->routeSummary(PlanCalendar::layout(3)))->toBe('3 дня · 2 ситуации, репетиция')
        ->and($ru->untilPhrase('До приёма', 5))->toBe('До приёма · 5 дней')
        ->and($ru->untilPhrase('До приёма', 1))->toBe('До приёма · 1 день')
        ->and($ru->untilPhrase('До приёма', 21))->toBe('До приёма · 21 день')
        ->and((new NativeStrings('en'))->routeSummary(PlanCalendar::layout(4)))->toBe('4 days · 2 situations, 1 review, rehearsal');
});

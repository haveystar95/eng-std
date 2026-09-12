<?php

declare(strict_types=1);

use App\Modules\Plan\Domain\Entity\PlanEvent;
use App\Modules\Plan\Domain\Service\NotificationRules;
use App\Modules\Plan\Domain\Service\NotificationTexts;
use App\Modules\Plan\Domain\Service\PlanEventRules;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\NotificationKind;
use App\Modules\Plan\Domain\ValueObject\PlanEventId;
use App\Modules\Plan\Domain\ValueObject\PlanEventKind;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * THE JOURNAL AND THE LETTERS (PLAN-UI-3) — which change is which fact, which fact is a letter,
 * when the tick owes one, and the exact words. Pure rules, no database.
 */
it('makes a shortened reschedule a rebuild and nothing else one — catches a letter for a longer plan', function (int $from, int $to, ?PlanEventKind $expected) {
    expect(PlanEventRules::forReschedule($from, $to))->toBe($expected);
})->with([
    'shorter' => [7, 5, PlanEventKind::DaysSkippedRebuilt],
    'same length (a new date only)' => [5, 5, null],
    'longer' => [5, 8, null],
]);

it('owes event_today on the event day at the reminder hour and event_passed after it, once each — catches a midnight letter, a second hour and a daily repeat', function (string $localNow, bool $hasToday, bool $hasPassed, array $expected) {
    $event = new DateTimeImmutable('2026-09-20');
    $due = PlanEventRules::dueOnCalendar($event, new DateTimeImmutable($localNow), 19 * 60, $hasToday, $hasPassed);

    expect(array_map(static fn (PlanEventKind $k): string => $k->value, $due))->toBe($expected);
})->with([
    'the day before' => ['2026-09-19 23:59:00', false, false, []],
    'event day, 00:15 — too early' => ['2026-09-20 00:15:00', false, false, []],
    'event day, 18:59 — before the reminder hour' => ['2026-09-20 18:59:00', false, false, []],
    'event day, 19:00 — the reminder hour' => ['2026-09-20 19:00:00', false, false, ['event_today']],
    'event day, already written' => ['2026-09-20 20:00:00', true, false, []],
    'the day after' => ['2026-09-21 00:00:00', true, false, ['event_passed']],
    'the day after, already written' => ['2026-09-21 09:00:00', true, true, []],
    'long after, today never written' => ['2026-09-25 09:00:00', false, false, ['event_passed']],
]);

it('turns only the letter-worthy facts into letters — catches «День 1 собран» a minute after «План готов»', function (PlanEventKind $kind, ?int $day, ?NotificationKind $expected) {
    expect(NotificationRules::forEvent($kind, $day))->toBe($expected);
})->with([
    'plan ready' => [PlanEventKind::PlanReady, null, NotificationKind::PlanReady],
    'day 1 ready is part of the plan' => [PlanEventKind::DayReady, 1, null],
    'day 2 ready' => [PlanEventKind::DayReady, 2, NotificationKind::DayReady],
    'day passed is journal only' => [PlanEventKind::DayPassed, 3, null],
    'rebuilt' => [PlanEventKind::DaysSkippedRebuilt, null, NotificationKind::DaysSkippedRebuilt],
    'event today' => [PlanEventKind::EventToday, null, NotificationKind::EventToday],
    'event passed is journal only' => [PlanEventKind::EventPassed, null, null],
]);

it('sends letters only about a plan that is still that plan — catches a reminder for a deleted plan', function (NotificationKind $kind, PlanStatus $status, bool $expected) {
    expect(NotificationRules::allows($kind, $status))->toBe($expected);
})->with([
    [NotificationKind::PlanReady, PlanStatus::Ready, true],
    [NotificationKind::PlanReady, PlanStatus::Active, true],
    [NotificationKind::PlanReady, PlanStatus::Deleted, false],
    [NotificationKind::DailyReminder, PlanStatus::Active, true],
    [NotificationKind::DailyReminder, PlanStatus::Overdue, true],
    [NotificationKind::DailyReminder, PlanStatus::Finished, false],
    [NotificationKind::DaysSkippedRebuilt, PlanStatus::Ready, false],
    [NotificationKind::DayReady, PlanStatus::Deleted, false],
]);

it('opens the reminder window for a quarter hour from the usual time — catches a reminder every tick of the evening', function (string $localNow, int $usual, bool $waiting, bool $reminded, bool $expected) {
    expect(NotificationRules::reminderDue(new DateTimeImmutable($localNow), $usual, $waiting, $reminded))->toBe($expected);
})->with([
    'before the window' => ['2026-09-12 18:59:00', 19 * 60, true, false, false],
    'window opens' => ['2026-09-12 19:00:00', 19 * 60, true, false, true],
    'inside' => ['2026-09-12 19:14:59', 19 * 60, true, false, true],
    'window closed' => ['2026-09-12 19:15:00', 19 * 60, true, false, false],
    'already reminded today' => ['2026-09-12 19:05:00', 19 * 60, true, true, false],
    'no day waiting' => ['2026-09-12 19:05:00', 19 * 60, false, false, false],
    'wraps past midnight' => ['2026-09-13 00:02:00', 23 * 60 + 50, true, false, true],
]);

it('keeps the journal line honest at birth — catches a day event without its day and a «rebuild» that grew', function () {
    $make = static fn (PlanEventKind $kind, ?int $day, array $payload): PlanEvent => PlanEvent::record(
        PlanEventId::generate(), UserId::generate(), PlanId::generate(), $kind, new DateTimeImmutable, null, $day, $payload,
    );

    expect($make(PlanEventKind::DayPassed, 2, [])->dayNumber)->toBe(2)
        ->and(fn () => $make(PlanEventKind::DayReady, null, []))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $make(PlanEventKind::DaysSkippedRebuilt, null, ['from' => 5, 'to' => 7]))->toThrow(InvalidArgumentException::class)
        ->and($make(PlanEventKind::DaysSkippedRebuilt, null, ['from' => 7, 'to' => 5])->payload)->toBe(['from' => 7, 'to' => 5]);
});

it('prints the owner’s Russian letters word for word — catches a drifted glossary', function () {
    $ru = new NotificationTexts('ru');

    $withDate = $ru->planReady('До приёма · 7 дней', 7, 'Регистратура');
    expect([$withDate->title, $withDate->body])->toBe(['План готов', 'До приёма · 7 дней. День 1 — «Регистратура»']);

    $noDate = $ru->planReady(null, 5, 'Запись к врачу');
    expect($noDate->body)->toBe('5 дней. День 1 — «Запись к врачу»');

    $day = $ru->dayReady(2, 'Приём у врача');
    expect([$day->title, $day->body])->toBe(['День 2 собран', '«Приём у врача» — можно начинать']);

    $reminder = $ru->dailyReminder(3, 'Аптека');
    expect([$reminder->title, $reminder->body])->toBe(['День 3 ждёт', '«Аптека» — начни с того места, где остановился']);

    $event = $ru->eventToday('Приём у врача');
    expect([$event->title, $event->body])->toBe(['Сегодня приём у врача', 'Скажи сам перед разговором — прогони его вслух'])
        ->and($ru->eventToday(null)->title)->toBe('Сегодня разговор')
        ->and($ru->eventToday('  ')->title)->toBe('Сегодня разговор');

    $rebuilt = $ru->daysSkippedRebuilt(7, 5);
    expect([$rebuilt->title, $rebuilt->body])->toBe(['Маршрут пересобран', 'Было 7 дней, стало 5'])
        ->and($ru->daysSkippedRebuilt(3, 2)->body)->toBe('Было 3 дня, стало 2');

    expect($ru->dayTitle(DayType::Review, null))->toBe('Повторение')
        ->and($ru->dayTitle(DayType::Rehearsal, null))->toBe('Репетиция')
        ->and($ru->dayTitle(DayType::Scene, 'Аптека'))->toBe('Аптека');
});

it('writes English letters and falls back to English for an unwritten language — catches a Russian letter to an English reader', function () {
    $en = new NotificationTexts('en');
    expect($en->daysSkippedRebuilt(7, 5)->body)->toBe('It was 7 days, now 5')
        ->and($en->planReady(null, 1, 'Pharmacy')->body)->toBe('1 day. Day 1 — “Pharmacy”')
        ->and((new NotificationTexts('de'))->dayReady(2, 'X')->title)->toBe('Day 2 is ready');
});

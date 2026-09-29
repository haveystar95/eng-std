<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Domain\Blueprint\BlueprintParser;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Exception\PlanDayBuilding;
use App\Modules\Plan\Domain\Exception\PlanDayLocked;
use App\Modules\Plan\Domain\Service\PlanCalendar;
use App\Modules\Plan\Domain\ValueObject\DayMetrics;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * THE DAYS OF A PLAN OPEN BY THE CALENDAR OF THEIR OPENING (наряд GEN-3 §11, `docs/plan-v2.md` §5): day N+1 comes on the
 * calendar day after day N was OPENED, in the learner's calendar; a plan catching up with its event does not wait for dates;
 * the event's day is a study day; a day next in line whose lesson is not written is `building`.
 */

/** A plan of `$days` days made on `$made` (local date), its lessons written unless told otherwise, not started. */
function psPlan(int $days, string $made, ?string $event = null, bool $lessons = true): Plan
{
    $kyiv = new DateTimeZone('Europe/Kyiv');
    $plan = Plan::create(
        id: PlanId::generate(),
        userId: UserId::generate(),
        goalText: 'Иду к врачу с ребёнком',
        targetLang: new LanguageCode('en'),
        nativeLang: new LanguageCode('ru'),
        level: PlanLevel::Beginner,
        daysRequested: $days,
        eventDate: $event === null ? null : new DateTimeImmutable($event, $kyiv),
        today: new DateTimeImmutable($made, $kyiv),
        now: new DateTimeImmutable($made.'T08:00:00', $kyiv),
        dayIds: static fn (): PlanDayId => PlanDayId::generate(),
    );
    $request = new PlanRequest('врач', 'English', 'Russian', PlanLevel::Beginner, PlanCalendar::scenesCount($plan->daysTotal()));
    $plan->acceptBlueprint((new BlueprintParser)->parse(FakePlanModel::planPayload($request)), new ModelCall('plan-builder-v2.1', 'test', 'fake', '0.000000', 1, 1), [], static fn (): PlanSceneId => PlanSceneId::generate());
    if ($lessons) {
        planWriteLessons($plan);
    }

    return $plan;
}

/** A local calendar day of the learner (Europe/Kyiv) and an instant of it. */
function psDay(string $date): DateTimeImmutable
{
    return new DateTimeImmutable($date, new DateTimeZone('Europe/Kyiv'));
}

function psAt(string $local): DateTimeImmutable
{
    return (new DateTimeImmutable($local, new DateTimeZone('Europe/Kyiv')))->setTimezone(new DateTimeZone('UTC'));
}

// Наряд GEN-3 §11.1: «день N+1 доступен со следующего календарного дня после ОТКРЫТИЯ дня N, в часовом поясе ученика; открыл в 23:00,
// закрыл в 01:00 — следующий день доступен с 01:00». Catches the old rule — the day after CLOSING (day 2 pushed to the 19th) —
// and a date read in UTC instead of the learner's calendar (23:00 in Kyiv is still the 17th there, 20:00 UTC).
it('opens day 2 at 01:00 when day 1 was opened at 23:00 the evening before and closed at 01:00', function () {
    $plan = psPlan(3, '2026-09-17');
    $plan->start(psAt('2026-09-17 22:50'), psDay('2026-09-17'));
    $plan->openDay(1, psDay('2026-09-17'), psAt('2026-09-17 23:00'));
    $plan->closeDay(1, DayMetrics::empty(), psDay('2026-09-18'), psAt('2026-09-18 01:00'));

    expect($plan->day(2)->opensOn()?->format('Y-m-d'))->toBe('2026-09-18')
        ->and($plan->effectiveDayStatus($plan->day(2), psDay('2026-09-18')))->toBe(DayStatus::Open)
        ->and($plan->openDay(2, psDay('2026-09-18'), psAt('2026-09-18 01:00'))->status())->toBe(DayStatus::InProgress);
});

// Наряд GEN-3 §11.1: «режим „догоняем“: календарных дней до события (включая день события) ≤ непройденных дней — замка нет, следующий
// день открывается сразу после закрытия текущего; план отдаёт catch_up true»; F: «до события 3 дня, непройденных 3 → замка нет,
// catch_up true». Catches a learner three days before the event locked out of the day they still have to walk today, and a plan
// with room to spare that stops waiting for the calendar.
it('does not lock the next day while the days left until the event are no more than the days not passed', function () {
    $plan = psPlan(4, '2026-09-10', '2026-09-14');
    $plan->start(psAt('2026-09-10 09:00'), psDay('2026-09-10'));

    // On the 10th: five calendar days to the event (10…14), four days to walk — room to spare, the calendar rules.
    expect($plan->isCatchingUp(psDay('2026-09-10')))->toBeFalse();

    // On the 12th day 1 is walked; then three calendar days are left (12, 13, 14) and three days not passed — catching up.
    $plan->openDay(1, psDay('2026-09-12'), psAt('2026-09-12 10:00'));
    $plan->closeDay(1, DayMetrics::empty(), psDay('2026-09-12'), psAt('2026-09-12 10:30'));

    expect($plan->isCatchingUp(psDay('2026-09-12')))->toBeTrue()
        ->and($plan->day(2)->opensOn()?->format('Y-m-d'))->toBe('2026-09-13')
        ->and($plan->effectiveDayStatus($plan->day(2), psDay('2026-09-12')))->toBe(DayStatus::Open)
        ->and($plan->openDay(2, psDay('2026-09-12'), psAt('2026-09-12 10:31'))->status())->toBe(DayStatus::InProgress);
});

// Наряд GEN-3 §11.1: «план без даты события — замок обычный». Catches a plan with no event treated as always late.
it('locks the next day of a plan with no event date until the calendar day after the day before it was opened', function () {
    $plan = psPlan(3, '2026-09-10');
    $plan->start(psAt('2026-09-10 09:00'), psDay('2026-09-10'));
    $plan->openDay(1, psDay('2026-09-10'), psAt('2026-09-10 10:00'));
    $plan->closeDay(1, DayMetrics::empty(), psDay('2026-09-10'), psAt('2026-09-10 10:30'));

    expect($plan->isCatchingUp(psDay('2026-09-10')))->toBeFalse()
        ->and($plan->effectiveDayStatus($plan->day(2), psDay('2026-09-10')))->toBe(DayStatus::Locked)
        ->and(fn () => $plan->openDay(2, psDay('2026-09-10'), psAt('2026-09-10 10:31')))->toThrow(PlanDayLocked::class);
});

// Наряд GEN-3 §11.1: «день события — учебный: оставшиеся дни (в т. ч. репетиция) открываются и в этот день; после даты события — как
// сейчас». Catches a rehearsal locked until the day after the event it rehearses for, and a plan past its date that stops
// following the calendar.
it('opens the rehearsal on the event\'s own day, and after the event date the calendar rules again', function () {
    $plan = psPlan(3, '2026-09-10', '2026-09-14');
    $plan->start(psAt('2026-09-13 09:00'), psDay('2026-09-13'));
    $plan->openDay(1, psDay('2026-09-13'), psAt('2026-09-13 09:10'));
    $plan->closeDay(1, DayMetrics::empty(), psDay('2026-09-13'), psAt('2026-09-13 09:40'));
    $plan->openDay(2, psDay('2026-09-14'), psAt('2026-09-14 08:00'));
    $plan->closeDay(2, DayMetrics::empty(), psDay('2026-09-14'), psAt('2026-09-14 08:30'));

    expect($plan->day(3)->opensOn()?->format('Y-m-d'))->toBe('2026-09-15')
        ->and($plan->isCatchingUp(psDay('2026-09-14')))->toBeTrue()
        ->and($plan->openDay(3, psDay('2026-09-14'), psAt('2026-09-14 08:31'))->status())->toBe(DayStatus::InProgress)
        ->and($plan->isCatchingUp(psDay('2026-09-15')))->toBeFalse();
});

// Наряд GEN-3 §11.2: «будущий день без готового урока виден как building (не locked, не failed), без allowed_action; открыть → 409
// plan_day_building». Catches a day whose lesson is being written shown as locked (the learner waits for a date that has come)
// or opened into an empty day, and a day further on, whose lesson nobody asked for yet, shown as being built.
it('shows the day next in line as building while its lesson is not written, and refuses to open it', function () {
    $plan = psPlan(3, '2026-09-10', null, lessons: false);
    planWriteLessons($plan);
    $plan->sceneOf($plan->day(2))?->resetLesson();
    $plan->start(psAt('2026-09-10 09:00'), psDay('2026-09-10'));
    $plan->openDay(1, psDay('2026-09-10'), psAt('2026-09-10 10:00'));

    expect($plan->isDayBuilding($plan->day(2)))->toBeFalse();

    $plan->closeDay(1, DayMetrics::empty(), psDay('2026-09-10'), psAt('2026-09-10 10:30'));

    expect($plan->isDayBuilding($plan->day(2)))->toBeTrue()
        ->and(fn () => $plan->openDay(2, psDay('2026-09-11'), psAt('2026-09-11 09:00')))->toThrow(PlanDayBuilding::class)
        ->and($plan->isDayBuilding($plan->day(3)))->toBeFalse();
});

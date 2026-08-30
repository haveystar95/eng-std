<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Exception\EventDateInPast;
use App\Modules\Learning\Domain\Service\PlanScheduler;
use App\Modules\Learning\Domain\ValueObject\DaySpacing;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanOutline;

/**
 * A1 — the days are counted by the SERVER.
 *
 * Every scenario below is the same three inputs (an outline, the minutes, the calendar) and one
 * question: how many teaching days are there, what goes in them, and did everything fit. The five
 * named scenarios are the ones the наряд asks for; the numbers in each `expect` are written out in
 * the test name so a failure says which arithmetic broke, not just that something did.
 */
beforeEach(fn () => $this->scheduler = new PlanScheduler());

/**
 * An outline shaped like P1's answer. Days are given as [title, term_budget, [outcome, …]] and
 * every day gets a role with one checkpoint per outcome, because that is what P1 is required to
 * produce and what the scheduler carries into the day.
 */
function outline(array $days, string $finalTitle = 'Прогон'): PlanOutline
{
    $raw = ['title' => 'План', 'goal_restated' => 'Цель', 'entities' => [], 'constraints' => [],
        'goal_terms' => [], 'recommended_days' => null, 'final_day' => ['title' => $finalTitle], 'days' => []];

    foreach ($days as $i => [$title, $budget, $outcomes]) {
        $raw['days'][] = [
            'index' => $i + 1,
            'title' => $title,
            'term_budget' => $budget,
            'outcome' => $outcomes,
            'topics' => ['тема ' . ($i + 1)],
            'role' => [
                'name' => 'собеседник ' . ($i + 1),
                'opening_lines' => [['text' => 'Hello?', 'translation' => 'Здравствуйте?']],
                'checkpoints' => array_map(static fn (string $o): string => 'слышно: ' . $o, $outcomes),
                'if_silent' => 'переспрашивает проще',
            ],
        ];
    }

    return PlanOutline::fromArray($raw);
}

function day(string $ymd): DateTimeImmutable
{
    return new DateTimeImmutable($ymd . ' 00:00:00');
}

// ── capacity table ────────────────────────────────────────────────────────────────────────────

it('reads the capacity table the prompt is told: 10 → 5, 20 → 9, 40 → 16', function () {
    expect($this->scheduler->capacityFor(10))->toBe(5)
        ->and($this->scheduler->capacityFor(20))->toBe(9)
        ->and($this->scheduler->capacityFor(40))->toBe(16);
});

it('interpolates a figure between the anchors instead of refusing it', function () {
    // 30 minutes sits mid-segment: 9 + 10 × 0.35 = 12.5 → 13.
    expect($this->scheduler->capacityFor(30))->toBe(13)
        ->and($this->scheduler->capacityFor(15))->toBe(7);   // 5 + 5 × 0.4 = 7
});

it('never lets a day hold nothing', function () {
    expect($this->scheduler->capacityFor(1))->toBeGreaterThanOrEqual(1);
});

// ── scenario 1: событие сегодня ───────────────────────────────────────────────────────────────

it('S3 «сегодня везу кота» — event today: one day does both jobs, need 9 ≤ capacity 9, fits', function () {
    $plan = $this->scheduler->compute(
        outline([['Открыть визит и понять назначение', 9, ['объяснить, зачем пришёл', 'понять назначение и повторить своими словами']]]),
        minutesPerDay: 20,
        eventDate: day('2026-08-30'),
        today: day('2026-08-30'),
    );

    expect($plan->maxDays)->toBe(1)
        ->and($plan->capacity)->toBe(9)
        ->and($plan->need)->toBe(9)
        ->and($plan->introDays)->toBe(1)
        ->and($plan->restDays)->toBe(0)
        ->and($plan->fits)->toBeTrue()
        ->and($plan->finalSameDay)->toBeTrue()
        ->and($plan->dropped)->toBe([])
        ->and($plan->days)->toHaveCount(1)
        // INTRO: the day teaches, and `kind` is what decides whether it gets material at all.
        // That it is also the last day is said by `finalSameDay`.
        ->and($plan->days[0]->kind)->toBe(PlanDayKind::Intro)
        ->and($plan->days[0]->checkpoints)->toHaveCount(2)
        ->and($plan->days[0]->termBudget)->toBe(9)
        ->and($plan->days[0]->scheduledOn->format('Y-m-d'))->toBe('2026-08-30');
});

it('compresses a same-day plan instead of dropping half of it, and says it did not fit', function () {
    // Two abilities' worth of an 18-term day, one day to do it in: everything is kept and `fits`
    // is the honest «нет». Dropping would mean dropping in favour of a day that does not exist.
    $plan = $this->scheduler->compute(
        outline([['Всё сразу', 18, ['представиться', 'ответить на технические вопросы', 'вести разговор удалённо']]]),
        minutesPerDay: 20,
        eventDate: day('2026-08-30'),
        today: day('2026-08-30'),
    );

    expect($plan->need)->toBe(18)
        ->and($plan->capacity)->toBe(9)
        ->and($plan->fits)->toBeFalse()
        ->and($plan->dropped)->toBe([])
        ->and($plan->days[0]->skills)->toHaveCount(3);
});

// ── scenario 2: 2 дня / 40 минут / собеседование — не влезает ─────────────────────────────────

it('S2 «собеседование», 2 дня / 40 мин: need 18 > room 16, does not fit and names what is dropped', function () {
    // P1 is told the exact figure (40 minutes → 16) and this outline came back at 18 anyway —
    // which is the whole reason the server does the arithmetic rather than trusting it. 2 days
    // means exactly ONE teaching day, so the demand does not fit and the plan says so.
    $plan = $this->scheduler->compute(
        outline([['Пройти основные этапы интервью', 18, [
            'представиться и рассказать про опыт',
            'ответить на технические вопросы про проект',
            'вести разговор в удалённом формате',
        ]]]),
        minutesPerDay: 40,
        eventDate: day('2026-09-01'),
        today: day('2026-08-31'),
    );

    expect($plan->maxDays)->toBe(2)
        ->and($plan->capacity)->toBe(16)
        ->and($plan->need)->toBe(18)          // 6 + 6 + 6
        ->and($plan->introDays)->toBe(1)
        ->and($plan->fits)->toBeFalse()
        ->and($plan->dropped)->toHaveCount(1)
        ->and($plan->dropped[0]->outcome)->toBe('вести разговор в удалённом формате')
        // What is KEPT still fills the one teaching day; the final day is separate.
        ->and($plan->days)->toHaveCount(2)
        // The day ASKS FOR a full day's cards — capacity — even though the two abilities that
        // survived only account for 12. Demand decides what fits; the minutes decide the day.
        ->and($plan->days[0]->termBudget)->toBe(16)
        ->and($plan->days[1]->kind)->toBe(PlanDayKind::Final);
});

it('drops from the END, because P1 writes days in dependency order', function () {
    $plan = $this->scheduler->compute(
        outline([
            ['Основа', 9, ['поздороваться']],
            ['Дальше', 9, ['уточнить']],
            ['Ещё дальше', 9, ['возразить']],
        ]),
        minutesPerDay: 20,
        eventDate: day('2026-09-01'),
        today: day('2026-08-31'),
    );

    // Room = 9 × 1 = 9; the first ability costs 9 and fills it.
    expect($plan->fits)->toBeFalse()
        ->and(array_map(fn ($s) => $s->outcome, $plan->dropped))->toBe(['уточнить', 'возразить']);
});

// ── scenario 3: 3 дня / 20 минут / врач ───────────────────────────────────────────────────────

it('S1 «врач», 3 дня / 20 мин: need 18 = room 18, two teaching days back to back, fits', function () {
    $plan = $this->scheduler->compute(
        outline([
            ['Начать приём и описать боль', 9, ['начать приём', 'сказать, где именно болит и как давно']],
            ['Уточнить симптомы и помощь', 9, ['ответить на уточняющие вопросы', 'понять назначение и повторить своими словами']],
        ]),
        minutesPerDay: 20,
        eventDate: day('2026-09-02'),
        today: day('2026-08-31'),
    );

    expect($plan->maxDays)->toBe(3)
        ->and($plan->capacity)->toBe(9)
        ->and($plan->need)->toBe(18)
        ->and($plan->introDays)->toBe(2)
        ->and($plan->restDays)->toBe(0)
        ->and($plan->fits)->toBeTrue()
        ->and($plan->spacing)->toBe(DaySpacing::Daily)
        ->and($plan->days)->toHaveCount(3)
        ->and(array_map(fn ($d) => $d->scheduledOn->format('Y-m-d'), $plan->days))
        ->toBe(['2026-08-31', '2026-09-01', '2026-09-02'])
        ->and($plan->days[0]->title)->toBe('Начать приём и описать боль')
        ->and($plan->days[0]->termBudget)->toBe(9)
        ->and($plan->days[1]->termBudget)->toBe(9);
});

it('gives the final day every checkpoint of the plan, in day order, and no collection budget', function () {
    $plan = $this->scheduler->compute(
        outline([
            ['День 1', 9, ['A', 'B']],
            ['День 2', 9, ['C', 'D']],
        ]),
        minutesPerDay: 20,
        eventDate: day('2026-09-02'),
        today: day('2026-08-31'),
    );

    $final = $plan->days[2];

    expect($final->kind)->toBe(PlanDayKind::Final)
        ->and($final->termBudget)->toBe(0)
        ->and($final->skills)->toBe([])
        ->and($final->role)->toBeNull()
        ->and($final->checkpoints)->toBe(['слышно: A', 'слышно: B', 'слышно: C', 'слышно: D']);
});

// ── scenario 4: 7 дней ────────────────────────────────────────────────────────────────────────

it('7 дней / 20 мин, need 27: three teaching days, three free — every other day', function () {
    $plan = $this->scheduler->compute(
        outline([
            ['День 1', 9, ['A', 'B']],
            ['День 2', 9, ['C', 'D']],
            ['День 3', 9, ['E', 'F']],
        ]),
        minutesPerDay: 20,
        eventDate: day('2026-09-06'),
        today: day('2026-08-31'),
    );

    expect($plan->maxDays)->toBe(7)
        ->and($plan->need)->toBe(27)
        ->and($plan->introDays)->toBe(3)
        ->and($plan->restDays)->toBe(3)          // 6 teaching slots − 3 used
        ->and($plan->fits)->toBeTrue()
        ->and($plan->spacing)->toBe(DaySpacing::EveryOtherDay)
        ->and(array_map(fn ($d) => $d->scheduledOn->format('Y-m-d'), $plan->days))
        ->toBe(['2026-08-31', '2026-09-02', '2026-09-04', '2026-09-06']);
});

it('runs teaching days back to back when the slack is smaller than the teaching', function () {
    // 5 days → 4 teaching slots, 3 used, 1 spare: 1 < 3, so no room to breathe.
    $plan = $this->scheduler->compute(
        outline([['День 1', 9, ['A', 'B']], ['День 2', 9, ['C', 'D']], ['День 3', 9, ['E', 'F']]]),
        minutesPerDay: 20,
        eventDate: day('2026-09-04'),
        today: day('2026-08-31'),
    );

    expect($plan->introDays)->toBe(3)
        ->and($plan->restDays)->toBe(1)
        ->and($plan->spacing)->toBe(DaySpacing::Daily)
        ->and(array_map(fn ($d) => $d->scheduledOn->format('Y-m-d'), $plan->days))
        ->toBe(['2026-08-31', '2026-09-01', '2026-09-02', '2026-09-04']);
});

// ── scenario 5: 30 дней ───────────────────────────────────────────────────────────────────────

it('30 дней / 40 мин, need 48: three teaching days spread out, 26 days of slack, everything fits', function () {
    $plan = $this->scheduler->compute(
        outline([
            ['День 1', 16, ['A', 'B']],
            ['День 2', 16, ['C', 'D']],
            ['День 3', 16, ['E', 'F']],
        ]),
        minutesPerDay: 40,
        eventDate: day('2026-09-29'),
        today: day('2026-08-31'),
    );

    expect($plan->maxDays)->toBe(30)
        ->and($plan->capacity)->toBe(16)
        ->and($plan->need)->toBe(48)
        ->and($plan->introDays)->toBe(3)
        ->and($plan->restDays)->toBe(26)
        ->and($plan->fits)->toBeTrue()
        ->and($plan->spacing)->toBe(DaySpacing::EveryOtherDay)
        // A long plan does NOT stretch to fill the calendar: the teaching happens now and the
        // event is where it is. Inventing 26 days of «повторение» is a product decision nobody
        // has made, and this class does not make it silently.
        ->and(array_map(fn ($d) => $d->scheduledOn->format('Y-m-d'), $plan->days))
        ->toBe(['2026-08-31', '2026-09-02', '2026-09-04', '2026-09-29']);
});

// ── the calendar ──────────────────────────────────────────────────────────────────────────────

it('refuses a past event date instead of clamping it to today', function () {
    $this->scheduler->compute(
        outline([['День 1', 9, ['A']]]),
        minutesPerDay: 20,
        eventDate: day('2026-08-29'),
        today: day('2026-08-31'),
    );
})->throws(EventDateInPast::class);

it('ignores the time of day on both ends', function () {
    $plan = $this->scheduler->compute(
        outline([['День 1', 9, ['A']]]),
        minutesPerDay: 20,
        eventDate: new DateTimeImmutable('2026-09-01 03:00:00'),
        today: new DateTimeImmutable('2026-08-31 23:30:00'),
    );

    expect($plan->maxDays)->toBe(2);
});

// ── packing ───────────────────────────────────────────────────────────────────────────────────

it('shares a day budget over its abilities without inflating the total', function () {
    // 16 over three abilities is 6 + 5 + 5, not 6 + 6 + 6. Rounding each one up would say the day
    // needs 18 and would report a plan that fits as one that does not.
    $skills = outline([['День', 16, ['A', 'B', 'C']]])->skills();

    expect(array_map(fn ($s) => $s->estTerms, $skills))->toBe([6, 5, 5])
        ->and(array_sum(array_map(fn ($s) => $s->estTerms, $skills)))->toBe(16);
});

it('packs by cumulative position, so three 5-term abilities make two days and not three', function () {
    $plan = $this->scheduler->compute(
        outline([['День', 15, ['A', 'B', 'C']]]),   // 5 + 5 + 5
        minutesPerDay: 20,                          // capacity 9
        eventDate: day('2026-09-02'),
        today: day('2026-08-31'),
    );

    expect($plan->introDays)->toBe(2)
        ->and($plan->days[0]->skills)->toHaveCount(2)
        // Two abilities worth 10 landed here and the day still asks for exactly 9 — the number of
        // cards that fit in 20 minutes. The overflow is in the DEMAND, which is what `fits` is
        // about; it is not something the day is allowed to buy its way out of.
        ->and($plan->days[0]->termBudget)->toBe(9)
        ->and($plan->days[1]->skills)->toHaveCount(1)
        ->and($plan->days[1]->termBudget)->toBe(9);
});

it('names a merged day after its main ability, since no outline day title covers it', function () {
    $plan = $this->scheduler->compute(
        outline([['Первый', 4, ['A']], ['Второй', 4, ['B']]]),
        minutesPerDay: 20,                          // capacity 9 — both abilities land on one day
        eventDate: day('2026-09-01'),
        today: day('2026-08-31'),
    );

    expect($plan->introDays)->toBe(1)
        ->and($plan->days[0]->sourceDayIndex)->toBeNull()
        ->and($plan->days[0]->title)->toBe('A');
});

it('gives a merged day ONE conversation — the one its first ability came from', function () {
    $plan = $this->scheduler->compute(
        outline([['Первый', 4, ['A']], ['Второй', 4, ['B']]]),
        minutesPerDay: 20,
        eventDate: day('2026-09-01'),
        today: day('2026-08-31'),
    );

    expect($plan->days[0]->role?->name)->toBe('собеседник 1')
        // …but the checkpoints are the merged day's own, in its own order.
        ->and($plan->days[0]->role?->checkpoints)->toBe(['слышно: A', 'слышно: B']);
});

it('computes the day phrase/word split as ceil(0.45 × budget)', function () {
    $plan = $this->scheduler->compute(
        outline([['День', 9, ['A', 'B']]]),
        minutesPerDay: 20,
        eventDate: day('2026-09-01'),
        today: day('2026-08-31'),
    );

    expect($plan->days[0]->phraseCount())->toBe(5)
        ->and($plan->days[0]->wordCount())->toBe(4);
});

// ── A7 ────────────────────────────────────────────────────────────────────────────────────────

it('A7 flags a tight deadline and names what is at risk, without cutting anything', function () {
    $plan = $this->scheduler->compute(
        outline([['День 1', 9, ['A', 'B']], ['День 2', 9, ['C', 'D']], ['День 3', 9, ['E', 'F']]]),
        minutesPerDay: 20,
        eventDate: day('2026-09-06'),
        today: day('2026-08-31'),
    );

    // Four days later, nothing done, event unchanged: two days left, one of them the final one.
    $check = $this->scheduler->recheck(
        remainingIntroDays: array_slice($plan->days, 0, 3),
        minutesPerDay: 20,
        eventDate: day('2026-09-06'),
        today: day('2026-09-05'),
    );

    expect($check->daysRemaining)->toBe(2)
        ->and($check->introDaysRemaining)->toBe(1)
        ->and($check->needRemaining)->toBe(27)
        ->and($check->capacity)->toBe(9)
        ->and($check->deadlineTight)->toBeTrue()
        ->and(array_map(fn ($s) => $s->outcome, $check->atRisk))->toBe(['C', 'D', 'E', 'F']);
});

it('A7 says nothing is tight while the plan is on schedule', function () {
    $plan = $this->scheduler->compute(
        outline([['День 1', 9, ['A', 'B']], ['День 2', 9, ['C', 'D']]]),
        minutesPerDay: 20,
        eventDate: day('2026-09-06'),
        today: day('2026-08-31'),
    );

    $check = $this->scheduler->recheck(
        remainingIntroDays: [$plan->days[1]],
        minutesPerDay: 20,
        eventDate: day('2026-09-06'),
        today: day('2026-09-01'),
    );

    expect($check->deadlineTight)->toBeFalse()
        ->and($check->atRisk)->toBe([]);
});

it('asks every teaching day for exactly capacity, whatever landed on it', function () {
    // The day's LENGTH is the learner's choice of minutes, not an arithmetic leftover. A day that
    // asked for the sum of its abilities would be a different length on Tuesday than on Monday for
    // no reason the learner chose — and would hand the validator a card count P1 picked.
    $plan = $this->scheduler->compute(
        outline([['День 1', 9, ['A', 'B']], ['День 2', 4, ['C']]]),
        minutesPerDay: 20,
        eventDate: day('2026-09-02'),
        today: day('2026-08-31'),
    );

    expect($plan->capacity)->toBe(9)
        ->and(array_map(fn ($d) => $d->termBudget, $plan->days))->toBe([9, 9, 0])
        // …and the phrase/word split follows the budget, so the model is handed 5 + 4 both days.
        ->and($plan->days[0]->phraseCount())->toBe(5)
        ->and($plan->days[1]->phraseCount())->toBe(5);
});

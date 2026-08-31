<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Exception\EventDateInPast;
use App\Modules\Learning\Domain\Service\PlanScheduler;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanOutline;

/**
 * A1 — the days are counted by the SERVER.
 *
 * Every scenario below is the same three inputs (a skeleton, the minutes, the calendar) and one
 * question: how many teaching days are there, what goes in them, and did everything fit. The
 * numbers in each `expect` are written out in the test name so a failure says which arithmetic
 * broke, not just that something did.
 *
 * ## What v0.2 changed here, and why the numbers all moved
 *
 * Two things at once. The capacity table went from 5 / 9 / 16 to **7 / 14 / 24** — a v0.2 day is
 * frames with a slot and the words that fill them, so twenty minutes carries more cards than
 * twenty minutes of memorised replies did. And `est_terms` is now the MODEL's price per ability
 * instead of the server's own day budget divided up and handed back to itself, which is why
 * «не влезает» can happen at all: under v0.1 the sum being compared was the server's own input.
 */
beforeEach(fn () => $this->scheduler = new PlanScheduler());

/**
 * A skeleton written as `[scene title, what the scene costs, [ability, …]]`.
 *
 * The cost is shared over the scene's abilities the way any whole number divides — remainder to
 * the first ones — so a scene of 14 over two abilities is 7 + 7 and one of 9 over two is 5 + 4.
 * That keeps a scenario readable as «this situation is worth about a day» while every ability
 * still carries its own price, which is what the scheduler actually reads.
 */
function outline(array $scenes): PlanOutline
{
    $raw = ['title' => 'План', 'goal_restated' => 'Цель', 'entities' => [], 'constraints' => [],
        'goal_terms' => [], 'scenes' => []];

    foreach ($scenes as $i => [$title, $cost, $outcomes]) {
        $base = intdiv($cost, count($outcomes));
        $remainder = $cost % count($outcomes);

        $skills = [];
        foreach ($outcomes as $n => $outcome) {
            $skills[] = [
                'outcome' => $outcome,
                'checkpoint' => 'слышно: ' . $outcome,
                'est_terms' => max(1, $base + ($n < $remainder ? 1 : 0)),
                'topics' => ['тема ' . ($i + 1)],
            ];
        }

        $raw['scenes'][] = [
            'title' => $title,
            'role' => [
                'name' => 'собеседник ' . ($i + 1),
                'opening_lines' => [['text' => 'Hello?', 'translation' => 'Здравствуйте?']],
                'if_silent' => 'переспрашивает проще',
            ],
            'skills' => $skills,
        ];
    }

    return PlanOutline::fromArray($raw);
}

/** One of the three hand-written v0.2 skeletons the plan is designed around. */
function fixtureOutline(string $name): PlanOutline
{
    /** @var array<mixed> $raw */
    $raw = json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/plan/' . $name), true);

    return PlanOutline::fromArray($raw);
}

function day(string $ymd): DateTimeImmutable
{
    return new DateTimeImmutable($ymd . ' 00:00:00');
}

// ── capacity table ────────────────────────────────────────────────────────────────────────────

it('reads the one capacity table there is: 10 → 7, 20 → 14, 40 → 24', function () {
    expect($this->scheduler->capacityFor(10))->toBe(7)
        ->and($this->scheduler->capacityFor(20))->toBe(14)
        ->and($this->scheduler->capacityFor(40))->toBe(24);
});

it('interpolates a figure between the anchors instead of refusing it', function () {
    // 30 minutes sits mid-segment: 14 + 10 × 0.5 = 19. 15 minutes: 7 + 5 × 0.7 = 10.5 → 11.
    expect($this->scheduler->capacityFor(30))->toBe(19)
        ->and($this->scheduler->capacityFor(15))->toBe(11);
});

it('never lets a day hold nothing', function () {
    expect($this->scheduler->capacityFor(1))->toBeGreaterThanOrEqual(1);
});

// ── the наряд's scenarios, on the real skeletons ──────────────────────────────────────────────

it('S1 «врач через 30 дней, 20 мин»: TWO days of teaching, not twenty-nine', function () {
    // THE test this whole rewrite exists for. Under v0.1 the model was told it had 30 days, wrote
    // 29 of them, and the server «computed» that it needed 29 — its own instruction, read back.
    // The skeleton now prices four abilities at 4 cards each; sixteen cards at fourteen a day is
    // two days, and the remaining month is the learner's to review in.
    $plan = $this->scheduler->compute(
        fixtureOutline('s1-outline.v0.2.json'),
        minutesPerDay: 20,
        eventDate: day('2026-09-29'),
        today: day('2026-08-31'),
    );

    expect($plan->need)->toBe(16)
        ->and($plan->capacity)->toBe(14)
        ->and($plan->introDays)->toBe(2)
        ->and($plan->fits)->toBeTrue()
        ->and($plan->dropped)->toBe([])
        ->and($plan->restDays)->toBe(27);
});

it('S1 «врач через 3 дня»: still two days of teaching, and it still fits', function () {
    $plan = $this->scheduler->compute(
        fixtureOutline('s1-outline.v0.2.json'),
        minutesPerDay: 20,
        eventDate: day('2026-09-02'),
        today: day('2026-08-31'),
    );

    expect($plan->maxDays)->toBe(3)
        ->and($plan->introDays)->toBe(2)
        ->and($plan->fits)->toBeTrue()
        ->and($plan->step)->toBe(1)
        ->and(array_map(fn ($d) => $d->scheduledOn->format('Y-m-d'), $plan->days))
        ->toBe(['2026-08-31', '2026-09-01', '2026-09-02']);
});

it('S2 «собеседование через 2 дня, 40 мин»: the deadline is too short and the TAIL is cut', function () {
    // Two days means exactly one teaching day: 24 cards for a goal that asks for 30. The abilities
    // are taken in P1's order until the room runs out, so what the learner loses is the last thing
    // the plan would have taught, not the cheapest.
    $plan = $this->scheduler->compute(
        fixtureOutline('s2-outline.v0.2.json'),
        minutesPerDay: 40,
        eventDate: day('2026-09-01'),
        today: day('2026-08-31'),
    );

    expect($plan->capacity)->toBe(24)
        ->and($plan->need)->toBe(30)
        ->and($plan->introDays)->toBe(1)
        ->and($plan->fits)->toBeFalse()
        ->and($plan->dropReason)->toBe('deadline')
        ->and($plan->dropped)->toHaveCount(1)
        // The «срок мал» card is built from these outcomes, so it says what was lost in the
        // learner's own words rather than «одно умение».
        ->and($plan->dropped[0]->outcome)
        ->toBe('понять уточняющий вопрос и переспросить своими словами, если не уверен');
});

it('S2 A7 says the same thing mid-plan: tight, and here is what is at risk', function () {
    $check = $this->scheduler->recheck(
        remainingIntroDays: $this->scheduler->compute(
            fixtureOutline('s2-outline.v0.2.json'),
            minutesPerDay: 40,
            eventDate: day('2026-09-10'),
            today: day('2026-08-31'),
        )->days,
        minutesPerDay: 40,
        eventDate: day('2026-09-01'),
        today: day('2026-08-31'),
    );

    // The same arithmetic as `compute()` above, asked mid-plan: one teaching day left, 24 cards of
    // room, 30 of demand. Nothing is CUT here — A7 reports, and the decision is the learner's.
    expect($check->deadlineTight)->toBeTrue()
        ->and($check->needRemaining)->toBe(30)
        ->and($check->introDaysRemaining)->toBe(1)
        ->and($check->atRisk)->toHaveCount(1);
});

it('S3 «сегодня везу кота»: one day does both jobs and everything is in it', function () {
    $plan = $this->scheduler->compute(
        fixtureOutline('s3-outline.v0.2.json'),
        minutesPerDay: 20,
        eventDate: day('2026-08-31'),
        today: day('2026-08-31'),
    );

    expect($plan->maxDays)->toBe(1)
        ->and($plan->need)->toBe(12)
        ->and($plan->capacity)->toBe(14)
        ->and($plan->introDays)->toBe(1)
        ->and($plan->fits)->toBeTrue()
        ->and($plan->finalSameDay)->toBeTrue()
        ->and($plan->dropped)->toBe([])
        ->and($plan->days)->toHaveCount(1)
        // INTRO: the day teaches, and `kind` is what decides whether it gets material at all.
        // That it is also the last day is said by `finalSameDay`.
        ->and($plan->days[0]->kind)->toBe(PlanDayKind::Intro)
        ->and($plan->days[0]->skills)->toHaveCount(3)
        ->and($plan->days[0]->checkpoints)->toHaveCount(3)
        ->and($plan->days[0]->termBudget)->toBe(14);
});

it('caps a plan at 14 teaching days when the demand is 250 cards, whatever the calendar offers', function () {
    // A year of room and fifty abilities at five cards each. The calendar is NOT what ran out, so
    // «перенеси дату» is the wrong advice and `drop_reason` says so.
    $plan = $this->scheduler->compute(
        collections(50, 5),
        minutesPerDay: 20,
        eventDate: day('2027-08-31'),
        today: day('2026-08-31'),
    );

    expect($plan->need)->toBe(250)
        ->and($plan->introDays)->toBe(PlanScheduler::MAX_INTRO_DAYS)
        ->and($plan->introDays)->toBe(14)
        ->and($plan->fits)->toBeFalse()
        ->and($plan->dropReason)->toBe('cap');
});

// ── the same-day plan ─────────────────────────────────────────────────────────────────────────

it('compresses a same-day plan instead of dropping half of it, and says it did not fit', function () {
    // Three abilities' worth of an 18-card demand, one day to do it in: everything is kept and
    // `fits` is the honest «нет». Dropping would mean dropping in favour of a day that does not exist.
    $plan = $this->scheduler->compute(
        outline([['Всё сразу', 18, ['представиться', 'ответить на технические вопросы', 'вести разговор удалённо']]]),
        minutesPerDay: 20,
        eventDate: day('2026-08-30'),
        today: day('2026-08-30'),
    );

    expect($plan->need)->toBe(18)
        ->and($plan->capacity)->toBe(14)
        ->and($plan->fits)->toBeFalse()
        ->and($plan->dropped)->toBe([])
        ->and($plan->days[0]->skills)->toHaveCount(3);
});

// ── dropping ──────────────────────────────────────────────────────────────────────────────────

it('drops from the END, because P1 writes scenes and abilities in dependency order', function () {
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

    // Room = 14 × 1 = 14; the first ability costs 9 and the second would take it to 18.
    expect($plan->fits)->toBeFalse()
        ->and(array_map(fn ($s) => $s->outcome, $plan->dropped))->toBe(['уточнить', 'возразить']);
});

it('gives the final day every checkpoint of the plan, in order, and no collection budget', function () {
    $plan = $this->scheduler->compute(
        outline([
            ['Сцена 1', 14, ['A', 'B']],
            ['Сцена 2', 14, ['C', 'D']],
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

// ── the calendar and the step ─────────────────────────────────────────────────────────────────

it('7 дней / 20 мин, need 27: two teaching days and four free, spread three apart', function () {
    $plan = $this->scheduler->compute(
        outline([
            ['Сцена 1', 9, ['A', 'B']],
            ['Сцена 2', 9, ['C', 'D']],
            ['Сцена 3', 9, ['E', 'F']],
        ]),
        minutesPerDay: 20,
        eventDate: day('2026-09-06'),
        today: day('2026-08-31'),
    );

    expect($plan->maxDays)->toBe(7)
        ->and($plan->need)->toBe(27)
        ->and($plan->introDays)->toBe(2)
        ->and($plan->restDays)->toBe(4)
        ->and($plan->fits)->toBeTrue()
        ->and($plan->step)->toBe(3)
        ->and(array_map(fn ($d) => $d->scheduledOn->format('Y-m-d'), $plan->days))
        ->toBe(['2026-08-31', '2026-09-03', '2026-09-06']);
});

it('runs teaching days back to back when the slack is smaller than the teaching', function () {
    // 5 days → 4 teaching slots, 3 used, 1 spare: 1 < 3, so no room to breathe.
    $plan = $this->scheduler->compute(
        outline([['Сцена 1', 14, ['A', 'B']], ['Сцена 2', 14, ['C', 'D']], ['Сцена 3', 14, ['E', 'F']]]),
        minutesPerDay: 20,
        eventDate: day('2026-09-04'),
        today: day('2026-08-31'),
    );

    expect($plan->introDays)->toBe(3)
        ->and($plan->restDays)->toBe(1)
        ->and($plan->step)->toBe(1)
        ->and(array_map(fn ($d) => $d->scheduledOn->format('Y-m-d'), $plan->days))
        ->toBe(['2026-08-31', '2026-09-01', '2026-09-02', '2026-09-04']);
});

it('30 дней / 40 мин, need 72: three teaching days spread out, 26 days of slack, everything fits', function () {
    $plan = $this->scheduler->compute(
        outline([
            ['Сцена 1', 24, ['A', 'B']],
            ['Сцена 2', 24, ['C', 'D']],
            ['Сцена 3', 24, ['E', 'F']],
        ]),
        minutesPerDay: 40,
        eventDate: day('2026-09-29'),
        today: day('2026-08-31'),
    );

    expect($plan->maxDays)->toBe(30)
        ->and($plan->capacity)->toBe(24)
        ->and($plan->need)->toBe(72)
        ->and($plan->introDays)->toBe(3)
        ->and($plan->restDays)->toBe(26)
        ->and($plan->fits)->toBeTrue()
        // 29 teaching days over 3 introduction days is 9 — clamped to the ceiling of 3, because a
        // word left alone for nine days was not taught, it was mentioned.
        ->and($plan->step)->toBe(3)
        // A long plan does NOT stretch to fill the calendar: the teaching is spread as wide as the
        // step allows and the event is where it is. Inventing 26 days of «повторение» is a product
        // decision nobody has made, and this class does not make it silently.
        ->and(array_map(fn ($d) => $d->scheduledOn->format('Y-m-d'), $plan->days))
        ->toBe(['2026-08-31', '2026-09-03', '2026-09-06', '2026-09-29']);
});

it('refuses a past event date instead of clamping it to today', function () {
    $this->scheduler->compute(
        outline([['Сцена', 9, ['A']]]),
        minutesPerDay: 20,
        eventDate: day('2026-08-29'),
        today: day('2026-08-31'),
    );
})->throws(EventDateInPast::class);

it('ignores the time of day on both ends', function () {
    $plan = $this->scheduler->compute(
        outline([['Сцена', 9, ['A']]]),
        minutesPerDay: 20,
        eventDate: new DateTimeImmutable('2026-09-01 03:00:00'),
        today: new DateTimeImmutable('2026-08-31 23:30:00'),
    );

    expect($plan->maxDays)->toBe(2);
});

// ── packing ───────────────────────────────────────────────────────────────────────────────────

it('packs by cumulative position, so three 5-card abilities make two days and not three', function () {
    $plan = $this->scheduler->compute(
        outline([['Сцена', 15, ['A', 'B', 'C']]]),   // 5 + 5 + 5
        minutesPerDay: 20,                           // capacity 14
        eventDate: day('2026-09-02'),
        today: day('2026-08-31'),
    );

    expect($plan->introDays)->toBe(2)
        ->and($plan->days[0]->skills)->toHaveCount(2)
        // Ten cards' worth of abilities landed here and the day still asks for exactly 14 — the
        // number of cards that fit in 20 minutes. The budget is the learner's minutes, not an
        // arithmetic leftover.
        ->and($plan->days[0]->termBudget)->toBe(14)
        ->and($plan->days[1]->skills)->toHaveCount(1)
        ->and($plan->days[1]->termBudget)->toBe(14);
});

it('never leaves a teaching day with nothing on it', function () {
    // Two abilities over two days: the packer would put both on day 1 by cumulative position and
    // leave day 2 empty, which is a day the learner opens onto nothing.
    $plan = $this->scheduler->compute(
        outline([['Сцена 1', 5, ['A']], ['Сцена 2', 5, ['B']]]),
        minutesPerDay: 10,               // capacity 7 — need 10, so two days by ceil
        eventDate: day('2026-09-02'),
        today: day('2026-08-31'),
    );

    expect($plan->introDays)->toBe(2)
        ->and($plan->days[0]->skills)->toHaveCount(1)
        ->and($plan->days[1]->skills)->toHaveCount(1);
});

it('names a day after the scene it came from, and a merged day after both', function () {
    $plan = $this->scheduler->compute(
        outline([['Первый', 4, ['A']], ['Второй', 4, ['B']]]),
        minutesPerDay: 20,                          // capacity 14 — both abilities land on one day
        eventDate: day('2026-09-01'),
        today: day('2026-08-31'),
    );

    expect($plan->introDays)->toBe(1)
        ->and($plan->days[0]->sourceSceneIndex)->toBeNull()
        // «A · B» and not «A»: the learner reads this line to decide whether to open the day, and
        // naming it after the first ability hides half of what the day does.
        ->and($plan->days[0]->title)->toBe('Первый · Второй');
});

it('gives a merged day ONE conversation on the day screen, and BOTH scenes in the brief', function () {
    $plan = $this->scheduler->compute(
        outline([['Первый', 4, ['A']], ['Второй', 4, ['B']]]),
        minutesPerDay: 20,
        eventDate: day('2026-09-01'),
        today: day('2026-08-31'),
    );

    expect($plan->days[0]->role?->name)->toBe('собеседник 1')
        // …and the checkpoints are the DAY's own, in its own order. They moved off the role with
        // v0.2 — a checkpoint belongs to the ability it proves, so a scene with no interlocutor
        // stopped being a scene whose promises nothing checks.
        ->and($plan->days[0]->checkpoints)->toBe(['слышно: A', 'слышно: B'])
        // The brief keeps them apart, because they ARE two conversations with two people.
        ->and($plan->days[0]->scenes)->toHaveCount(2)
        ->and($plan->days[0]->scenes[1]['role']['name'])->toBe('собеседник 2');
});

it('numbers the day`s checkpoints 1..N straight through, in ability order', function () {
    $plan = $this->scheduler->compute(
        outline([['Первый', 4, ['A']], ['Второй', 8, ['B', 'C']]]),
        minutesPerDay: 20,
        eventDate: day('2026-09-01'),
        today: day('2026-08-31'),
    );

    $json = $plan->days[0]->dayJson();

    expect(array_keys($json))->toBe(['index', 'title', 'scenes', 'checkpoints'])
        ->and($json['checkpoints'])->toBe(['слышно: A', 'слышно: B', 'слышно: C'])
        ->and($json['scenes'][0]['skills'][0]['checkpoint_index'])->toBe(1)
        ->and($json['scenes'][1]['skills'][0]['checkpoint_index'])->toBe(2)
        ->and($json['scenes'][1]['skills'][1]['checkpoint_index'])->toBe(3);
});

it('splits one scene across two days and gives each day only its own abilities', function () {
    $plan = $this->scheduler->compute(
        outline([['Длинная сцена', 20, ['A', 'B', 'C', 'D']]]),   // 5 each, capacity 14
        minutesPerDay: 20,
        eventDate: day('2026-09-02'),
        today: day('2026-08-31'),
    );

    expect($plan->introDays)->toBe(2)
        // Both days carry the same scene, with the same person — and each one only the abilities
        // that landed on it. Numbering them «часть 1 / часть 2» would invent a distinction the
        // plan does not make.
        ->and($plan->days[0]->title)->toBe('Длинная сцена')
        ->and($plan->days[1]->title)->toBe('Длинная сцена')
        ->and($plan->days[0]->scenes)->toHaveCount(1)
        ->and($plan->days[1]->scenes)->toHaveCount(1)
        ->and($plan->days[0]->scenes[0]['skills'])->toHaveCount(3)
        ->and($plan->days[1]->scenes[0]['skills'])->toHaveCount(1)
        // …and each day numbers its OWN checkpoints from 1, because «N» is a property of the day.
        ->and($plan->days[1]->scenes[0]['skills'][0]['checkpoint_index'])->toBe(1);
});

it('asks every teaching day for exactly capacity, whatever landed on it', function () {
    // The day's LENGTH is the learner's choice of minutes, not an arithmetic leftover. A day that
    // asked for the sum of its abilities would be a different length on Tuesday than on Monday for
    // no reason the learner chose — and would hand the validator a card count P1 picked.
    $plan = $this->scheduler->compute(
        outline([['Сцена 1', 14, ['A', 'B']], ['Сцена 2', 4, ['C']]]),
        minutesPerDay: 20,
        eventDate: day('2026-09-02'),
        today: day('2026-08-31'),
    );

    expect($plan->capacity)->toBe(14)
        ->and(array_map(fn ($d) => $d->termBudget, $plan->days))->toBe([14, 14, 0]);
});

// ── A7 ────────────────────────────────────────────────────────────────────────────────────────

it('A7 flags a tight deadline and names what is at risk, without cutting anything', function () {
    $plan = $this->scheduler->compute(
        outline([['Сцена 1', 9, ['A', 'B']], ['Сцена 2', 9, ['C', 'D']], ['Сцена 3', 9, ['E', 'F']]]),
        minutesPerDay: 20,
        eventDate: day('2026-09-06'),
        today: day('2026-08-31'),
    );

    // Five days later, nothing done, event unchanged: two days left, one of them the final one.
    $check = $this->scheduler->recheck(
        remainingIntroDays: $plan->days,
        minutesPerDay: 20,
        eventDate: day('2026-09-06'),
        today: day('2026-09-05'),
    );

    expect($check->daysRemaining)->toBe(2)
        ->and($check->introDaysRemaining)->toBe(1)
        ->and($check->needRemaining)->toBe(27)
        ->and($check->capacity)->toBe(14)
        ->and($check->deadlineTight)->toBeTrue()
        ->and(array_map(fn ($s) => $s->outcome, $check->atRisk))->toBe(['D', 'E', 'F']);
});

it('A7 says nothing is tight while the plan is on schedule', function () {
    $plan = $this->scheduler->compute(
        outline([['Сцена 1', 14, ['A', 'B']], ['Сцена 2', 14, ['C', 'D']]]),
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

// ── the step and the cap (PLAN-1b Ч.1) ────────────────────────────────────────────────────────

/** `$n` introduction days' worth of demand: one ability per scene, priced at a full day each. */
function collections(int $n, int $cost = 14): PlanOutline
{
    $scenes = [];
    for ($i = 1; $i <= $n; $i++) {
        $scenes[] = ['Коллекция ' . $i, $cost, ['умение ' . $i]];
    }

    return outline($scenes);
}

it('30 дней / 10 коллекций: step 3 — the teaching is spread, not stacked into the first week', function () {
    $plan = $this->scheduler->compute(
        collections(10),
        minutesPerDay: 20,
        eventDate: day('2026-09-30'),
        today: day('2026-08-31'),
    );

    expect($plan->maxDays)->toBe(31)
        ->and($plan->introDays)->toBe(10)
        ->and($plan->step)->toBe(3)
        ->and($plan->fits)->toBeTrue()
        ->and($plan->dropReason)->toBeNull()
        ->and(array_map(fn ($d) => $d->scheduledOn->format('Y-m-d'), $plan->days))
        ->toBe([
            '2026-08-31', '2026-09-03', '2026-09-06', '2026-09-09', '2026-09-12',
            '2026-09-15', '2026-09-18', '2026-09-21', '2026-09-24', '2026-09-27',
            '2026-09-30',   // the final day, on the event
        ]);
});

it('5 дней / 4 коллекции: step 1 — no room to spread, so the days run back to back', function () {
    $plan = $this->scheduler->compute(
        collections(4),
        minutesPerDay: 20,
        eventDate: day('2026-09-05'),
        today: day('2026-08-31'),
    );

    expect($plan->maxDays)->toBe(6)
        ->and($plan->introDays)->toBe(4)
        ->and($plan->step)->toBe(1)          // floor(5 / 4)
        ->and($plan->fits)->toBeTrue()
        ->and(array_map(fn ($d) => $d->scheduledOn->format('Y-m-d'), $plan->days))
        ->toBe(['2026-08-31', '2026-09-01', '2026-09-02', '2026-09-03', '2026-09-05']);
});

it('7 дней / 2 коллекции: step 3 — the ceiling holds even though the room would allow more', function () {
    $plan = $this->scheduler->compute(
        collections(2),
        minutesPerDay: 20,
        eventDate: day('2026-09-07'),
        today: day('2026-08-31'),
    );

    expect($plan->maxDays)->toBe(8)
        ->and($plan->introDays)->toBe(2)
        ->and($plan->step)->toBe(3)
        ->and(array_map(fn ($d) => $d->scheduledOn->format('Y-m-d'), $plan->days))
        ->toBe(['2026-08-31', '2026-09-03', '2026-09-07']);
});

it('caps a 40-collection plan at 14 introduction days and drops the rest as `cap`', function () {
    $plan = $this->scheduler->compute(
        collections(40),
        minutesPerDay: 20,
        eventDate: day('2027-08-31'),
        today: day('2026-08-31'),
    );

    expect($plan->need)->toBe(560)
        ->and($plan->introDays)->toBe(14)
        ->and($plan->fits)->toBeFalse()
        ->and($plan->dropReason)->toBe('cap')
        ->and($plan->dropped)->toHaveCount(26)
        ->and($plan->dropped[0]->outcome)->toBe('умение 15')
        // 14 teaching days + the final one.
        ->and($plan->days)->toHaveCount(15)
        ->and($plan->step)->toBe(3);
});

it('blames the DEADLINE, not the cap, when the calendar is what ran out', function () {
    $plan = $this->scheduler->compute(
        collections(5),
        minutesPerDay: 20,
        eventDate: day('2026-09-03'),   // 3 teaching days for 5 days of demand
        today: day('2026-08-31'),
    );

    expect($plan->fits)->toBeFalse()
        ->and($plan->dropReason)->toBe('deadline')
        ->and($plan->dropped)->toHaveCount(2);
});

it('A7 re-checks against the cap as well as against the calendar', function () {
    $plan = $this->scheduler->compute(
        collections(40),
        minutesPerDay: 20,
        eventDate: day('2027-08-31'),
        today: day('2026-08-31'),
    );

    // A year still to go, and the fourteen days that survived the cap are all still ahead.
    $check = $this->scheduler->recheck(
        remainingIntroDays: array_slice($plan->days, 0, 14),
        minutesPerDay: 20,
        eventDate: day('2027-08-31'),
        today: day('2026-08-31'),
    );

    expect($check->introDaysRemaining)->toBe(14)
        ->and($check->needRemaining)->toBe(196)
        ->and($check->deadlineTight)->toBeFalse();
});

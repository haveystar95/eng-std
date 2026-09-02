<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Exception\EventDateInPast;
use App\Modules\Learning\Domain\Service\PlanScheduler;
use App\Modules\Learning\Domain\Service\SceneDay;
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
 * ## What v0.4 changed here, and why the packing tests are gone
 *
 * ONE DAY IS ONE SCENE (канон §2). The days are no longer `ceil(need / capacity)` with abilities
 * packed into them by cumulative position — they are the scenes, in P1's order, one each. So the
 * old questions («does a scene split across two days», «do two short scenes share one», «is a day
 * named after both») have no answers any more, because the shapes they asked about cannot occur.
 *
 * What is still asked, and is the whole of this file:
 *
 *   how many teaching days — as many as there are scenes, bounded by the calendar and by the cap;
 *   what is cut when they do not fit — whole SCENES, from the tail;
 *   where the days LAND — the step, unchanged;
 *   what the final day is — every checkpoint of the plan, no material, no budget.
 *
 * `need` and `capacity` are still computed and still reported; they simply stop deciding anything
 * ({@see SceneDay}), and the tests that assert them do it because the «срок мал» card and the plan
 * preview print them at the learner.
 */
beforeEach(fn () => $this->scheduler = new PlanScheduler());

/**
 * A skeleton written as `[scene title, what the scene costs, [ability, …]]`.
 *
 * The cost is shared over the scene's abilities the way any whole number divides — remainder to the
 * first ones. Since v0.4 it decides nothing about the calendar; it is what the plan tells the
 * learner it is about to teach, and what A7 sums when it asks whether the rest still fits.
 */
function outline(array $scenes): PlanOutline
{
    $raw = ['goal_summary' => 'Цель', 'scenes' => []];

    foreach ($scenes as $i => [$title, $cost, $outcomes]) {
        $base = intdiv($cost, count($outcomes));
        $remainder = $cost % count($outcomes);

        $skills = [];
        foreach ($outcomes as $n => $outcome) {
            $skills[] = [
                'id' => 's' . ($i + 1) . '.' . ($n + 1),
                'outcome' => $outcome,
                'checkpoint' => 'слышно: ' . $outcome,
                'est_terms' => max(1, $base + ($n < $remainder ? 1 : 0)),
                'topics' => ['тема ' . ($i + 1)],
            ];
        }

        $raw['scenes'][] = [
            'position' => $i + 1,
            'title' => $title,
            'intro' => 'Вводка сцены ' . ($i + 1) . '. Перед тобой собеседник, и он заговорит первым.',
            'skills' => $skills,
            'opening_lines' => ['Hello?'],
            'entities' => [],
        ];
    }

    return PlanOutline::fromArray($raw);
}

/** One of the three hand-written v0.4 skeletons the plan is designed around. */
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

// ── capacity table: still read, and no longer a divisor ───────────────────────────────────────

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

it('gives every teaching day the scene`s own size, whatever the minutes say', function () {
    // The minutes bound the SESSION now, not the day: «~25 единиц» is what a scene holds, and a
    // learner who picked ten minutes walks the same day over more sittings rather than a smaller one.
    $ten = $this->scheduler->compute(outline([['Сцена', 8, ['a', 'b']]]), 10, day('2026-09-05'), day('2026-09-01'));
    $forty = $this->scheduler->compute(outline([['Сцена', 8, ['a', 'b']]]), 40, day('2026-09-05'), day('2026-09-01'));

    expect($ten->days[0]->termBudget)->toBe(SceneDay::UNITS)
        ->and($forty->days[0]->termBudget)->toBe(SceneDay::UNITS)
        ->and($ten->capacity)->toBe(7)
        ->and($forty->capacity)->toBe(24);
});

// ── the наряд's scenarios, on the real skeletons ──────────────────────────────────────────────

it('S1 «врач через 30 дней»: TWO days of teaching, because the goal is two situations', function () {
    // THE test this rewrite exists for, asked the v0.4 way. Under v0.1 the model was told it had 30
    // days, wrote 29 of them, and the server «computed» that it needed 29 — its own instruction,
    // read back. Under v0.2 it was arithmetic over card prices. Now it is the plainest fact there
    // is: the goal is «регистратура» and «кабинет врача», so it is two days.
    $plan = $this->scheduler->compute(
        fixtureOutline('s1-outline.v0.4.json'),
        minutesPerDay: 20,
        eventDate: day('2026-09-29'),
        today: day('2026-08-31'),
    );

    expect($plan->introDays)->toBe(2)
        ->and($plan->need)->toBe(20)
        ->and($plan->capacity)->toBe(14)
        ->and($plan->fits)->toBeTrue()
        ->and($plan->dropped)->toBe([])
        ->and($plan->restDays)->toBe(27);
});

it('S1 «врач через 3 дня»: still two days of teaching, and it still fits', function () {
    $plan = $this->scheduler->compute(
        fixtureOutline('s1-outline.v0.4.json'),
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

it('S2 «собеседование через 2 дня»: one teaching day, and the last TWO scenes are cut', function () {
    // Two days means exactly one teaching day, and the goal is three situations. What the learner
    // loses is the tail — the questions at the end of the call — and not a slice out of the middle
    // of the first conversation, which is what packing by capacity used to produce.
    $plan = $this->scheduler->compute(
        fixtureOutline('s2-outline.v0.4.json'),
        minutesPerDay: 40,
        eventDate: day('2026-09-01'),
        today: day('2026-08-31'),
    );

    expect($plan->introDays)->toBe(1)
        ->and($plan->fits)->toBeFalse()
        ->and($plan->dropReason)->toBe('deadline')
        // Whole scenes: two situations, two abilities each.
        ->and($plan->dropped)->toHaveCount(4)
        // The «срок мал» card is built from these outcomes, so it says what was lost in the
        // learner's own words rather than «четыре умения».
        ->and($plan->dropped[0]->outcome)->toBe('описать последний проект своими словами')
        ->and($plan->days[0]->title)->toBe('Открыть звонок и рассказать о себе');
});

it('S2 A7 says the same thing mid-plan: tight, and here is what is at risk', function () {
    $check = $this->scheduler->recheck(
        remainingIntroDays: array_slice($this->scheduler->compute(
            fixtureOutline('s2-outline.v0.4.json'),
            minutesPerDay: 40,
            eventDate: day('2026-09-10'),
            today: day('2026-08-31'),
        )->days, 0, 3),
        minutesPerDay: 40,
        eventDate: day('2026-09-01'),
        today: day('2026-08-31'),
    );

    // Three scenes left, one teaching day to hold them. Nothing is CUT here — A7 reports, and the
    // decision is the learner's.
    expect($check->deadlineTight)->toBeTrue()
        ->and($check->introDaysRemaining)->toBe(1)
        ->and($check->atRisk)->toHaveCount(4);
});

it('S3 «сегодня везу кота»: one day does both jobs, and the whole plan is that one scene', function () {
    $plan = $this->scheduler->compute(
        fixtureOutline('s3-outline.v0.4.json'),
        minutesPerDay: 20,
        eventDate: day('2026-08-31'),
        today: day('2026-08-31'),
    );

    expect($plan->maxDays)->toBe(1)
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
        ->and($plan->days[0]->termBudget)->toBe(SceneDay::UNITS);
});

it('drops the scenes a same-day plan cannot reach, instead of compressing them into one sitting', function () {
    // The old rule compressed the WHOLE plan into the one day there was. That was possible while a
    // day was a bag of cards; two situations in one sitting is two sittings, so the rest is named in
    // `dropped` and the learner is told.
    $plan = $this->scheduler->compute(
        outline([['Первая', 10, ['a', 'b']], ['Вторая', 10, ['c', 'd']]]),
        minutesPerDay: 20,
        eventDate: day('2026-09-01'),
        today: day('2026-09-01'),
    );

    expect($plan->days)->toHaveCount(1)
        ->and($plan->finalSameDay)->toBeTrue()
        ->and($plan->fits)->toBeFalse()
        ->and($plan->dropReason)->toBe('deadline')
        ->and($plan->dropped)->toHaveCount(2)
        ->and($plan->days[0]->title)->toBe('Первая');
});

it('caps a plan at 14 teaching days when the goal is twenty situations, whatever the calendar offers', function () {
    // A year of room and twenty scenes. The calendar is NOT what ran out, so «перенеси дату» is the
    // wrong advice and `drop_reason` says so.
    $scenes = [];
    for ($i = 1; $i <= 20; $i++) {
        $scenes[] = ["Сцена {$i}", 5, ["умение {$i}"]];
    }

    $plan = $this->scheduler->compute(
        outline($scenes),
        minutesPerDay: 20,
        eventDate: day('2027-08-31'),
        today: day('2026-08-31'),
    );

    expect($plan->introDays)->toBe(PlanScheduler::MAX_INTRO_DAYS)
        ->and($plan->fits)->toBeFalse()
        ->and($plan->dropReason)->toBe('cap')
        ->and($plan->dropped)->toHaveCount(6);
});

it('drops from the END, because P1 writes scenes typical-before-deep', function () {
    $plan = $this->scheduler->compute(
        outline([
            ['Регистратура', 6, ['записаться']],
            ['Кабинет', 6, ['описать боль']],
            ['Аптека', 6, ['купить лекарство']],
        ]),
        minutesPerDay: 20,
        eventDate: day('2026-09-02'),
        today: day('2026-08-31'),
    );

    expect($plan->introDays)->toBe(2)
        ->and(array_map(fn ($d) => $d->title, array_slice($plan->days, 0, 2)))
        ->toBe(['Регистратура', 'Кабинет'])
        ->and($plan->dropped)->toHaveCount(1)
        ->and($plan->dropped[0]->outcome)->toBe('купить лекарство');
});

// ── the day the scene becomes ─────────────────────────────────────────────────────────────────

it('gives the day the scene`s title, its вводка, its lines and its names', function () {
    $plan = $this->scheduler->compute(
        fixtureOutline('s1-outline.v0.4.json'),
        minutesPerDay: 20,
        eventDate: day('2026-09-05'),
        today: day('2026-09-01'),
    );

    $first = $plan->days[0];

    expect($first->title)->toBe('Записаться и открыть приём')
        ->and($first->intro)->toStartWith('Перед тобой администратор регистратуры.')
        ->and($first->openingLines)->toContain('Do you have an appointment?')
        ->and($first->entities)->toBe(['доктор Смит'])
        ->and($first->sourceSceneIndex)->toBe(1)
        // The interlocutor, derived from the scene's own lines: P1 v0.4 answers with no role object,
        // and a name invented here would be a fact about a person nobody described.
        ->and($first->role?->openingLines)->toHaveCount(4)
        ->and($first->role?->name)->toBe('');
});

it('hands the prompt the scene and nothing else — five keys, and the skills carry their ids', function () {
    $plan = $this->scheduler->compute(
        fixtureOutline('s1-outline.v0.4.json'),
        minutesPerDay: 20,
        eventDate: day('2026-09-05'),
        today: day('2026-09-01'),
    );

    $json = $plan->days[0]->sceneJson();

    expect(array_keys($json))->toBe(['title', 'intro', 'skills', 'opening_lines', 'entities'])
        ->and($json['skills'][0]['id'])->toBe('s1.1')
        ->and($json['skills'][1]['id'])->toBe('s1.2')
        // No `est_terms` in the scene the model reads: a price is the scheduler's business, and a
        // number in front of the model is a number it will try to hit.
        ->and(array_keys($json['skills'][0]))->toBe(['id', 'outcome', 'checkpoint', 'topics']);
});

it('gives the final day every checkpoint of the plan, in order, and no material at all', function () {
    $plan = $this->scheduler->compute(
        outline([['Первая', 8, ['a', 'b']], ['Вторая', 8, ['c', 'd']]]),
        minutesPerDay: 20,
        eventDate: day('2026-09-10'),
        today: day('2026-09-01'),
    );

    $final = $plan->days[count($plan->days) - 1];

    expect($final->kind)->toBe(PlanDayKind::Final)
        ->and($final->termBudget)->toBe(0)
        ->and($final->skills)->toBe([])
        ->and($final->sourceSceneIndex)->toBeNull()
        ->and($final->checkpoints)->toBe(['слышно: a', 'слышно: b', 'слышно: c', 'слышно: d'])
        ->and($final->scheduledOn->format('Y-m-d'))->toBe('2026-09-10');
});

// ── the calendar ──────────────────────────────────────────────────────────────────────────────

it('spreads teaching over the room there is, up to three days apart', function () {
    $plan = $this->scheduler->compute(
        outline([['Первая', 8, ['a']], ['Вторая', 8, ['b']]]),
        minutesPerDay: 20,
        eventDate: day('2026-09-08'),
        today: day('2026-09-01'),
    );

    expect($plan->introDays)->toBe(2)
        ->and($plan->step)->toBe(3)
        ->and($plan->restDays)->toBe(5)
        ->and(array_map(fn ($d) => $d->scheduledOn->format('Y-m-d'), $plan->days))
        ->toBe(['2026-09-01', '2026-09-04', '2026-09-08']);
});

it('runs teaching days back to back when the slack is smaller than the teaching', function () {
    $plan = $this->scheduler->compute(
        outline([['Первая', 8, ['a']], ['Вторая', 8, ['b']], ['Третья', 8, ['c']]]),
        minutesPerDay: 20,
        eventDate: day('2026-09-04'),
        today: day('2026-09-01'),
    );

    expect($plan->introDays)->toBe(3)
        ->and($plan->step)->toBe(1)
        ->and(array_map(fn ($d) => $d->scheduledOn->format('Y-m-d'), $plan->days))
        ->toBe(['2026-09-01', '2026-09-02', '2026-09-03', '2026-09-04']);
});

it('never lets the step run past three days, however much room there is', function () {
    $plan = $this->scheduler->compute(
        outline([['Первая', 8, ['a']], ['Вторая', 8, ['b']]]),
        minutesPerDay: 20,
        eventDate: day('2026-12-01'),
        today: day('2026-09-01'),
    );

    expect($plan->step)->toBe(PlanScheduler::MAX_STEP);
});

it('refuses a past event date instead of clamping it to today', function () {
    expect(fn () => $this->scheduler->compute(
        outline([['Сцена', 8, ['a']]]),
        minutesPerDay: 20,
        eventDate: day('2026-08-30'),
        today: day('2026-08-31'),
    ))->toThrow(EventDateInPast::class);
});

it('ignores the time of day on both ends', function () {
    // A plan is counted in days. «Today at 23:50» and «today at 00:10» are the same day, and a
    // scheduler with an opinion about hours would make a plan that starts at midnight one day short.
    $plan = $this->scheduler->compute(
        outline([['Сцена', 8, ['a']]]),
        minutesPerDay: 20,
        eventDate: new DateTimeImmutable('2026-09-05 23:50:00'),
        today: new DateTimeImmutable('2026-09-01 00:10:00'),
    );

    expect($plan->maxDays)->toBe(5)
        ->and($plan->days[0]->scheduledOn->format('H:i'))->toBe('00:00');
});

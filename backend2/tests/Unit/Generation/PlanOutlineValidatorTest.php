<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\PlanOutlineValidator;
use App\Modules\Generation\Domain\ValueObject\PlanViolation;

beforeEach(fn () => $this->validator = new PlanOutlineValidator());

function outlineFixture(string $name): array
{
    /** @var array<mixed> $raw */
    $raw = json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/plan/' . $name), true);

    return $raw;
}

function outlineCodes(array $violations): array
{
    return array_values(array_unique(array_map(static fn (PlanViolation $v): string => $v->code, $violations)));
}

/** One scene, three abilities, everything valid — the base every negative case below breaks once. */
function outlineWith(array $overrides = [], array $skillOverrides = []): array
{
    $skill = [
        'id' => 's1.1',
        'outcome' => 'сказать, где именно болит',
        'checkpoint' => 'называет конкретное место, и врачу не приходится переспрашивать',
        'est_terms' => 4,
        'topics' => ['части тела и где болит'],
        ...$skillOverrides,
    ];

    return [
        'goal_summary' => 'Иду к врачу с болью в спине.',
        'scenes' => [[
            'position' => 1,
            'title' => 'Описать боль',
            'intro' => 'Перед тобой врач. Он спросит, где болит и как давно. Успех — если он понял '
                . 'место с первого раза.',
            'skills' => [$skill, [...$skill, 'id' => 's1.2'], [...$skill, 'id' => 's1.3']],
            'opening_lines' => ['Where does it hurt?', 'Since when?'],
            'entities' => [],
        ]],
        ...$overrides,
    ];
}

it('passes the three v0.4 skeletons the plan is designed around', function (string $fixture) {
    expect($this->validator->validate(outlineFixture($fixture), 'ru'))->toBe([]);
})->with([
    's1-outline.v0.4.json',
    's2-outline.v0.4.json',
    's3-outline.v0.4.json',
]);

// ── the вводка: the screen a person with zero English reads first ─────────────────────────────

it('refuses a scene with no вводка — the day would open on a list of sentences', function () {
    $raw = outlineWith();
    $raw['scenes'][0]['intro'] = '';

    expect(outlineCodes($this->validator->validate($raw)))
        ->toContain(PlanOutlineValidator::INTRO_MISSING);
});

it('counts, rather than refuses, a вводка with the language being learned inside it', function () {
    // The outline gets ONE re-run and no more, so a gate that fires here costs the learner two paid
    // calls and then the whole plan. «Изучаемого языка во вводке нет» is a rule worth stating and
    // not worth $0.05.
    $raw = outlineWith();
    $raw['scenes'][0]['intro'] = 'Врач спросит: where does it hurt, и ты ответишь.';

    expect($this->validator->validate($raw, 'ru'))->toBe([])
        ->and(outlineCodes($this->validator->warnings($raw, 'ru')))
        ->toContain(PlanOutlineValidator::INTRO_LANGUAGE_WARNING);
});

it('refuses a skeleton with nothing to schedule', function () {
    expect(outlineCodes($this->validator->validate(['scenes' => []])))
        ->toContain(PlanOutlineValidator::NO_SCENES);
});

it('refuses an ability with no checkpoint — a promise nothing can prove', function () {
    $raw = outlineWith(skillOverrides: ['checkpoint' => '']);

    expect(outlineCodes($this->validator->validate($raw)))
        ->toContain(PlanOutlineValidator::CHECKPOINT_MISSING);
});

it('refuses a checkpoint that is the promise said again with the punctuation moved', function () {
    $raw = outlineWith(skillOverrides: ['checkpoint' => 'Сказать где именно болит!']);

    expect(outlineCodes($this->validator->validate($raw)))
        ->toContain(PlanOutlineValidator::CHECKPOINT_ECHOES_OUTCOME);
});

// ── the counts: a guide, a warning band, and a refusal ────────────────────────────────────────

it('refuses a price only outside 1–12 — the range the scheduler`s arithmetic still means something in', function (mixed $est) {
    // Zero is an ability the scheduler believes is free; forty turns a two-day goal into a
    // fortnight. Those break the arithmetic. Nine does not.
    $violations = $this->validator->validate(outlineWith(skillOverrides: ['est_terms' => $est]));

    expect(outlineCodes($violations))->toContain(PlanOutlineValidator::EST_TERMS);
})->with([0, 13, 40, 'много', null]);

it('accepts a price off-guide but usable, and warns about it', function (int $est) {
    $raw = outlineWith(skillOverrides: ['est_terms' => $est]);

    expect($this->validator->validate($raw))->toBe([])
        ->and(outlineCodes($this->validator->warnings($raw)))
        ->toContain(PlanOutlineValidator::EST_TERMS_WARNING);
})->with([1, 2, 9, 12]);

it('says nothing at all about a price the prompt asked for', function (int $est) {
    $raw = outlineWith(skillOverrides: ['est_terms' => $est]);

    expect($this->validator->validate($raw))->toBe([])
        ->and($this->validator->warnings($raw))->toBe([]);
})->with([3, 5, 8]);

it('accepts one opening line and refuses seven', function (int $lines, bool $ok) {
    // The lines are raw material for «Тебе скажут» — v0.4 asks the day to ADAPT them rather than
    // quote them, so one thin line is a thin scene and not a broken one. Seven is somebody else's
    // scene.
    $raw = outlineWith();
    $raw['scenes'][0]['opening_lines'] = array_fill(0, $lines, 'Where does it hurt?');

    expect(outlineCodes($this->validator->validate($raw)))
        ->when(! $ok, fn ($e) => $e->toContain(PlanOutlineValidator::OPENING_LINES))
        ->when($ok, fn ($e) => $e->not->toContain(PlanOutlineValidator::OPENING_LINES));
})->with([[1, true], [6, true], [7, false]]);

it('accepts a scene with nobody to talk to', function () {
    // Reading forms alone has no interlocutor, and inventing «сотрудник, который просто рядом» is
    // worse than admitting it. Its checkpoints are still tested in the rehearsal.
    $raw = outlineWith();
    $raw['scenes'][0]['opening_lines'] = [];

    expect($this->validator->validate($raw))->toBe([]);
});

it('refuses «и» joining two actions in one promise', function () {
    $raw = outlineWith(skillOverrides: [
        'outcome' => 'объяснить, что болит, и попросить направление',
        'checkpoint' => 'называет место боли, и врач выписывает направление',
    ]);

    expect(outlineCodes($this->validator->validate($raw)))
        ->toContain(PlanOutlineValidator::OUTCOME_TWO_ACTIONS);
});

it('leaves the one «и» that belongs alone — a comprehension ability must say it back', function () {
    // The prompt's own exemption: understanding cannot be observed, so «понять…» always ends
    // «…и повторить своими словами». A gate that refused it would refuse the rule being obeyed.
    $raw = outlineWith(skillOverrides: [
        'outcome' => 'понять, что назначил врач, и повторить своими словами',
        'checkpoint' => 'повторяет назначение, и врач подтверждает',
    ]);

    expect($this->validator->validate($raw))->toBe([]);
});

it('leaves «и» between two objects of one action alone', function () {
    $raw = outlineWith(skillOverrides: ['outcome' => 'назвать время и место приёма']);

    expect($this->validator->validate($raw))->toBe([]);
});

it('refuses a word of the language being learned on the screen the learner reads first', function (string $field, mixed $value) {
    $raw = $field === 'topics'
        ? outlineWith(skillOverrides: ['topics' => [$value]])
        : ($field === 'outcome' || $field === 'checkpoint'
            ? outlineWith(skillOverrides: [$field => $value])
            : outlineWith([$field => $value]));

    expect(outlineCodes($this->validator->validate($raw, 'ru')))
        ->toContain(PlanOutlineValidator::TARGET_LANGUAGE);
})->with([
    ['title', 'Plan for the doctor'],
    ['goal_restated', 'Иду к врачу и говорю where it hurts.'],
    ['topics', 'past simple'],
    ['outcome', 'сказать where it hurts'],
    ['checkpoint', 'называет lower back'],
]);

it('leaves the learner`s own Latin words alone — goal_terms, abbreviations and codes', function (string $outcome, array $goalTerms) {
    $raw = outlineWith(['goal_terms' => $goalTerms], ['outcome' => $outcome]);

    expect($this->validator->validate($raw, 'ru'))->toBe([]);
})->with([
    ['рассказать про свой опыт с Laravel', ['Laravel']],
    ['назвать, что делал с API', []],
    ['назвать место 14A', []],
]);

it('refuses more than eight scenes, and lets six through', function (int $scenes, bool $ok) {
    // The prompt asks for 1–5. A sixth scene is a plan the learner can still read and the
    // scheduler can still cut; a ninth is a course.
    $raw = outlineWith();
    $raw['scenes'] = array_fill(0, $scenes, $raw['scenes'][0]);

    expect(outlineCodes($this->validator->validate($raw)))
        ->when(! $ok, fn ($e) => $e->toContain(PlanOutlineValidator::SCENE_COUNT))
        ->when($ok, fn ($e) => $e->not->toContain(PlanOutlineValidator::SCENE_COUNT));
})->with([[6, true], [8, true], [9, false]]);

/** A skeleton of exactly `$total` abilities, spread over as few scenes as it takes. */
function outlineWithSkills(int $total): array
{
    $raw = outlineWith();
    $skill = $raw['scenes'][0]['skills'][0];
    $scene = $raw['scenes'][0];

    $scenes = [];
    for ($left = $total; $left > 0; $left -= 4) {
        $scene['skills'] = array_fill(0, min(4, $left), $skill);
        $scenes[] = $scene;
    }
    $raw['scenes'] = $scenes;

    return $raw;
}

it('refuses an ability count only outside 3–20', function (int $total) {
    expect(outlineCodes($this->validator->validate(outlineWithSkills($total))))
        ->toContain(PlanOutlineValidator::SKILL_COUNT);
})->with([2, 21, 24]);

it('accepts 13 abilities and WARNS — the live skeleton that cost $0.069 to refuse twice', function (int $total) {
    // `docs/research/plan-v0.3-run.md`: the model wrote thirteen abilities twice in a row, the
    // second time with the number quoted at it, and `PlanScheduler` would have taught that plan in
    // six days. Off-guide is not broken.
    $raw = outlineWithSkills($total);

    expect($this->validator->validate($raw))->toBe([])
        ->and(outlineCodes($this->validator->warnings($raw)))
        ->toContain(PlanOutlineValidator::SKILL_COUNT_WARNING);
})->with([13, 20]);

it('says nothing about an ability count the prompt asked for', function (int $total) {
    $raw = outlineWithSkills($total);

    expect($this->validator->validate($raw))->toBe([])
        ->and($this->validator->warnings($raw))->toBe([]);
})->with([3, 12]);

it('refuses a binding list that came back as a string', function () {
    // The failure that would otherwise reach the day prompt as the literal characters of a JSON
    // array, and be read by the model as content.
    expect(outlineCodes($this->validator->validate(outlineWith(['goal_terms' => 'PHP, API']))))
        ->toContain(PlanOutlineValidator::NOT_A_LIST);
});

it('accepts binding lists that are simply empty', function () {
    expect($this->validator->validate(outlineWith(['entities' => [], 'constraints' => [], 'goal_terms' => []])))
        ->toBe([]);
});

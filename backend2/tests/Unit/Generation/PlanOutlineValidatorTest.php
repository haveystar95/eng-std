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

/** One scene, one ability, everything valid — the base every negative case below breaks once. */
function outlineWith(array $overrides = [], array $skillOverrides = []): array
{
    $skill = [
        'outcome' => 'сказать, где именно болит',
        'checkpoint' => 'называет конкретное место, и врачу не приходится переспрашивать',
        'est_terms' => 4,
        'topics' => ['части тела и где болит'],
        ...$skillOverrides,
    ];

    return [
        'title' => 'К врачу из-за спины',
        'goal_restated' => 'Иду к врачу с болью в спине.',
        'entities' => [],
        'constraints' => [],
        'goal_terms' => [],
        'scenes' => [[
            'title' => 'Описать боль',
            'role' => [
                'name' => 'врач-терапевт',
                'opening_lines' => [
                    ['text' => 'Where does it hurt?', 'translation' => 'Где болит?'],
                    ['text' => 'Since when?', 'translation' => 'С каких пор?'],
                ],
                'if_silent' => 'показывает пальцем и спрашивает проще',
            ],
            'skills' => [$skill, $skill, $skill],
        ]],
        ...$overrides,
    ];
}

it('passes the three v0.2 skeletons the plan is designed around', function (string $fixture) {
    expect($this->validator->validate(outlineFixture($fixture), 'ru'))->toBe([]);
})->with([
    's1-outline.v0.2.json',
    's2-outline.v0.2.json',
    's3-outline.v0.2.json',
]);

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

it('refuses a price outside 3–8, in both directions', function (mixed $est) {
    $violations = $this->validator->validate(outlineWith(skillOverrides: ['est_terms' => $est]));

    expect(outlineCodes($violations))->toContain(PlanOutlineValidator::EST_TERMS);
})->with([9, 2, 0, 'много', null]);

it('accepts both ends of the price range', function (int $est) {
    expect($this->validator->validate(outlineWith(skillOverrides: ['est_terms' => $est])))->toBe([]);
})->with([3, 8]);

it('refuses a scene whose interlocutor says nothing the learner must recognise', function () {
    // P2 quotes `opening_lines` verbatim as the lines the day has to teach. A role with one line
    // is a conversation with nothing to answer.
    $raw = outlineWith();
    $raw['scenes'][0]['role']['opening_lines'] = [['text' => 'Hello?', 'translation' => 'Здравствуйте?']];

    expect(outlineCodes($this->validator->validate($raw)))->toContain(PlanOutlineValidator::OPENING_LINES);
});

it('accepts a scene with no interlocutor at all', function () {
    // Reading forms alone has nobody to talk to, and the prompt says inventing «сотрудник, который
    // просто рядом» is worse than admitting it. Its checkpoints are still tested in the rehearsal.
    $raw = outlineWith();
    $raw['scenes'][0]['role'] = null;

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

it('refuses more than five scenes — past that a plan is a course', function () {
    $raw = outlineWith();
    $scene = $raw['scenes'][0];
    $raw['scenes'] = array_fill(0, 6, $scene);

    expect(outlineCodes($this->validator->validate($raw)))->toContain(PlanOutlineValidator::SCENE_COUNT);
});

it('refuses a plan with fewer than three abilities or more than twelve', function (int $perScene, int $scenes) {
    $raw = outlineWith();
    $skill = $raw['scenes'][0]['skills'][0];
    $raw['scenes'][0]['skills'] = array_fill(0, $perScene, $skill);
    $raw['scenes'] = array_fill(0, $scenes, $raw['scenes'][0]);

    expect(outlineCodes($this->validator->validate($raw)))->toContain(PlanOutlineValidator::SKILL_COUNT);
})->with([[2, 1], [7, 2]]);

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

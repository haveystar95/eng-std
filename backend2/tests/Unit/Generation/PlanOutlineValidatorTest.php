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

it('passes the three real outlines the sandbox produced', function (string $fixture) {
    expect($this->validator->validate(outlineFixture($fixture)))->toBe([]);
})->with([
    's1-outline.v0.json',
    's2-outline.v0.1.json',
    's3-outline.v0.1.json',
]);

it('reads a v0 outline without complaining about the field v0.1 dropped', function () {
    // S1 still carries `final_day.checkpoints`, which v0.1 removed because the server assembles
    // that list now. A stored outline written months ago must keep opening.
    $raw = outlineFixture('s1-outline.v0.json');

    expect($raw['final_day'])->toHaveKey('checkpoints')
        ->and($this->validator->validate($raw))->toBe([]);
});

it('refuses an outline with nothing to schedule', function () {
    expect(outlineCodes($this->validator->validate(['days' => [], 'final_day' => ['title' => 'Прогон']])))
        ->toContain(PlanOutlineValidator::NO_DAYS);
});

it('refuses a day that promises nothing', function () {
    $raw = ['final_day' => ['title' => 'Прогон'], 'days' => [
        ['index' => 1, 'title' => 'День', 'term_budget' => 9, 'outcome' => [], 'role' => null],
    ]];

    expect(outlineCodes($this->validator->validate($raw)))->toContain(PlanOutlineValidator::DAY_WITHOUT_OUTCOME);
});

it('accepts a day with no interlocutor at all', function () {
    // Reading forms alone has nobody to talk to, and the prompt says inventing «сотрудник, который
    // просто рядом» is worse than admitting it. Such a day simply has no checkpoints of its own.
    $raw = ['final_day' => ['title' => 'Прогон'], 'days' => [
        ['index' => 1, 'title' => 'Прочитать бланки', 'term_budget' => 9, 'outcome' => ['прочитать бланк'], 'role' => null],
    ]];

    expect($this->validator->validate($raw))->toBe([]);
});

it('refuses a conversation with the wrong number of checkpoints', function () {
    $raw = ['final_day' => ['title' => 'Прогон'], 'days' => [[
        'index' => 1, 'title' => 'День', 'term_budget' => 9,
        'outcome' => ['сказать А', 'сказать Б'],
        'role' => ['name' => 'врач', 'opening_lines' => [], 'checkpoints' => ['слышно А'], 'if_silent' => 'переспросит'],
    ]]];

    expect(outlineCodes($this->validator->validate($raw)))
        ->toContain(PlanOutlineValidator::CHECKPOINT_COUNT)
        ->toContain(PlanOutlineValidator::CHECKPOINT_MISMATCH);
});

it('refuses a checkpoint that is the promise said again with the punctuation moved', function () {
    $raw = ['final_day' => ['title' => 'Прогон'], 'days' => [[
        'index' => 1, 'title' => 'День', 'term_budget' => 9,
        'outcome' => ['сказать, где именно болит', 'понять назначение и повторить своими словами'],
        'role' => ['name' => 'врач', 'opening_lines' => [],
            'checkpoints' => ['Сказать где именно болит!', 'повторяет назначение своими словами, и врач подтверждает'],
            'if_silent' => 'переспросит'],
    ]]];

    $violations = $this->validator->validate($raw);

    expect(outlineCodes($violations))->toBe([PlanOutlineValidator::CHECKPOINT_ECHOES_OUTCOME])
        ->and($violations[0]->detail)->toContain('чек-пойнт 0');
});

it('refuses a day with no term budget', function () {
    $raw = ['final_day' => ['title' => 'Прогон'], 'days' => [
        ['index' => 1, 'title' => 'День', 'outcome' => ['сказать А'], 'role' => null],
    ]];

    expect(outlineCodes($this->validator->validate($raw)))->toContain(PlanOutlineValidator::BUDGET_MISSING);
});

it('refuses an outline with no final day', function () {
    $raw = ['days' => [['index' => 1, 'title' => 'День', 'term_budget' => 9, 'outcome' => ['А'], 'role' => null]]];

    expect(outlineCodes($this->validator->validate($raw)))->toContain(PlanOutlineValidator::NO_FINAL_DAY);
});

it('refuses a binding list that came back as a string', function () {
    // The failure that would otherwise reach the day prompt as the literal characters of a JSON
    // array, and be read by the model as content.
    $raw = ['final_day' => ['title' => 'Прогон'], 'goal_terms' => 'PHP, API',
        'days' => [['index' => 1, 'title' => 'День', 'term_budget' => 9, 'outcome' => ['А'], 'role' => null]]];

    expect(outlineCodes($this->validator->validate($raw)))->toContain(PlanOutlineValidator::NOT_A_LIST);
});

it('accepts binding lists that are simply empty', function () {
    $raw = ['final_day' => ['title' => 'Прогон'], 'entities' => [], 'constraints' => [], 'goal_terms' => [],
        'days' => [['index' => 1, 'title' => 'День', 'term_budget' => 9, 'outcome' => ['А'], 'role' => null]]];

    expect($this->validator->validate($raw))->toBe([]);
});

it('lets a ONE-DAY plan carry up to five checkpoints — it is the whole goal', function () {
    // The S3 scenario, live: «сегодня везу кота в ветклинику, прививка и странный кашель» has four
    // parts, the prompt says a short plan compresses rather than drops, and the model honestly
    // wrote four abilities with four checkpoints. The 2–3 band is a shape rule for an ordinary
    // day; on a single-day plan it was measuring the length of the GOAL.
    $raw = ['final_day' => ['title' => 'Прогон'], 'single_day' => true, 'days' => [[
        'index' => 1, 'title' => 'Весь визит', 'term_budget' => 9,
        'outcome' => ['объяснить визит', 'описать кашель', 'понять назначение и повторить своими словами', 'спросить, когда вернуться'],
        'role' => ['name' => 'ветеринар', 'opening_lines' => [],
            'checkpoints' => ['слышно причину визита', 'слышно описание кашля', 'повторяет назначение', 'спрашивает про повтор'],
            'if_silent' => 'предложит выбор'],
    ]]];

    expect($this->validator->validate($raw))->toBe([]);
});

it('still holds the floor of two, however short the plan', function () {
    $raw = ['final_day' => ['title' => 'Прогон'], 'single_day' => true, 'days' => [[
        'index' => 1, 'title' => 'Весь визит', 'term_budget' => 9,
        'outcome' => ['объяснить визит'],
        'role' => ['name' => 'ветеринар', 'opening_lines' => [], 'checkpoints' => ['слышно причину'], 'if_silent' => 'предложит выбор'],
    ]]];

    expect(outlineCodes($this->validator->validate($raw)))->toContain(PlanOutlineValidator::CHECKPOINT_COUNT);
});

it('still refuses four checkpoints on a multi-day plan, where the band means what it says', function () {
    $raw = ['final_day' => ['title' => 'Прогон'], 'single_day' => false, 'days' => [
        ['index' => 1, 'title' => 'День 1', 'term_budget' => 9,
            'outcome' => ['A', 'B', 'C', 'D'],
            'role' => ['name' => 'врач', 'opening_lines' => [], 'checkpoints' => ['a', 'b', 'c', 'd'], 'if_silent' => 'переспросит']],
        ['index' => 2, 'title' => 'День 2', 'term_budget' => 9, 'outcome' => ['E', 'F'],
            'role' => ['name' => 'врач', 'opening_lines' => [], 'checkpoints' => ['e', 'f'], 'if_silent' => 'переспросит']],
    ]];

    expect(outlineCodes($this->validator->validate($raw)))->toContain(PlanOutlineValidator::CHECKPOINT_COUNT);
});

it('stops a one-day plan at five, because a conversation past that cannot be judged', function () {
    $outcomes = ['A', 'B', 'C', 'D', 'E', 'F'];
    $raw = ['final_day' => ['title' => 'Прогон'], 'single_day' => true, 'days' => [[
        'index' => 1, 'title' => 'Весь визит', 'term_budget' => 9,
        'outcome' => $outcomes,
        'role' => ['name' => 'ветеринар', 'opening_lines' => [],
            'checkpoints' => array_map(static fn (string $o): string => 'слышно ' . $o, $outcomes),
            'if_silent' => 'предложит выбор'],
    ]]];

    $violations = $this->validator->validate($raw);

    expect(outlineCodes($violations))->toContain(PlanOutlineValidator::CHECKPOINT_COUNT)
        ->and($violations[0]->detail)->toContain('2–5');
});

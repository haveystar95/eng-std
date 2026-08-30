<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\PlanCoherenceValidator;
use App\Modules\Generation\Domain\ValueObject\PlanCoherenceCandidate;
use App\Modules\Generation\Domain\ValueObject\PlanDayItem;
use App\Modules\Generation\Domain\ValueObject\PlanViolation;

/**
 * The gate that only exists because a plan is a SEQUENCE.
 *
 * Every case here is a day that would pass {@see PlanDayValidator} — the counts are right, the
 * checkpoints are closed, the keys are clean — and is still wrong as part of its plan.
 */
beforeEach(fn () => $this->gate = new PlanCoherenceValidator());

function line(string $text, string $translation = 'перевод', string $exampleTranslation = 'перевод примера'): PlanDayItem
{
    return new PlanDayItem($text, 'phrase', true, $translation, null, '', $text . ' out loud', $exampleTranslation, 1);
}

function word(string $text, string $translation = 'слово', string $exampleTranslation = 'перевод примера'): PlanDayItem
{
    return new PlanDayItem($text, 'word', false, $translation, null, '', 'I used ' . $text . '.', $exampleTranslation, null);
}

/** @param list<PlanDayItem> $items */
function coherence(array $items, array $overrides = []): PlanCoherenceCandidate
{
    return new PlanCoherenceCandidate(
        supportLang: $overrides['support'] ?? 'ru',
        dayIndex: $overrides['day'] ?? 2,
        items: $items,
        knownTexts: $overrides['known'] ?? [],
        previousCheckpoints: $overrides['previous'] ?? [],
        dayCheckpoints: $overrides['checkpoints'] ?? [],
        entities: $overrides['entities'] ?? [],
    );
}

/**
 * Its own name, not `codes()`: {@see PlanDayValidatorTest} already declares one, and Pest shares a
 * process across files.
 *
 * @param  list<PlanViolation>  $violations
 */
function coherenceCodes(array $violations): array
{
    return array_map(static fn (PlanViolation $v): string => $v->code, $violations);
}

// ── rule 1: a term is new on ONE day ──────────────────────────────────────────────────────────

it('cuts a day that introduces a term an earlier day already taught', function () {
    $day = coherence(
        [line('Where does it hurt?'), word('back'), word('shoulder'), word('knee')],
        ['known' => ['t1' => 'back', 't2' => 'neck']],
    );

    expect(coherenceCodes($this->gate->validate($day)))->toContain(PlanCoherenceValidator::TERM_REPEATED);
});

it('matches a repeated term the way the rest of the plan does — case and punctuation folded', function () {
    $day = coherence(
        [line('Where does it hurt?'), word('Back!'), word('shoulder'), word('knee')],
        ['known' => ['t1' => 'back']],
    );

    expect(coherenceCodes($this->gate->validate($day)))->toContain(PlanCoherenceValidator::TERM_REPEATED);
});

it('lets a day through when nothing it teaches was taught before', function () {
    $day = coherence(
        [line('Where does it hurt?'), line('How long has it been?'), word('shoulder'), word('knee')],
        ['known' => ['t1' => 'back', 't2' => 'neck']],
    );

    expect($this->gate->validate($day))->toBe([]);
});

it('says nothing about repeats on the FIRST day, which has no earlier day to repeat', function () {
    $day = coherence([line('Hello there'), word('doctor')], ['day' => 1, 'known' => []]);

    expect($this->gate->validate($day))->toBe([]);
});

// ── rule 2: no checkpoint twice ───────────────────────────────────────────────────────────────

it('cuts a day that promises a checkpoint another day already promises', function () {
    $day = coherence([line('a'), word('b')], [
        'previous' => ['слышно, как он называет место боли'],
        'checkpoints' => ['Слышно, как он называет место боли.'],
    ]);

    expect(coherenceCodes($this->gate->validate($day)))->toContain(PlanCoherenceValidator::CHECKPOINT_DUPLICATED);
});

it('catches a day that promises the same checkpoint twice inside itself', function () {
    $day = coherence([line('a'), word('b')], [
        'checkpoints' => ['слышно, как он спрашивает цену', 'слышно, как он спрашивает цену'],
    ]);

    expect(coherenceCodes($this->gate->validate($day)))->toContain(PlanCoherenceValidator::CHECKPOINT_DUPLICATED);
});

// ── rule 3: the skeleton's entities ───────────────────────────────────────────────────────────

it('cuts a translation that calls a masculine entity «она»', function () {
    $day = coherence(
        [line('a', 'Кота зовут Барсик', 'Кота зовут Барсик, она любит спать'), word('b')],
        ['entities' => [['name' => 'кот', 'gender' => 'masculine', 'number' => 'singular', 'note' => '']]],
    );

    expect(coherenceCodes($this->gate->validate($day)))->toContain(PlanCoherenceValidator::ENTITY_DISAGREEMENT);
});

it('leaves a sentence alone when it agrees, and when it does not mention the entity at all', function () {
    $entities = [['name' => 'кот', 'gender' => 'masculine', 'number' => 'singular', 'note' => '']];

    $agreeing = coherence([line('a', 'Кот здесь', 'Кот здесь, он спит'), word('b')], ['entities' => $entities]);
    // «Она» is about something else entirely — the cat is not in this sentence.
    $unrelated = coherence([line('a', 'Она открыла дверь', 'Она открыла дверь и вышла'), word('b')], ['entities' => $entities]);

    expect($this->gate->validate($agreeing))->toBe([])
        ->and($this->gate->validate($unrelated))->toBe([]);
});

it('does not read «который» as the entity «кот»', function () {
    // The false positive the inflection ceiling exists for: a three-letter name is a prefix of a
    // very ordinary Russian word, and flagging it would buy a paid regeneration for a correct day.
    $day = coherence(
        [line('a', 'Тот, который пришёл', 'Она пришла, та, которая живёт рядом'), word('b')],
        ['entities' => [['name' => 'кот', 'gender' => 'masculine', 'number' => 'singular', 'note' => '']]],
    );

    expect($this->gate->validate($day))->toBe([]);
});

it('does not apply Russian agreement markers to a plan written in another language', function () {
    $day = coherence(
        [line('a', 'Pisica se numeste Barsik, она', 'x'), word('b')],
        ['support' => 'ro', 'entities' => [['name' => 'pisica', 'gender' => 'masculine', 'number' => 'singular', 'note' => '']]],
    );

    expect($this->gate->validate($day))->toBe([]);
});

// ── rule 4: the reply share, shared with the day gate ─────────────────────────────────────────

it('refuses a regenerated day that came back as a vocabulary list', function () {
    $day = coherence([line('a'), word('b'), word('c'), word('d'), word('e'), word('f'), word('g'), word('h'), word('i')]);

    expect(coherenceCodes($this->gate->validate($day)))->toContain(PlanCoherenceValidator::LINE_SHARE);
});

it('accepts the reply share the day validator accepts — one range, two gates', function () {
    // 9 cards, 5 replies: exactly `ceil(0.45 × 9)`, which is what the server asked the model for.
    $day = coherence([
        line('a'), line('b'), line('c'), line('d'), line('e'),
        word('f'), word('g'), word('h'), word('i'),
    ]);

    expect($this->gate->validate($day))->toBe([]);
});

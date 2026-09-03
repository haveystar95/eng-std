<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Service\PlanDayComposer;
use App\Modules\Generation\Domain\Service\PlanDayValidator;
use App\Modules\Generation\Domain\ValueObject\PlanDayCandidate;
use App\Modules\Generation\Domain\ValueObject\PlanDayItem;
use App\Modules\Generation\Domain\ValueObject\PlanShelf;
use App\Modules\Generation\Domain\ValueObject\PlanViolation;

/**
 * The gate that decides whether a paid day is written or paid for again.
 *
 * The positive case runs on the hand-written v0.4 day-scenes in `tests/Fixtures/plan`, not on
 * synthetic material, and that is the point: a gate which refuses a good day costs more than one
 * which lets a weak day through — the second is caught by reading, the first burns money on
 * regenerations and then gets switched off. Every negative below breaks exactly ONE thing in a day
 * that is otherwise clean, so a failing test names the rule it broke.
 *
 * The shelves are flattened here the way {@see PlanDayComposer::items()} flattens them — assembled
 * cards pasted from `frame` + `filler`, the shelf deciding the kind — so what the gates judge in
 * this file is the same object production judges.
 */
beforeEach(fn () => $this->validator = new PlanDayValidator());

/** @return array<string, mixed> */
function planFixture(string $name): array
{
    /** @var array<string, mixed> $raw */
    $raw = json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/plan/' . $name), true);

    return $raw;
}

/**
 * The six shelves, flattened into cards exactly as the composer flattens them.
 *
 * @param  array<string, mixed>  $day
 * @return list<PlanDayItem>
 */
function planItems(array $day): array
{
    $out = [];
    foreach (PlanShelf::model() as $shelf) {
        $index = -1;
        /** @var list<array<string, mixed>> $cards */
        $cards = is_array($day[$shelf->value] ?? null) ? $day[$shelf->value] : [];
        foreach ($cards as $card) {
            $index++;
            $assembled = $shelf->isAssembled();
            $frame = $assembled ? (string) ($card['frame'] ?? '') : '';
            $filler = $assembled ? (string) ($card['filler'] ?? '') : '';

            $out[] = new PlanDayItem(
                text: $assembled ? PlanDayComposer::assemble($frame, $filler) : (string) ($card['text'] ?? ''),
                type: $shelf->kind() === PlanDayItem::KIND_WORD ? 'word' : 'phrase',
                kind: $shelf->kind(),
                isLine: $shelf->kind() === PlanDayItem::KIND_LINE,
                translation: (string) ($card['translation'] ?? ''),
                transliteration: (string) ($card['transliteration'] ?? ''),
                description: '',
                example: (string) ($card['example'] ?? ''),
                exampleTranslation: (string) ($card['example_translation'] ?? ''),
                frame: $frame,
                filler: $filler,
                speaker: match (true) {
                    $shelf->isRole() => PlanDayItem::SPEAKER_ROLE,
                    $shelf->kind() === PlanDayItem::KIND_LINE => PlanDayItem::SPEAKER_LEARNER,
                    default => null,
                },
                imageApiPrompt: (string) ($card['image_api_prompt'] ?? ''),
                coversCheckpoint: null,
                index: $index,
                shelf: $shelf->value,
                skillRef: ((string) ($card['skill_ref'] ?? '')) ?: null,
                value: ((string) ($card['value'] ?? '')) ?: null,
            );
        }
    }

    return $out;
}

/**
 * The S1 day-scene, with `$edits` applied to the raw answer before it is flattened.
 *
 * Edits are addressed the way a violation is — shelf and index — so a test reads as «break `say[1]`
 * and see which code comes back», which is also how a repair call would be pointed at it.
 *
 * @param  array<string, array<int, array<string, mixed>>>  $edits  shelf => index => fields
 */
function planCandidate(
    array $edits = [],
    string $dayFixture = 's1-day1.v0.4.json',
    string $outlineFixture = 's1-outline.v0.4.json',
    int $scene = 1,
    string $supportLang = 'ru',
    string $targetLang = 'en',
    string $level = 'basic',
    array $rescueKit = [],
    array $knownTexts = [],
): PlanDayCandidate {
    $day = planFixture($dayFixture);
    foreach ($edits as $shelf => $cards) {
        foreach ($cards as $index => $fields) {
            if ($fields === []) {
                // An empty edit REMOVES the card — that is how an empty shelf is built.
                unset($day[$shelf][$index]);
                $day[$shelf] = array_values($day[$shelf]);

                continue;
            }
            $day[$shelf][$index] = [...$day[$shelf][$index], ...$fields];
        }
    }

    $outline = planFixture($outlineFixture);
    /** @var array<string, mixed> $sceneData */
    $sceneData = $outline['scenes'][$scene - 1];

    return new PlanDayCandidate(
        supportLang: $supportLang,
        targetLang: $targetLang,
        items: planItems($day),
        // THE IDS ARE THE SERVER'S, spelled here the way {@see PlanOutline::fromArray()} spells
        // them. P1 is not asked for them: an id is an address, and «s1.2» computed from the
        // position addresses exactly what a model-written id would have.
        skillIds: array_map(
            static fn (int $i): string => 's' . $scene . '.' . ($i + 1),
            array_keys($sceneData['skills']),
        ),
        entityNames: $sceneData['entities'] ?? [],
        rescueKit: $rescueKit,
        knownTexts: $knownTexts,
        goalTerms: [],
        level: $level,
        sceneIntro: (string) ($sceneData['intro'] ?? ''),
    );
}

/** @param list<PlanViolation> $violations */
function planCodes(array $violations): array
{
    return array_values(array_unique(array_map(static fn (PlanViolation $v): string => $v->code, $violations)));
}

// ── the day that is right ────────────────────────────────────────────────────────────────────

it('lets a hand-written day-scene through, with no fatal verdict and no counter', function () {
    $day = planCandidate();

    expect($this->validator->validate($day))->toBe([])
        // Not one counter either: the fixture sits inside every shelf guide, asks a question, has a
        // repair move and serves both skills. A fixture that raised counters would make «this day
        // is clean» impossible to state.
        ->and(planCodes($this->validator->warnings($day)))->toBe([]);
});

it('lets the Romanian day through, where three gates have no rule for the language', function () {
    // `numbers`, the stop-list and the repair phrases are all English-only tables. A language absent
    // from them is not judged — «немецкое правило ещё не написано» must not read as «каждый
    // немецкий день сломан» — and this is the day that proves the silence is silence and not a pass
    // by accident.
    $day = planCandidate(dayFixture: 's3-day1.v0.4.json', outlineFixture: 's3-outline.v0.4.json', targetLang: 'ro');

    expect($this->validator->validate($day))->toBe([])
        ->and(planCodes($this->validator->warnings($day)))->toBe([]);
});

// ── the shelves ──────────────────────────────────────────────────────────────────────────────

it('refuses a day whose «Тебе скажут» shelf is empty', function () {
    $day = planCandidate(['hear' => [3 => [], 2 => [], 1 => [], 0 => []]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::SHELF_MISSING);
});

it('refuses a day with nothing to say', function () {
    $day = planCandidate(['say' => [3 => [], 2 => [], 1 => [], 0 => []]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::SHELF_MISSING);
});

it('refuses a day whose lines are built from nothing', function () {
    $day = planCandidate([
        'words' => [3 => [], 2 => [], 1 => [], 0 => []],
        'chunks' => [1 => [], 0 => []],
    ]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::SHELF_MISSING);
});

it('does not refuse a day with no questions to ask and no numbers to hear', function () {
    // Both are GUIDES: a scene where there is genuinely nothing to clarify is a real scene, and a
    // gate that refused it would be refusing the situation rather than the answer.
    $day = planCandidate(['ask' => [1 => [], 0 => []], 'numbers' => [1 => [], 0 => []]]);

    expect($this->validator->validate($day))->toBe([])
        ->and(planCodes($this->validator->warnings($day)))->toContain(PlanDayValidator::SIZE_OUT_OF_RANGE);
});

// ── the card ─────────────────────────────────────────────────────────────────────────────────

it('refuses a frame with two holes and one filler', function () {
    $day = planCandidate(['say' => [1 => ['frame' => 'It hurts in my ___ and my ___.']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::GAP_MISSING);
});

it('refuses a hole with nothing to put in it', function () {
    $day = planCandidate(['say' => [1 => ['filler' => '']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::GAP_MISSING);
});

it('refuses a filler with nowhere to stand', function () {
    $day = planCandidate(['say' => [2 => ['filler' => 'lower back']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::GAP_MISSING);
});

it('refuses a gap left standing in a field the learner reads', function () {
    $day = planCandidate(['words' => [0 => ['example' => 'The pain in my ___ gets worse at night.']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::GAP_OUTSIDE_FRAME);
});

it('refuses a translation with a gap in it — the узор of live day 2', function () {
    $day = planCandidate(['say' => [1 => ['translation' => 'Болит в ___.']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::TRANSLATION_HAS_GAP);
});

it('counts, and no longer refuses, a filler the day never taught', function () {
    // WHAT THREE PAID DAYS BOUGHT. `card.filler_not_card` was card-fatal until the live run of
    // наряд P2-v0.4: three P2 answers for «К врачу с ребёнком» died on it, three repairs were
    // bought trying to satisfy it, and day 1 never shipped. The refused cards were sentences a
    // person says — «Should we go to the front desk?», «In which clinic is it?» — whose gaps hold
    // words the day is FORBIDDEN to card, because basic vocabulary is barred and numbers live only
    // on their own shelf. The P2 v0.4 prompt never states this rule; the repair prompt does, and
    // the model broke it three times running.
    //
    // So on a LINE the check stayed and its rank changed: the day ships and the mismatch is counted.
    // The owner closed it on 02.09 — the line keeps the counter, the prompt gains a preference
    // («prefer as the key a word or chunk from today's shelves»), and the word keeps the refusal
    // (the test below).
    $day = planCandidate(['say' => [1 => ['filler' => 'the spine']]]);

    expect(planCodes($this->validator->validate($day)))->not->toContain(PlanDayValidator::FILLER_NOT_CARD)
        ->and(planCodes($this->validator->warnings($day)))->toContain(PlanDayValidator::FILLER_MISMATCH_WARNING);
});

it('refuses a word whose example does not contain the word', function () {
    // THE OTHER RANK OF THE SAME CODE. On a line the rule was unsatisfiable; here it is the
    // mechanics of the card: the example is the sentence the gap is cut out of, and
    // `PlayabilityAssessor` calls a term clozeable only when its example contains the answer. An
    // example that never says «prescription» does not make a weaker card, it makes a card the
    // trainer cannot build — and the model is only being asked to use the word it just wrote.
    $day = planCandidate(['words' => [1 => ['example' => 'The doctor wrote something for the pain.']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::FILLER_NOT_CARD);
});

it('refuses a connector whose example does not contain the connector', function () {
    $day = planCandidate(['chunks' => [1 => ['example' => 'Please wait until the doctor is free.']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::FILLER_NOT_CARD);
});

it('accepts a word standing in its own sentence, however the sentence is punctuated', function () {
    // Word boundaries and nothing stricter: case, commas and the full stop are not the card's
    // business. «form» inside «information» is still refused — that is a gap cut inside a word.
    $day = planCandidate(['words' => [1 => ['example' => 'Take this Prescription, please, to the pharmacy.']]]);

    expect(planCodes($this->validator->validate($day)))->not->toContain(PlanDayValidator::FILLER_NOT_CARD);
});

it('accepts a filler the day teaches inside a bigger piece', function () {
    // Coverage, not equality: the repair answered «Should we go to the ___?» + «front desk» on a day
    // whose chunk was «come to the front desk», and for the learner that IS the piece they were
    // just taught, standing in the gap. Here the day teaches «take a seat» and the gap holds
    // «seat», so nothing is counted — the counter is for a gap the day left unexplained.
    $day = planCandidate(['say' => [1 => ['frame' => 'Is this ___ free?', 'filler' => 'seat', 'translation' => 'Это место свободно?']]]);

    expect(planCodes($this->validator->warnings($day)))->not->toContain(PlanDayValidator::FILLER_MISMATCH_WARNING);
});

it('lets the interlocutor speak their own English — the hear shelf owes the day no filler', function () {
    // THE GATE THAT PAID FOR ITSELF. On the live run of наряд P2-v0.4 the receptionist said «Is it
    // for your child?», «What time works for you?», «Please fill out this form», and all three were
    // refused because `child`, `time` and `form` are not cards of the day. They cannot be: basic
    // words are barred from being cards, and «числа живут только в numbers». Nine cards went back
    // twice, two repairs and two day calls were spent, and the model had been right both times.
    //
    // `hear` is understood and never produced, so a filler the day did not teach is not a demand on
    // the learner — it is the word the clerk happened to stress. It is exempt from the COUNTER too:
    // production still gets counted (the `say` case above), and counting the interlocutor's own
    // English would bury that count in noise.
    $day = planCandidate(['hear' => [0 => ['frame' => 'Is it for your ___?', 'filler' => 'child']]]);

    expect(planCodes($this->validator->validate($day)))->not->toContain(PlanDayValidator::FILLER_NOT_CARD)
        ->and(planCodes($this->validator->warnings($day)))->not->toContain(PlanDayValidator::FILLER_MISMATCH_WARNING);
});

it('counts the same mismatch in a language that inflects, where it may not be one at all', function () {
    // «tusea» is the card and «tusea mare» is what the frame needs: a model that inflected or
    // qualified correctly is not refused, and outside English it may not be a mismatch in the first
    // place — which is why the counter, not the refusal, is the honest answer everywhere.
    $day = planCandidate(
        ['say' => [1 => ['filler' => 'tusea mare']]],
        dayFixture: 's3-day1.v0.4.json',
        outlineFixture: 's3-outline.v0.4.json',
        targetLang: 'ro',
    );

    expect(planCodes($this->validator->validate($day)))->not->toContain(PlanDayValidator::FILLER_NOT_CARD)
        ->and(planCodes($this->validator->warnings($day)))->toContain(PlanDayValidator::FILLER_MISMATCH_WARNING);
});

it('refuses two cards that assemble into the same text', function () {
    $day = planCandidate(['ask' => [1 => ['frame' => 'How often should I take the ___?', 'filler' => 'painkiller']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::CLONE);
});

it('refuses a card that teaches a rescue phrase the server already writes', function () {
    $day = planCandidate(rescueKit: ['Sorry, could you say that again?']);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::CLONE);
});

it('refuses a card an earlier day of this plan already introduced', function () {
    $day = planCandidate(knownTexts: ['prescription']);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::CLONE);
});

it('refuses an example that is a card of the day rather than a sentence with one in it', function () {
    $day = planCandidate(['words' => [1 => ['example' => 'prescription']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::EXAMPLE_IS_A_TERM);
});

/**
 * «ТЕБЕ СКАЖУТ» НЕ ПРОИЗНОСЯТ, ПОЭТОМУ ЕЁ ПЕРЕВОД НЕ ФАТАЛЕН — решение владельца, 03.09.
 *
 * The gate says «ученик читает вопрос, в котором не спрошено то, что карточка требует произнести».
 * Of a `hear` line that sentence is not true: the card asks for nothing, the понимаю tier never
 * produces it. It burned the owner's live day 2 on «Smoking»/«курение» against «Курить внутри
 * нельзя» — a verbal noun against a verb, correct Russian nobody is asked to say.
 */
it('counts, and does not refuse, an interlocutor line whose translation drops its key', function () {
    $day = planCandidate(['hear' => [0 => [
        'frame' => 'Do you have a ___?',
        'filler' => 'prescription',
        'translation' => 'У вас всё в порядке?',
    ]]]);

    expect(planCodes($this->validator->validate($day)))->not->toContain(PlanDayValidator::TRANSLATION_MISSING_KEY)
        ->and(planCodes($this->validator->warnings($day)))
        ->toContain(PlanDayValidator::HEAR_TRANSLATION_MISSING_KEY);
});

it('still refuses a line the learner DOES say whose translation drops its key', function () {
    // `say` is the learner's own turn: there the sentence the gate says is exactly true.
    $day = planCandidate(['say' => [0 => ['translation' => 'У меня всё в порядке.']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::TRANSLATION_MISSING_KEY);
});

/**
 * A SENTENCE THAT SWALLOWED A WHOLE LINE — COUNTED, NEVER REFUSED (решение владельца, 03.09).
 *
 * The defect is real («I see, without utilities.» taught by «When the power went out, I realized
 * that I see, without utilities, life becomes…») and the shape is sometimes unavoidable: the third
 * rebuild of the owner's live day 2 died here on the connector «included in the rent», whose day
 * line is «Heating is included in the rent.» — the connector plus one word. An example of the
 * connector is REQUIRED to contain the connector, so it contains the line too.
 */
it('counts an example that swallowed a whole line of the day, and writes the day anyway', function () {
    // `say[0]` of the fixture assembles to «I need to check in, please.»; the word «prescription»
    // still contains its own text, so nothing else about this card is wrong.
    $day = planCandidate(['words' => [1 => [
        'example' => 'Before my prescription I need to check in, please, at the desk.',
    ]]]);

    expect(planCodes($this->validator->validate($day)))->not->toContain(PlanDayValidator::EXAMPLE_IS_A_TERM)
        ->and(planCodes($this->validator->warnings($day)))
        ->toContain(PlanDayValidator::EXAMPLE_CONTAINS_LINE);
});


it('leaves an example that merely uses the day\'s words alone', function () {
    // The other side of the same rule, and the reason it looks for a LINE: an example is REQUIRED
    // to contain its own card, and the day's lines are built out of the day's words — so «contains
    // a card of the day» would refuse every healthy example there is.
    $day = planCandidate(['words' => [1 => [
        'example' => 'The nurse will check the prescription before you take a seat.',
    ]]]);

    expect(planCodes($this->validator->validate($day)))->not->toContain(PlanDayValidator::EXAMPLE_IS_A_TERM);
});

it('refuses two examples that are one sentence with the term swapped — Д-29', function () {
    // «If the fever gets worse, I need to worse tomorrow»: the live day 3 wrote one sentence and
    // dropped four different words into its slot. Every other gate passed all four.
    $day = planCandidate([
        'words' => [
            1 => ['example' => 'The nurse handed me a prescription at the desk.'],
            2 => ['example' => 'The nurse handed me a painkiller at the desk.'],
        ],
    ]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::EXAMPLE_SKELETON_CLONE);
});

it('refuses an example that arrives without its translation — вторая половина Д-29', function () {
    // The live run found one sentence written into TWO example rows, the second of them
    // untranslated. That duplicate was the server's and is fixed where it was made; this is the
    // same half-card arriving from the model instead, and it lands in the day just as untranslated.
    $day = planCandidate(['words' => [1 => ['example_translation' => '']]]);

    expect(planCodes($this->validator->validate($day)))
        ->toContain(PlanDayValidator::EXAMPLE_WITHOUT_TRANSLATION);
});

it('refuses basic vocabulary as a card from «Понимаю простое» up', function () {
    $day = planCandidate(['words' => [3 => ['text' => 'Monday', 'translation' => 'понедельник']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::WORD_IS_BASIC);
});

it('counts the same card at level zero, where it is the lesson', function () {
    $day = planCandidate(['words' => [3 => ['text' => 'Monday', 'translation' => 'понедельник']]], level: 'zero');

    expect(planCodes($this->validator->validate($day)))->not->toContain(PlanDayValidator::WORD_IS_BASIC)
        ->and(planCodes($this->validator->warnings($day)))->toContain(PlanDayValidator::WORD_IS_BASIC_WARNING);
});

it('refuses a bare number as a word card, whatever the list holds', function () {
    $day = planCandidate(['words' => [3 => ['text' => '14', 'translation' => 'четырнадцать']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::WORD_IS_BASIC);
});

it('refuses a card longer than its shelf allows', function () {
    $day = planCandidate(['words' => [0 => ['text' => 'the lower part of my back']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::KIND_SIZE);
});

it('refuses a spoken line the learner could not say in one breath', function () {
    $day = planCandidate(['say' => [2 => [
        'frame' => 'Sorry, I did not quite catch that last part, could you please say the whole thing again for me?',
    ]]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::KIND_SIZE);
});

it('lets the interlocutor speak longer than the learner — понимать можно длиннее', function () {
    // Nine words: refused on `say`, fine on `hear`. That asymmetry is канон §7 and the one thing
    // this rule exists to express.
    $day = planCandidate(['hear' => [0 => ['frame' => 'Do you have an appointment with us here today, please?']]]);

    expect(planCodes($this->validator->validate($day)))->not->toContain(PlanDayValidator::KIND_SIZE);
});

it('refuses a proper name of the scenario as a card of its own', function () {
    $day = planCandidate(['words' => [3 => ['text' => 'доктор Смит', 'translation' => 'доктор Смит']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::TERM_IS_A_NAME);
});

it('teaches a common noun the skeleton mis-filed as an entity', function () {
    // THE LIVE SKELETON'S OWN LIST. P1 answered `entities: [clinic, front desk, appointment,
    // walk-in clinic]` for «К врачу с ребёнком» — four common nouns, and exactly the vocabulary the
    // scene exists to teach. Read strictly, the day was asked to teach a visit to a clinic without
    // the word «clinic», and two of its cards were refused for using it.
    //
    // An entity now bars a card only when it is WRITTEN as a name. «доктор Смит» above still is;
    // «clinic» is P1 filing a word in the wrong list, and the card survives it.
    $day = planCandidate(
        ['words' => [3 => ['text' => 'clinic', 'translation' => 'клиника', 'example' => 'The clinic opens at eight.', 'example_translation' => 'Клиника открывается в восемь.']]],
    );
    $day = new PlanDayCandidate(
        supportLang: $day->supportLang,
        targetLang: $day->targetLang,
        items: $day->items,
        skillIds: $day->skillIds,
        entityNames: ['clinic', 'front desk', 'Dr. Ahmed'],
        rescueKit: $day->rescueKit,
        knownTexts: $day->knownTexts,
        goalTerms: $day->goalTerms,
        level: $day->level,
        sceneIntro: $day->sceneIntro,
    );

    expect(planCodes($this->validator->validate($day)))->not->toContain(PlanDayValidator::TERM_IS_A_NAME);
});

it('refuses a translation that is the term written in the other alphabet', function () {
    // «Ivanov» glossed «Иванов» is one word and one piece of information: the learner reads the
    // Latin, says the Cyrillic, and has learned that a name is spelled the way it sounds. Character
    // by character the two strings differ in every position, so every other gate passes it.
    $day = planCandidate(['words' => [3 => ['text' => 'Ivanov', 'translation' => 'Иванов']]]);

    expect(planCodes($this->validator->validate($day)))
        ->toContain(PlanDayValidator::TRANSLATION_IS_TRANSLITERATION);
});

it('refuses a translation that is simply the term again', function () {
    $day = planCandidate(['words' => [1 => ['translation' => 'prescription']]]);

    expect(planCodes($this->validator->validate($day)))
        ->toContain(PlanDayValidator::TRANSLATION_IS_TRANSLITERATION);
});

it('refuses a line whose translation never names the word it grades — гейт 219', function () {
    $day = planCandidate(['say' => [1 => ['translation' => 'У меня всё болит.']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::TRANSLATION_MISSING_KEY);
});

it('refuses a card that serves a skill this scene never promised', function () {
    $day = planCandidate(['say' => [0 => ['skill_ref' => 's9.9']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::SKILL_REF_INVALID);
});

it('refuses a card that names no skill at all', function () {
    $day = planCandidate(['words' => [0 => ['skill_ref' => '']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::SKILL_REF_INVALID);
});

it('refuses a number the line does not say', function () {
    $day = planCandidate(['numbers' => [1 => ['value' => '85']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::NUMBER_VALUE_MISMATCH);
});

it('refuses a number card with nothing to grade against', function () {
    $day = planCandidate(['numbers' => [0 => ['value' => '']]]);

    expect(planCodes($this->validator->validate($day)))->toContain(PlanDayValidator::NUMBER_VALUE_MISMATCH);
});

it('hears a number spelled out as words, and one written as digits', function () {
    $spelled = planCandidate(['numbers' => [1 => ['frame' => 'That will be ___, please.', 'filler' => 'twenty euros', 'value' => '20']]]);
    $digits = planCandidate(['numbers' => [1 => ['frame' => 'That will be ___, please.', 'filler' => '20 euros', 'value' => '20']]]);

    expect(planCodes($this->validator->validate($spelled)))->not->toContain(PlanDayValidator::NUMBER_VALUE_MISMATCH)
        ->and(planCodes($this->validator->validate($digits)))->not->toContain(PlanDayValidator::NUMBER_VALUE_MISMATCH);
});

// ── counted, never refused ───────────────────────────────────────────────────────────────────

it('counts a shelf outside its guide instead of refusing the day', function () {
    $day = planCandidate(['hear' => [3 => [], 2 => []]]);

    expect($this->validator->validate($day))->toBe([])
        ->and(planCodes($this->validator->warnings($day)))->toContain(PlanDayValidator::SIZE_OUT_OF_RANGE);
});

it('counts a day with no question of its own', function () {
    $day = planCandidate([
        'say' => [
            2 => ['frame' => 'Please slow down a little.', 'translation' => 'Помедленнее, пожалуйста.'],
            3 => ['frame' => 'I will bring a ___.', 'filler' => 'referral', 'translation' => 'Я принесу направление.'],
        ],
        'ask' => [
            0 => ['frame' => 'I take the ___ every morning.', 'translation' => 'Я принимаю обезболивающее каждое утро.'],
            1 => ['frame' => 'I will ___ over there.', 'translation' => 'Я хочу присесть вон там.'],
        ],
    ]);

    expect($this->validator->validate($day))->toBe([])
        ->and(planCodes($this->validator->warnings($day)))->toContain(PlanDayValidator::NO_QUESTION);
});

it('counts a day with no way out of a misheard sentence', function () {
    $day = planCandidate(['say' => [2 => [
        'frame' => 'I came here on my own.',
        'translation' => 'Я пришёл сам.',
    ]]]);

    expect($this->validator->validate($day))->toBe([])
        ->and(planCodes($this->validator->warnings($day)))->toContain(PlanDayValidator::NO_REPAIR);
});

it('counts a skill of the scene that no card serves', function () {
    $day = planCandidate([
        'hear' => [0 => ['skill_ref' => 's1.2'], 2 => ['skill_ref' => 's1.2']],
        'say' => [0 => ['skill_ref' => 's1.2'], 3 => ['skill_ref' => 's1.2']],
        'ask' => [1 => ['skill_ref' => 's1.2']],
        'words' => [3 => ['skill_ref' => 's1.2']],
        'chunks' => [0 => ['skill_ref' => 's1.2'], 1 => ['skill_ref' => 's1.2']],
        'numbers' => [0 => ['skill_ref' => 's1.2']],
    ]);

    expect($this->validator->validate($day))->toBe([])
        ->and(planCodes($this->validator->warnings($day)))->toContain(PlanDayValidator::SKILL_UNCOVERED);
});

it('counts a piece that stands in no line of the day', function () {
    $day = planCandidate(['words' => [1 => ['text' => 'waiting room', 'translation' => 'зал ожидания']]]);

    expect(planCodes($this->validator->warnings($day)))->toContain(PlanDayValidator::SUBSTITUTION_OUTSIDE_FRAME);
});

it('counts formulas past a third of the spoken lines', function () {
    $day = planCandidate([
        'say' => [
            0 => ['frame' => 'Good morning, I am here.', 'filler' => '', 'translation' => 'Доброе утро, я здесь.'],
            1 => ['frame' => 'Thank you very much.', 'filler' => '', 'translation' => 'Большое спасибо.'],
            3 => ['frame' => 'Nice to meet you.', 'filler' => '', 'translation' => 'Приятно познакомиться.'],
        ],
    ]);

    expect(planCodes($this->validator->warnings($day)))->toContain(PlanDayValidator::FORMULA_CAP);
});

it('counts a day that is more the interlocutor than the learner', function () {
    $day = planCandidate(['say' => [3 => [], 2 => [], 1 => []], 'ask' => [1 => [], 0 => []]]);

    expect(planCodes($this->validator->warnings($day)))->toContain(PlanDayValidator::ROLE_LINE_SHARE);
});

it('counts a card that retells the вводка the learner has already read', function () {
    $day = planCandidate(['say' => [1 => [
        'translation' => 'Сейчас у тебя спросят, записан ли ты и с чем пришёл',
    ]]]);

    expect(planCodes($this->validator->warnings($day)))->toContain(PlanDayValidator::INTRO_REPEATED);
});

it('counts a word with no picture to find', function () {
    $day = planCandidate(['words' => [0 => ['image_api_prompt' => '']]]);

    expect($this->validator->validate($day))->toBe([])
        ->and(planCodes($this->validator->warnings($day)))->toContain(PlanDayValidator::IMAGE_PROMPT_MISSING);
});

it('counts two cards that ask the learner the same question', function () {
    $day = planCandidate(['words' => [2 => ['translation' => 'рецепт']]]);

    expect(planCodes($this->validator->warnings($day)))->toContain(PlanDayValidator::KEY_DUPLICATED);
});

// ── the reading hint: repaired, never fatal ──────────────────────────────────────────────────

it('strips the punctuation a hint picks up from the line it transcribes', function () {
    expect($this->validator->transliterationFor('ru', 'ит хётс ин май лоуэр бэк.'))
        ->toBe('ит хётс ин май лоуэр бэк');
});

it('drops a hint written in the alphabet the learner cannot read', function () {
    expect($this->validator->transliterationFor('ru', 'it hurts in my lower back'))->toBeNull();
});

it('knows when a reading hint is mandatory at all', function () {
    expect($this->validator->scriptsDiffer('ru', 'en'))->toBeTrue()
        ->and($this->validator->scriptsDiffer('ru', 'ro'))->toBeTrue()
        ->and($this->validator->scriptsDiffer('en', 'ro'))->toBeFalse();
});

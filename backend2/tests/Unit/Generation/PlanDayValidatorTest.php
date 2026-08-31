<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Service\PlanDayComposer;
use App\Modules\Generation\Domain\Service\PlanDayValidator;
use App\Modules\Generation\Domain\ValueObject\PlanDayCandidate;
use App\Modules\Generation\Domain\ValueObject\PlanDayItem;
use App\Modules\Generation\Domain\ValueObject\PlanViolation;

/**
 * The gate that decides whether a paid day is written or paid for again.
 *
 * The positive cases run on the hand-written v0.3 days in `tests/Fixtures/plan`, not on synthetic
 * material, and that is the point: a gate which refuses a good day costs more than one which lets
 * a weak day through — the second is caught by reading, the first burns money on regenerations and
 * then gets switched off. The negatives are one per rule, each breaking exactly one thing.
 *
 * Since v0.3 the fixtures carry `frame` and `filler` and NO `text` on a line: the day is assembled
 * through {@see PlanDayComposer::assemble()} on the way into the candidate, exactly as it is in
 * production, so what the gates judge here is the sentence the learner would see.
 */
beforeEach(fn () => $this->validator = new PlanDayValidator());

/** @return array<string, mixed> */
function dayFixture(string $name): array
{
    /** @var array<string, mixed> $raw */
    $raw = json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/plan/' . $name), true);

    return $raw;
}

/** The opening lines of every scene of a day, as the claim handler flattens them. */
function openingLinesOf(string $outlineFixture): array
{
    /** @var array<string, mixed> $raw */
    $raw = json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/plan/' . $outlineFixture), true);

    $out = [];
    foreach ($raw['scenes'] as $scene) {
        foreach ($scene['role']['opening_lines'] ?? [] as $line) {
            $out[] = $line['text'];
        }
    }

    return $out;
}

/** The three arrays of a fixture, flattened exactly as {@see PlanDayComposer::items()} flattens them. */
function itemsOf(array $day): array
{
    $out = [];
    foreach ([
        'phrases' => PlanDayItem::KIND_LINE,
        'words' => PlanDayItem::KIND_WORD,
        'chunks' => PlanDayItem::KIND_CHUNK,
    ] as $key => $kind) {
        // The index is the card's position in the ARRAY it was written in, not in this flattened
        // list — that is the half of an address P2R merges by.
        foreach (array_values($day[$key] ?? []) as $index => $card) {
            $isLine = $kind === PlanDayItem::KIND_LINE;
            $frame = $isLine ? ($card['frame'] ?? '') : '';
            $filler = $isLine ? ($card['filler'] ?? '') : '';
            $out[] = new PlanDayItem(
                text: $isLine ? PlanDayComposer::assemble($frame, $filler) : $card['text'],
                type: $card['type'],
                kind: $kind,
                isLine: $isLine,
                translation: $card['translation'],
                transliteration: $card['transliteration'] ?? '',
                description: $card['description'],
                example: $card['example'],
                exampleTranslation: $card['example_translation'],
                frame: $frame,
                filler: $filler,
                speaker: $isLine ? ($card['speaker'] ?? null) : null,
                imageApiPrompt: $card['image_api_prompt'] ?? '',
                coversCheckpoint: $isLine ? ($card['covers_checkpoint'] ?? null) : null,
                index: $index,
            );
        }
    }

    return $out;
}

/**
 * A candidate built from a fixture, with one card replaced or one number moved.
 *
 * @param  callable(list<PlanDayItem>): list<PlanDayItem>|null  $mutate
 */
function candidate(
    string $dayFixture,
    string $outlineFixture,
    int $checkpoints,
    ?callable $mutate = null,
    string $supportLang = 'ru',
    string $targetLang = 'en',
    array $goalTerms = [],
): PlanDayCandidate {
    $items = itemsOf(dayFixture($dayFixture));
    $items = $mutate === null ? $items : $mutate($items);

    $counted = ['line' => 0, 'word' => 0, 'chunk' => 0];
    foreach ($items as $item) {
        $counted[$item->kind] = ($counted[$item->kind] ?? 0) + 1;
    }

    return new PlanDayCandidate(
        supportLang: $supportLang,
        targetLang: $targetLang,
        termBudget: count($items),
        phraseCount: $counted['line'],
        chunkCount: $counted['chunk'],
        wordCount: $counted['word'],
        checkpointCount: $checkpoints,
        goalTerms: $goalTerms,
        openingLines: openingLinesOf($outlineFixture),
        items: $items,
    );
}

/**
 * A day of `$lines` lines and nothing else, `$formulas` of them without a slot.
 *
 * The one place synthetic material is right: this asks what the ARITHMETIC of the formula cap is,
 * and a hand-written day has one line count, not three. Everything else about it is deliberately
 * clean, so the only verdict that can move is the one under test.
 */
function syntheticDay(int $lines, int $formulas): PlanDayCandidate
{
    $items = [new PlanDayItem(
        text: 'help',
        type: 'word',
        kind: PlanDayItem::KIND_WORD,
        isLine: false,
        translation: 'помощь',
        transliteration: null,
        description: '',
        example: 'I need help today, please.',
        exampleTranslation: 'мне нужна помощь сегодня',
        imageApiPrompt: 'a hand reaching out across a counter',
    )];

    for ($i = 1; $i <= $lines; $i++) {
        $formula = $i <= $formulas;
        $frame = $formula ? "Thank you very much, {$i}." : 'I need ___ today.';
        $filler = $formula ? '' : 'help';
        $items[] = new PlanDayItem(
            text: PlanDayComposer::assemble($frame, $filler),
            type: 'phrase',
            kind: PlanDayItem::KIND_LINE,
            isLine: true,
            translation: "реплика {$i}",
            transliteration: null,
            description: '',
            example: $formula ? "Thank you very much, {$i}, really." : "I need help today, number {$i}.",
            exampleTranslation: "пример {$i}",
            frame: $frame,
            filler: $filler,
            speaker: PlanDayItem::SPEAKER_LEARNER,
            imageApiPrompt: "a person asking for help, scene {$i}",
            coversCheckpoint: null,
        );
    }

    return new PlanDayCandidate(
        supportLang: 'ru',
        targetLang: 'en',
        termBudget: $lines + 1,
        phraseCount: $lines,
        chunkCount: 0,
        wordCount: 1,
        checkpointCount: 0,
        goalTerms: [],
        openingLines: [],
        items: $items,
    );
}

function dayCodes(array $violations): array
{
    return array_values(array_unique(array_map(static fn (PlanViolation $v): string => $v->code, $violations)));
}

/** Replace the item at `$at` with a copy carrying `$overrides`. */
function withCard(int $at, array $overrides): callable
{
    return static function (array $items) use ($at, $overrides): array {
        $item = $items[$at];
        $frame = $overrides['frame'] ?? $item->frame;
        $filler = array_key_exists('filler', $overrides) ? $overrides['filler'] : $item->filler;

        $items[$at] = new PlanDayItem(
            // The assembly is what production does, so an override of `frame` or `filler` moves
            // `text` with it — unless the test names `text` outright, which is how a card that
            // could never be assembled is put in front of the gates.
            text: $overrides['text'] ?? (
                $item->kind === PlanDayItem::KIND_LINE
                    ? PlanDayComposer::assemble($frame, $filler)
                    : $item->text
            ),
            type: $overrides['type'] ?? $item->type,
            kind: $overrides['kind'] ?? $item->kind,
            isLine: $overrides['isLine'] ?? $item->isLine,
            translation: $overrides['translation'] ?? $item->translation,
            transliteration: $overrides['transliteration'] ?? $item->transliteration,
            description: $overrides['description'] ?? $item->description,
            example: $overrides['example'] ?? $item->example,
            exampleTranslation: $overrides['exampleTranslation'] ?? $item->exampleTranslation,
            frame: $frame,
            filler: $filler,
            speaker: array_key_exists('speaker', $overrides) ? $overrides['speaker'] : $item->speaker,
            imageApiPrompt: $overrides['imageApiPrompt'] ?? $item->imageApiPrompt,
            coversCheckpoint: array_key_exists('coversCheckpoint', $overrides)
                ? $overrides['coversCheckpoint']
                : $item->coversCheckpoint,
            // The address survives the override: a test that broke a card and lost its position
            // would assert against `phrases[0]` whatever it edited.
            index: $item->index,
        );

        return $items;
    };
}

// ── the positives ─────────────────────────────────────────────────────────────────────────────

it('passes the hand-written v0.3 days', function (string $day, string $outline, int $checkpoints) {
    expect($this->validator->validate(candidate($day, $outline, $checkpoints)))->toBe([]);
})->with([
    ['s1-day1.v0.3.json', 's1-outline.v0.2.json', 3],
    ['s2-day1.v0.3.json', 's2-outline.v0.2.json', 3],
    ['s3-day1.v0.3.json', 's3-outline.v0.2.json', 3],
]);

it('passes the Romanian day too, where the reading hint is what the learner reads by', function () {
    expect($this->validator->validate(
        candidate('s3-day1.v0.3.json', 's3-outline.v0.2.json', 3, supportLang: 'ru', targetLang: 'ro'),
    ))->toBe([]);
});

it('has nothing to warn about on a day written the way v0.3 asks', function (string $day, string $outline) {
    // The three warnings are shape rules, and the fixtures are what the shape is supposed to look
    // like: a formula count under the cap, a question the learner asks, a way out of a misheard
    // line. A fixture that warns is a fixture that stopped being an example.
    expect($this->validator->warnings(candidate($day, $outline, 3)))->toBe([]);
})->with([
    ['s1-day1.v0.3.json', 's1-outline.v0.2.json'],
    ['s2-day1.v0.3.json', 's2-outline.v0.2.json'],
]);

// ── the three numbers ─────────────────────────────────────────────────────────────────────────

it('refuses a day that is one card off the count it was asked for', function () {
    $day = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3);
    $short = new PlanDayCandidate(
        supportLang: $day->supportLang,
        targetLang: $day->targetLang,
        termBudget: 14,
        phraseCount: 9,          // the day has 8
        chunkCount: $day->chunkCount,
        wordCount: $day->wordCount,
        checkpointCount: 3,
        goalTerms: [],
        openingLines: $day->openingLines,
        items: $day->items,
    );

    expect(dayCodes($this->validator->validate($short)))->toContain(PlanDayValidator::ARRAY_COUNT);
});

// ── the assembly: what the server builds, and what it refuses to build from ───────────────────

it('builds the line from the frame and the filler, punctuation and all', function (string $frame, string $filler, string $expected) {
    expect(PlanDayComposer::assemble($frame, $filler))->toBe($expected);
})->with([
    // The ordinary case, the formula, and the two positions that broke a naive implementation:
    // a slot at the very start of the string and a slot at the very end, where trimming eats it.
    'a slot in the middle' => ['I worked on ___ last year.', 'the API', 'I worked on the API last year.'],
    'a slot at the end' => ['I worked on ___', 'the payment module', 'I worked on the payment module'],
    'a slot at the end, punctuated' => ['I worked on ___.', 'the API', 'I worked on the API.'],
    'a slot at the start' => ['___ is my main focus.', 'The payment module', 'The payment module is my main focus.'],
    'a formula has nothing to paste' => ['Nice to meet you.', '', 'Nice to meet you.'],
    'a question mark survives' => ['Could you repeat ___?', 'the question', 'Could you repeat the question?'],
    // ONE substitution, never two: a second slot is a defect the validator names, and filling it
    // as well would hide it behind a sentence that reads fine.
    'only the first slot is filled' => ['I moved ___ to ___.', 'it', 'I moved it to ___.'],
]);

it('refuses a frame with two holes in it', function () {
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(1, [
        'frame' => 'I have an appointment at ___ with ___.',
    ]));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::FRAME_SLOT_COUNT);
});

it('refuses a hole with nothing in it, and a filler with nowhere to go', function (array $overrides) {
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(1, $overrides));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::FILLER_MISMATCH);
})->with([
    'a slot and no filler' => [['filler' => '']],
    'a filler and no slot' => [['frame' => 'I have an appointment this morning.', 'filler' => 'half past nine']],
]);

it('refuses a filler that is not a card of this day, character for character', function (string $filler) {
    // «payment module» when the card says «the payment module» is not a near miss: the learner
    // meets the word on a card and in a line, and if the two differ they are two words.
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(1, ['filler' => $filler]));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::FILLER_NOT_A_CARD);
})->with(['ten', 'Half past nine', 'half past nine ']);

it('only warns about an off-by-a-case filler outside English, where inflection is the honest answer', function () {
    // P2 v0.3.1 has to decide what a filler looks like in a language with cases. Until it does,
    // «поясница» as a card and «в пояснице» in the slot is a warning and not a refused day.
    $day = candidate('s3-day1.v0.3.json', 's3-outline.v0.2.json', 3, withCard(1, [
        'filler' => 'vaccinul',
    ]), targetLang: 'ro');

    expect(dayCodes($this->validator->validate($day)))->not->toContain(PlanDayValidator::FILLER_NOT_A_CARD)
        ->and(dayCodes($this->validator->warnings($day)))->toContain(PlanDayValidator::FILLER_MISMATCH_WARNING);
});

it('refuses an interlocutor line the skeleton never promised', function () {
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(0, [
        'frame' => 'Have you been here before?',   // plausible, and not in `opening_lines`
    ]));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::ROLE_LINE_INVENTED);
});

it('only WARNS about a day where more than a quarter of the lines belong to the interlocutor', function () {
    // The ceiling joined its own floor ({@see PlanDayValidator::NO_ROLE_LINE}) in v0.3.1: it is a
    // proportion of the answer, no one card is to blame for it, and refusing a day over its shape
    // costs a paid call. Counted instead.
    $off = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(7, [
        'frame' => 'How long has it been like this?',   // verbatim from the skeleton, but one too many
        'filler' => '',
        'speaker' => PlanDayItem::SPEAKER_ROLE,
        'coversCheckpoint' => null,
    ]));

    expect($this->validator->validate($off))->toBe([])
        ->and(dayCodes($this->validator->warnings($off)))->toContain(PlanDayValidator::ROLE_LINE_SHARE);
});

// ── the slot, which belongs to `frame` alone ──────────────────────────────────────────────────

it('refuses a card that left the slot in a field the learner has to say', function (array $overrides, string $field) {
    // Since v0.3 `text` cannot carry a slot by accident — it is assembled. The rule stays for the
    // fields the model still writes itself, and for the one way `text` can still get one: a
    // second hole no filler reached.
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(1, $overrides));
    $violations = $this->validator->validate($broken);

    expect(dayCodes($violations))->toContain(PlanDayValidator::SLOT_OUTSIDE_FRAME)
        ->and(implode(' ', array_map(static fn (PlanViolation $v): string => (string) $v, $violations)))
        ->toContain("`{$field}`");
})->with([
    [['frame' => 'I have an appointment at ___ on ___.'], 'text'],
    [['example' => 'I have an appointment at ___, with doctor Ionescu.'], 'example'],
    [['translation' => 'У меня приём в ___.'], 'translation'],
    [['exampleTranslation' => 'У меня приём в ___, к доктору Ионеску.'], 'example_translation'],
]);

it('keeps the slot legal in `frame` itself, which is the whole point of the field', function () {
    // Guard against the obvious over-reach: if the rule ever read `frame` too, every day would fail.
    expect($this->validator->validate(candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3)))->toBe([]);
});

it('never fails a day over a slot in the reading hint — the hint is dropped, not the day', function () {
    // Реестр решений, п. 189: transliteration is repaired or dropped, never fatal. A slot in it is
    // just another unusable hint.
    $day = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(1, [
        'transliteration' => 'ай хэв эн эпойнтмент эт ___',
    ]));

    expect($this->validator->validate($day))->toBe([])
        ->and($this->validator->transliterationFor('ru', 'ай хэв эн эпойнтмент эт ___'))->toBeNull();
});

// ── the substitutions ─────────────────────────────────────────────────────────────────────────

it('WARNS about a word that stands in no frame of the day, and writes the day', function () {
    // The rule the whole of v0.2 turns on: a word whose example is not one of the day's frames with
    // that word in the slot is a glossary entry sitting next to the conversation. Still the rule —
    // and no longer a refusal. It was the LAST fatal gate between a live day and `ready`, three
    // attempts in a row, always over one word of fourteen cards
    // (`docs/research/plan-v0.3-run.md`). One card is an address now, not a second paid day.
    $off = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(8, [
        'text' => 'physiotherapy',
        'translation' => 'физиотерапия',
        'example' => 'Physiotherapy usually helps with this.',
    ]));

    // Not `toBe([])`: this word is also a line's filler, so renaming it breaks the filler gate as
    // well. What is under test is that the word-in-a-frame rule is no longer among the fatal ones.
    expect(dayCodes($this->validator->validate($off)))
        ->not->toContain(PlanDayValidator::SUBSTITUTION_OUTSIDE_FRAME)
        ->and(dayCodes($this->validator->warnings($off)))
        ->toContain(PlanDayValidator::SUBSTITUTION_OUTSIDE_FRAME);
});

it('WARNS about a word that sits in the FIXED part of a frame instead of its hole', function () {
    // The rule for a word is unchanged and still narrower than the one for a connector: in the
    // slot, or nowhere. Only its price changed.
    $off = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(8, [
        'text' => 'appointment',
        'translation' => 'приём у врача',
        'example' => 'I have an appointment at eleven, not at ten.',
    ]));

    expect(dayCodes($this->validator->validate($off)))
        ->not->toContain(PlanDayValidator::SUBSTITUTION_OUTSIDE_FRAME)
        ->and(dayCodes($this->validator->warnings($off)))
        ->toContain(PlanDayValidator::SUBSTITUTION_OUTSIDE_FRAME);
});

/**
 * s2 with the frame built AROUND a connector — the live «собеседование» shape — and the connector's
 * example set to `$example`.
 */
function connectorInTheFixedPart(string $frame, string $example): PlanDayCandidate
{
    return candidate('s2-day1.v0.3.json', 's2-outline.v0.2.json', 3, static function (array $items) use ($frame, $example): array {
        $items = withCard(6, [
            'frame' => $frame,
            'filler' => 'the payment module',
            'translation' => 'Я в основном работаю с модулем оплаты.',
            'transliteration' => 'ай мэйнли уорк уиз зэ пэймент модьюл',
            'example' => 'I mainly work with the payment module, day in day out.',
        ])($items);

        return withCard(12, [
            'text' => 'work with',
            'translation' => 'работать с',
            'transliteration' => 'уорк уиз',
            'description' => 'To spend your working time on one system rather than another.',
            'example' => $example,
            'exampleTranslation' => 'Я в основном работаю с этим.',
        ])($items);
    });
}

it('lets a CONNECTOR live in the fixed part of a frame — that is where the language puts it', function () {
    // The live «собеседование» refusal, four times over: «I mainly work with ___» beside the chunk
    // «work with». v0.2 called that a defect; it is how a phrasal verb is used. What is checked
    // instead is that the example CONTAINS a frame of the day carrying the connector, and is not a
    // line of the day repeated.
    expect($this->validator->validate(
        connectorInTheFixedPart('I mainly work with ___.', 'I mainly work with queues.'),
    ))->toBe([]);
});

it('lets a connector`s example add a detail, exactly as a word`s may', function () {
    // Containment and not equality. This sentence is the frame plus «, mostly» — which is what the
    // prompt asks an example to be — and one commit of anchored comparison refused it.
    expect($this->validator->validate(
        connectorInTheFixedPart('I mainly work with ___.', 'I mainly work with Laravel, mostly.'),
    ))->toBe([]);
});

it('still wants the WHOLE frame in a connector`s example, tail included — and says so in a warning', function () {
    // Containment is of the frame, not of its beginning: an example that drops the frame's fixed
    // tail is not that frame with a detail added, it is a different sentence. Said, not refused.
    $off = connectorInTheFixedPart(
        'I mainly work with ___ every day.',
        'I mainly work with Laravel, mostly.',
    );

    expect($this->validator->validate($off))->toBe([])
        ->and(dayCodes($this->validator->warnings($off)))
        ->toContain(PlanDayValidator::CHUNK_OUTSIDE_FRAME);
});

it('still refuses a connector whose example is a line of the day — through the clone gate', function () {
    // «work on» whose only example is the line it already stands in teaches that line twice and the
    // connector not at all. This one stays FATAL, and not because of the connector rule: an example
    // that is any card's text word for word is `day.example_is_a_term`, which never moved. The
    // connector warning names it too, so the log says which of the two problems it is.
    $broken = candidate('s2-day1.v0.3.json', 's2-outline.v0.2.json', 3, withCard(12, [
        'example' => 'I deal with the payment module every day.',
    ]));

    expect(dayCodes($this->validator->validate($broken)))
        ->toContain(PlanDayValidator::EXAMPLE_IS_A_TERM)
        ->and(dayCodes($this->validator->warnings($broken)))
        ->toContain(PlanDayValidator::CHUNK_OUTSIDE_FRAME);
});

it('WARNS about a connector no frame of the day contains at all', function () {
    $off = candidate('s2-day1.v0.3.json', 's2-outline.v0.2.json', 3, withCard(12, [
        'text' => 'roll out',
        'translation' => 'выкатывать',
        'transliteration' => 'роул аут',
        'example' => 'We roll out changes on Thursdays.',
        'exampleTranslation' => 'Мы выкатываем изменения по четвергам.',
    ]));

    expect($this->validator->validate($off))->toBe([])
        ->and(dayCodes($this->validator->warnings($off)))
        ->toContain(PlanDayValidator::CHUNK_OUTSIDE_FRAME);
});

it('warns about the live sentence that cost a whole day — one dropped «Sorry,»', function () {
    // `docs/research/plan-v0.3-run.md`: frame «Sorry, the connection is ___», connector «breaking
    // up», example «The connection is breaking up again on my side.» — this day's situation, not a
    // clone of the line, arrived at by fixing the clone the previous attempt had. It was refused
    // for the missing «Sorry,». Now it is written, and the log says what it cost.
    $off = candidate('s2-day1.v0.3.json', 's2-outline.v0.2.json', 3, static function (array $items): array {
        $items = withCard(2, [
            'frame' => 'Sorry, the connection is ___.',
            'filler' => 'unstable',
        ])($items);

        return withCard(12, [
            'text' => 'breaking up',
            'translation' => 'прерывается',
            'transliteration' => 'брейкин ап',
            'description' => 'When a call keeps cutting out and words go missing.',
            'example' => 'The connection is breaking up again on my side.',
            'exampleTranslation' => 'Связь снова прерывается с моей стороны.',
        ])($items);
    });

    expect($this->validator->validate($off))->toBe([])
        ->and(dayCodes($this->validator->warnings($off)))
        ->toContain(PlanDayValidator::CHUNK_OUTSIDE_FRAME);
});

it('warns when the interlocutor never says a word, though the scene has lines to quote', function () {
    // The live v0.3 day: eight lines, all the learner's, while the skeleton's scene had three
    // `opening_lines` nobody quoted — and the day's first line answered a question that was not in
    // it. The CEILING on role lines has always been a quarter; this is the floor.
    $silent = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, static function (array $items): array {
        $items = withCard(0, ['speaker' => PlanDayItem::SPEAKER_LEARNER])($items);

        return withCard(5, ['speaker' => PlanDayItem::SPEAKER_LEARNER])($items);
    });

    expect($this->validator->validate($silent))->toBe([])
        ->and(dayCodes($this->validator->warnings($silent)))->toContain(PlanDayValidator::NO_ROLE_LINE);
});

it('says nothing about a silent interlocutor when the day has nobody to talk to', function () {
    // A scene of reading forms alone has no role and no `opening_lines`. «Nobody spoke» is not a
    // defect when there is nobody.
    $day = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3);
    $nobody = new PlanDayCandidate(
        supportLang: $day->supportLang,
        targetLang: $day->targetLang,
        termBudget: $day->termBudget,
        phraseCount: $day->phraseCount,
        chunkCount: $day->chunkCount,
        wordCount: $day->wordCount,
        checkpointCount: 3,
        goalTerms: [],
        openingLines: [],
        items: array_map(
            static fn (PlanDayItem $i): PlanDayItem => $i->kind === PlanDayItem::KIND_LINE
                ? new PlanDayItem(
                    text: $i->text, type: $i->type, kind: $i->kind, isLine: true,
                    translation: $i->translation, transliteration: $i->transliteration,
                    description: $i->description, example: $i->example,
                    exampleTranslation: $i->exampleTranslation, frame: $i->frame, filler: $i->filler,
                    speaker: PlanDayItem::SPEAKER_LEARNER, imageApiPrompt: $i->imageApiPrompt,
                    coversCheckpoint: $i->coversCheckpoint,
                )
                : $i,
            $day->items,
        ),
    );

    expect(dayCodes($this->validator->warnings($nobody)))->not->toContain(PlanDayValidator::NO_ROLE_LINE);
});

// ── the warnings: counted, never fatal ────────────────────────────────────────────────────────

it('warns about the formula cap as a third rounded UP, and never refuses over it', function (int $lines, int $cap) {
    // 8 → 3, 4 → 2, 14 → 5. Rounded up since v0.3, and no longer fatal: the live day had three
    // right formulas out of eight and was refused for it, twice, at $0.05 each.
    $day = fn (int $formulas): PlanDayCandidate => syntheticDay($lines, $formulas);

    expect(dayCodes($this->validator->warnings($day($cap))))->not->toContain(PlanDayValidator::FORMULA_CAP)
        ->and(dayCodes($this->validator->warnings($day($cap + 1))))->toContain(PlanDayValidator::FORMULA_CAP)
        ->and(dayCodes($this->validator->validate($day($cap + 1))))->not->toContain(PlanDayValidator::FORMULA_CAP);
})->with([
    [8, 3],
    [4, 2],
    [14, 5],
]);

it('warns when no line of the learner`s is a question', function () {
    // The live day was eight «I…» statements in a row. It reads as a questionnaire, and the thing
    // it fails to train is the one the learner will need.
    $day = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(3, [
        'frame' => 'I would like to check in, please.',
    ]));

    expect(dayCodes($this->validator->warnings($day)))->toContain(PlanDayValidator::NO_QUESTION)
        ->and(dayCodes($this->validator->validate($day)))->not->toContain(PlanDayValidator::NO_QUESTION);
});

it('does not count the interlocutor`s question as the learner asking one', function () {
    // s1 has «Do you have an appointment?» and «Where does it hurt?» — both the role's. Recognising
    // a question is a different ability from asking one.
    $day = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(3, [
        'frame' => 'I would like to check in, please.',
    ]));

    expect(dayCodes($this->validator->warnings($day)))->toContain(PlanDayValidator::NO_QUESTION);
});

it('warns when no line is a repair move, and stays quiet where no list is configured', function () {
    $withoutRepair = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(3, [
        'frame' => 'Could you say the time again?',   // a question, and not on the marker list
    ]));

    expect(dayCodes($this->validator->warnings($withoutRepair)))
        ->toContain(PlanDayValidator::NO_REPAIR)
        ->not->toContain(PlanDayValidator::NO_QUESTION);

    // No list for this target language means the question was never asked of it. Romanian has an
    // empty list in `config/generation.php`, and a Romanian day is never warned about a repair.
    $romanian = candidate('s3-day1.v0.3.json', 's3-outline.v0.2.json', 3, targetLang: 'ro');
    expect(dayCodes($this->validator->warnings($romanian)))->not->toContain(PlanDayValidator::NO_REPAIR);
});

it('reads the repair list it was given, and nothing else', function () {
    $narrow = new PlanDayValidator(repairMarkers: ['en' => ['one moment']]);
    $day = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3);

    // s1's repair line is «Could you repeat that, please?» — a repair by the default list and not
    // by this one.
    expect(dayCodes($narrow->warnings($day)))->toContain(PlanDayValidator::NO_REPAIR)
        ->and(dayCodes($this->validator->warnings($day)))->not->toContain(PlanDayValidator::NO_REPAIR);
});

// ── the kinds ─────────────────────────────────────────────────────────────────────────────────

it('refuses a line with no speaker', function () {
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(1, ['speaker' => null]));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::KIND_MISMATCH);
});

it('refuses a connector that claims to be a single word', function () {
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(12, ['type' => 'word']));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::KIND_MISMATCH);
});

// ── the checkpoints ───────────────────────────────────────────────────────────────────────────

it('refuses a day with a checkpoint no line closes', function () {
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, static function (array $items): array {
        // Checkpoint 2 is closed by exactly one line; move it and nothing closes it.
        return withCard(4, ['coversCheckpoint' => 1])($items);
    });

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::CHECKPOINT_UNCOVERED);
});

it('refuses a substitution marked as closing a checkpoint', function () {
    // A checkpoint is a thing that must be SAID; marking a noun as closing one says the learner can
    // tick it by knowing vocabulary, and they cannot.
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, static function (array $items): array {
        $item = $items[8];
        $items[8] = new PlanDayItem(
            text: $item->text, type: $item->type, kind: $item->kind, isLine: false,
            translation: $item->translation, transliteration: $item->transliteration,
            description: $item->description, example: $item->example,
            exampleTranslation: $item->exampleTranslation, frame: '', filler: '', speaker: null,
            imageApiPrompt: $item->imageApiPrompt, coversCheckpoint: 3,
        );

        return $items;
    });

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::CHECKPOINT_ON_WORD);
});

// ── the pictures ──────────────────────────────────────────────────────────────────────────────

it('refuses a card with nothing to draw', function () {
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(3, ['imageApiPrompt' => '']));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::IMAGE_PROMPT_MISSING);
});

// ── examples and keys, unchanged since v0.1 ───────────────────────────────────────────────────

it('refuses an example that is another card`s line, word for word', function () {
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(9, [
        'example' => "I'm here because of back pain.",
    ]));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::EXAMPLE_IS_A_TERM);
});

it('refuses two cards sharing one example', function () {
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(9, [
        'example' => 'It hurts in my lower back, right here.',
    ]));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::EXAMPLE_DUPLICATED);
});

it('refuses two cards sharing one key', function () {
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(9, [
        'translation' => 'поясница',
    ]));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::KEY_DUPLICATED);
});

it('refuses a key written in the language being learned', function () {
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(8, [
        'translation' => 'the lower part of the back',
    ]));

    expect(dayCodes($this->validator->validate($broken)))
        ->toContain(PlanDayValidator::KEY_NOT_SUPPORT_LANGUAGE);
});

it('leaves an abbreviation, a code and a goal term alone in a key', function (string $translation, array $goalTerms) {
    $ok = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(8, [
        'translation' => $translation,
    ]), goalTerms: $goalTerms);

    expect($this->validator->validate($ok))->toBe([]);
})->with([
    ['снимок MRI поясницы', []],
    ['место 14A в очереди', []],
    ['поясница по Laravel-методике', ['Laravel']],
]);

// ── the reading hint: repaired, never fatal ───────────────────────────────────────────────────

it('never fails a day over a reading hint, however broken it is', function (?string $hint) {
    $day = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(2, [
        'transliteration' => $hint,
    ]));

    expect($this->validator->validate($day))->toBe([]);
})->with(['', 'my name is denis', 'мaй нэйм', null]);

it('repairs a hint that only picked up sentence punctuation', function () {
    expect($this->validator->transliterationFor('ru', 'куд ю клэрифай уич проджект ю мин?'))
        ->toBe('куд ю клэрифай уич проджект ю мин');
});

it('refuses to repair a hint written in the wrong alphabet, so the caller can drop it', function () {
    expect($this->validator->transliterationFor('ru', 'ай уоз риспонсибл фо зэ API'))->toBeNull()
        ->and($this->validator->transliterationFor('ru', ''))->toBeNull();
});

// ── the address: where the defect is, and nothing the model wrote ─────────────────────────────

it('addresses a card violation by array, index and field', function () {
    // «phrases[1].translation» — the whole of what a repair call is given to work with. The index
    // is the card's position in the array the model wrote it in, which is what P2R merges by.
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(1, [
        'translation' => 'У меня приём в ___.',
    ]));

    $slot = array_values(array_filter(
        $this->validator->validate($broken),
        static fn (PlanViolation $v): bool => $v->code === PlanDayValidator::SLOT_OUTSIDE_FRAME,
    ))[0];

    expect($slot->isAddressed())->toBeTrue()
        ->and($slot->array)->toBe('phrases')
        ->and($slot->index)->toBe(1)
        ->and($slot->field)->toBe('translation')
        ->and($slot->reason)->not->toBe('')
        ->and($slot->address())->toBe(
            'phrases[1].translation — day.slot_outside_frame: `translation` carries a `___` — the '
            . 'slot lives in `frame` and nowhere else; this field is about the FULL assembled line',
        );
});

it('quotes NOTHING the model wrote in the address, however loudly the detail does', function () {
    // The finding that cost $0.05 and this whole наряд: a retry told «day.example_is_a_term
    // [Right now, I am a backend developer.]» answered WITH that sentence
    // (`docs/research/plan-v0.3-run.md`, второй заход). The prose still carries the card, because a
    // person reads `fail_reason`; the address may not, because a model reads that.
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(9, [
        'example' => "I'm here because of back pain.",
    ]));

    $clone = array_values(array_filter(
        $this->validator->validate($broken),
        static fn (PlanViolation $v): bool => $v->code === PlanDayValidator::EXAMPLE_IS_A_TERM,
    ))[0];

    expect($clone->address())->toBe(
        'words[1].example — day.example_is_a_term: the `example` is, word for word, a card of this '
        . 'day rather than a sentence containing one',
    )
        ->and($clone->address())->not->toContain('back pain')
        // …and the OTHER card, the one it clones, is not named either — that is the sentence the
        // model would have copied back.
        ->and($clone->address())->not->toContain("I'm here")
        // The Russian prose is the human's, and it names both.
        ->and((string) $clone)->toContain('back pain');
});

it('leaves a violation about the ANSWER unaddressed, because no card is to blame for it', function () {
    // A count, an uncovered checkpoint, a proportion: nothing a repair call could be pointed at, so
    // these are what send a day back WHOLE ({@see PlanDayRepairer}).
    $day = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3);
    $short = new PlanDayCandidate(
        supportLang: 'ru', targetLang: 'en', termBudget: 14, phraseCount: 9,
        chunkCount: $day->chunkCount, wordCount: $day->wordCount, checkpointCount: 3,
        goalTerms: [], openingLines: $day->openingLines, items: $day->items,
    );

    $count = array_values(array_filter(
        $this->validator->validate($short),
        static fn (PlanViolation $v): bool => $v->code === PlanDayValidator::ARRAY_COUNT,
    ))[0];

    expect($count->isAddressed())->toBeFalse()
        ->and($count->array)->toBeNull()
        ->and($count->index)->toBeNull()
        ->and($count->address())->toStartWith('day — day.array_count: ');
});

it('gives every fatal violation of a broken day an English reason', function () {
    // A reason is what a repair call reads instead of the card it must not see. A fatal violation
    // with an empty one would reach P2R as a bare code, and «day.kind_mismatch» alone does not say
    // what to change.
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, static function (array $items): array {
        $items = withCard(1, ['imageApiPrompt' => '', 'translation' => 'У меня приём в ___.'])($items);

        return withCard(9, ['speaker' => PlanDayItem::SPEAKER_LEARNER])($items);
    });

    $violations = $this->validator->validate($broken);

    expect($violations)->not->toBe([]);
    foreach ($violations as $violation) {
        expect($violation->reason)->not->toBe('');
    }
});

it('knows when a reading hint is mandatory at all', function () {
    expect($this->validator->scriptsDiffer('ru', 'en'))->toBeTrue()
        ->and($this->validator->scriptsDiffer('ru', 'ro'))->toBeTrue()
        ->and($this->validator->scriptsDiffer('en', 'ro'))->toBeFalse();
});

// ── the name on a card of its own ─────────────────────────────────────────────────────────────

it('refuses a card whose term is a name out of the skeleton', function () {
    // The owner's phone, 31.08: «Ivanov», reading «[иванов]», translation «Иванов», example «My
    // last name is Ivanov, yes.» — a surname dealt as a word to learn. There is nothing in it to
    // know, and the answer is the question written in the other alphabet.
    $day = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(8, [
        'text' => 'Ivanov',
        'translation' => 'фамилия гостя',
        'transliteration' => 'иванов',
        'example' => 'My last name is Ivanov, yes.',
    ]));

    $withEntity = new PlanDayCandidate(
        supportLang: 'ru', targetLang: 'en', termBudget: $day->termBudget,
        phraseCount: $day->phraseCount, chunkCount: $day->chunkCount, wordCount: $day->wordCount,
        checkpointCount: 3, goalTerms: [], openingLines: $day->openingLines, items: $day->items,
        entityNames: ['Ivanov'],
    );

    expect(dayCodes($this->validator->validate($withEntity)))->toContain(PlanDayValidator::TERM_IS_A_NAME);
});

it('refuses a card whose term is one of the learner`s own goal terms', function () {
    // Spelled verbatim in both languages by construction, so a card of one asks nothing.
    $day = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(8, [
        'text' => 'Laravel',
        'translation' => 'фреймворк',
        'example' => 'It hurts in my Laravel, right here.',
    ]), goalTerms: ['Laravel']);

    expect(dayCodes($this->validator->validate($day)))->toContain(PlanDayValidator::TERM_IS_A_NAME);
});

it('lets the name stand in a LINE, which is the whole point of the rule', function () {
    // «My last name is ___» + «Ivanov» is the sentence worth having. The rule exists so that this
    // keeps working, not in spite of it.
    $day = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3);
    $withEntity = new PlanDayCandidate(
        supportLang: 'ru', targetLang: 'en', termBudget: $day->termBudget,
        phraseCount: $day->phraseCount, chunkCount: $day->chunkCount, wordCount: $day->wordCount,
        checkpointCount: 3, goalTerms: [], openingLines: $day->openingLines, items: $day->items,
        entityNames: ['доктор Ионеску'],
    );

    expect($this->validator->validate($withEntity))->toBe([]);
});

// ── the key that is the term in the other alphabet ────────────────────────────────────────────

it('refuses a key that is the term transliterated', function (string $term, string $key) {
    $broken = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(8, [
        'text' => $term,
        'translation' => $key,
        'example' => "It hurts in my {$term}, right here.",
    ]));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::KEY_IS_THE_TERM);
})->with([
    'the live one' => ['Ivanov', 'Иванов'],
    'a digraph' => ['Shchukin', 'Щукин'],
    'the soft sign vanishes' => ['Olga', 'Ольга'],
]);

it('leaves a borrowing alone even when its translation IS its reading', function () {
    // Measured on the owner's own live day: «passport» is glossed «паспорт», which is both the
    // correct Russian word and — by accident of the borrowing — its own pronunciation hint. A
    // check on «is the key the reading» refused that card. The name it was meant to catch is
    // caught by the skeleton instead: «паспорт» → `pasport` and «passport` → `passport` are two
    // words, while «Иванов» → `ivanov` and «Ivanov» are one.
    $ok = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(8, [
        'text' => 'passport',
        'translation' => 'паспорт',
        'transliteration' => 'паспорт',
        'example' => 'It hurts in my passport, right here.',
    ]));

    expect(dayCodes($this->validator->validate($ok)))->not->toContain(PlanDayValidator::KEY_IS_THE_TERM);
});

it('leaves an honest translation alone, however close the two words sound', function (string $term, string $key) {
    $ok = candidate('s1-day1.v0.3.json', 's1-outline.v0.2.json', 3, withCard(8, [
        'text' => $term,
        'translation' => $key,
        'example' => "It hurts in my {$term}, right here.",
    ]));

    expect(dayCodes($this->validator->validate($ok)))->not->toContain(PlanDayValidator::KEY_IS_THE_TERM);
})->with([
    'a borrowing that is really translated' => ['manager', 'руководитель'],
    'nothing alike' => ['passport', 'паспорт документ'],
]);

<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\PlanDayValidator;
use App\Modules\Generation\Domain\ValueObject\PlanDayCandidate;
use App\Modules\Generation\Domain\ValueObject\PlanDayItem;
use App\Modules\Generation\Domain\ValueObject\PlanViolation;

/**
 * The gate that decides whether a paid day is written or paid for again.
 *
 * The positive cases run on the hand-written v0.2 days in `tests/Fixtures/plan`, not on synthetic
 * material, and that is the point: a gate which refuses a good day costs more than one which lets
 * a weak day through — the second is caught by reading, the first burns money on regenerations and
 * then gets switched off. The negatives are one per rule, each breaking exactly one thing.
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
        foreach ($day[$key] ?? [] as $card) {
            $isLine = $kind === PlanDayItem::KIND_LINE;
            $out[] = new PlanDayItem(
                text: $card['text'],
                type: $card['type'],
                kind: $kind,
                isLine: $isLine,
                translation: $card['translation'],
                transliteration: $card['transliteration'] ?? '',
                description: $card['description'],
                example: $card['example'],
                exampleTranslation: $card['example_translation'],
                frame: $isLine ? ($card['frame'] ?? '') : '',
                speaker: $isLine ? ($card['speaker'] ?? null) : null,
                imageApiPrompt: $card['image_api_prompt'] ?? '',
                coversCheckpoint: $isLine ? ($card['covers_checkpoint'] ?? null) : null,
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

function dayCodes(array $violations): array
{
    return array_values(array_unique(array_map(static fn (PlanViolation $v): string => $v->code, $violations)));
}

/** Replace the item at `$at` with a copy carrying `$overrides`. */
function withCard(int $at, array $overrides): callable
{
    return static function (array $items) use ($at, $overrides): array {
        $item = $items[$at];
        $items[$at] = new PlanDayItem(
            text: $overrides['text'] ?? $item->text,
            type: $overrides['type'] ?? $item->type,
            kind: $overrides['kind'] ?? $item->kind,
            isLine: $overrides['isLine'] ?? $item->isLine,
            translation: $overrides['translation'] ?? $item->translation,
            transliteration: $overrides['transliteration'] ?? $item->transliteration,
            description: $overrides['description'] ?? $item->description,
            example: $overrides['example'] ?? $item->example,
            exampleTranslation: $overrides['exampleTranslation'] ?? $item->exampleTranslation,
            frame: $overrides['frame'] ?? $item->frame,
            speaker: array_key_exists('speaker', $overrides) ? $overrides['speaker'] : $item->speaker,
            imageApiPrompt: $overrides['imageApiPrompt'] ?? $item->imageApiPrompt,
            coversCheckpoint: array_key_exists('coversCheckpoint', $overrides)
                ? $overrides['coversCheckpoint']
                : $item->coversCheckpoint,
        );

        return $items;
    };
}

// ── the positives ─────────────────────────────────────────────────────────────────────────────

it('passes the hand-written v0.2 days', function (string $day, string $outline, int $checkpoints) {
    expect($this->validator->validate(candidate($day, $outline, $checkpoints)))->toBe([]);
})->with([
    ['s1-day1.v0.2.json', 's1-outline.v0.2.json', 3],
    ['s2-day1.v0.2.json', 's2-outline.v0.2.json', 3],
    ['s3-day1.v0.2.json', 's3-outline.v0.2.json', 3],
]);

it('passes the Romanian day too, where the reading hint is what the learner reads by', function () {
    expect($this->validator->validate(
        candidate('s3-day1.v0.2.json', 's3-outline.v0.2.json', 3, supportLang: 'ru', targetLang: 'ro'),
    ))->toBe([]);
});

// ── the three numbers ─────────────────────────────────────────────────────────────────────────

it('refuses a day that is one card off the count it was asked for', function () {
    $day = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3);
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

// ── the frames ────────────────────────────────────────────────────────────────────────────────

it('refuses a line that is not its own frame with a word in the hole', function () {
    $broken = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3, withCard(1, [
        'text' => 'I will be there in the morning.',   // nothing to do with «I have an appointment at ___»
    ]));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::FRAME_MISMATCH);
});

it('accepts a line whose frame is filled with a longer phrase', function () {
    $ok = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3, withCard(1, [
        'text' => 'I have an appointment at half past nine in the morning.',
    ]));

    expect($this->validator->validate($ok))->toBe([]);
});

it('refuses a day that is more than a third fixed formulas', function () {
    // Three of eight lines with no slot: the day teaches sentences the learner can say and nothing
    // they can say next.
    $broken = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3, static function (array $items): array {
        return withCard(2, ['frame' => ''])($items);
    });

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::FRAME_SHARE);
});

it('refuses an interlocutor line the skeleton never promised', function () {
    $broken = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3, withCard(0, [
        'text' => 'Have you been here before?',   // plausible, and not in `opening_lines`
    ]));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::ROLE_LINE_INVENTED);
});

it('refuses a day where more than a quarter of the lines belong to the interlocutor', function () {
    $broken = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3, withCard(7, [
        'text' => 'How long has it been like this?',   // verbatim from the skeleton, but one too many
        'frame' => '',
        'speaker' => PlanDayItem::SPEAKER_ROLE,
        'coversCheckpoint' => null,
    ]));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::ROLE_LINE_SHARE);
});

// ── the substitutions ─────────────────────────────────────────────────────────────────────────

it('refuses a word that stands in no frame of the day', function () {
    // The rule the whole of v0.2 turns on: a word whose example is not one of the day's frames with
    // that word in the slot is a glossary entry sitting next to the conversation.
    $broken = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3, withCard(8, [
        'text' => 'physiotherapy',
        'translation' => 'физиотерапия',
        'example' => 'Physiotherapy usually helps with this.',
    ]));

    expect(dayCodes($this->validator->validate($broken)))
        ->toContain(PlanDayValidator::SUBSTITUTION_WITHOUT_FRAME);
});

// ── the kinds ─────────────────────────────────────────────────────────────────────────────────

it('refuses a line with no speaker', function () {
    $broken = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3, withCard(1, ['speaker' => null]));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::KIND_MISMATCH);
});

it('refuses a connector that claims to be a single word', function () {
    $broken = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3, withCard(12, ['type' => 'word']));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::KIND_MISMATCH);
});

// ── the checkpoints ───────────────────────────────────────────────────────────────────────────

it('refuses a day with a checkpoint no line closes', function () {
    $broken = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3, static function (array $items): array {
        // Checkpoint 2 is closed by exactly one line; move it and nothing closes it.
        return withCard(4, ['coversCheckpoint' => 1])($items);
    });

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::CHECKPOINT_UNCOVERED);
});

it('refuses a substitution marked as closing a checkpoint', function () {
    // A checkpoint is a thing that must be SAID; marking a noun as closing one says the learner can
    // tick it by knowing vocabulary, and they cannot.
    $broken = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3, static function (array $items): array {
        $item = $items[8];
        $items[8] = new PlanDayItem(
            text: $item->text, type: $item->type, kind: $item->kind, isLine: false,
            translation: $item->translation, transliteration: $item->transliteration,
            description: $item->description, example: $item->example,
            exampleTranslation: $item->exampleTranslation, frame: '', speaker: null,
            imageApiPrompt: $item->imageApiPrompt, coversCheckpoint: 3,
        );

        return $items;
    });

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::CHECKPOINT_ON_WORD);
});

// ── the pictures ──────────────────────────────────────────────────────────────────────────────

it('refuses a card with nothing to draw', function () {
    $broken = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3, withCard(3, ['imageApiPrompt' => '']));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::IMAGE_PROMPT_MISSING);
});

// ── examples and keys, unchanged since v0.1 ───────────────────────────────────────────────────

it('refuses an example that is another card`s line, word for word', function () {
    $broken = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3, withCard(9, [
        'example' => 'I have an appointment at ten.',
    ]));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::EXAMPLE_IS_A_TERM);
});

it('refuses two cards sharing one example', function () {
    $broken = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3, withCard(9, [
        'example' => 'It hurts in my lower back most of the day.',
    ]));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::EXAMPLE_DUPLICATED);
});

it('refuses two cards sharing one key', function () {
    $broken = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3, withCard(9, [
        'translation' => 'поясница',
    ]));

    expect(dayCodes($this->validator->validate($broken)))->toContain(PlanDayValidator::KEY_DUPLICATED);
});

it('refuses a key written in the language being learned', function () {
    $broken = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3, withCard(8, [
        'translation' => 'the lower part of the back',
    ]));

    expect(dayCodes($this->validator->validate($broken)))
        ->toContain(PlanDayValidator::KEY_NOT_SUPPORT_LANGUAGE);
});

it('leaves an abbreviation, a code and a goal term alone in a key', function (string $translation, array $goalTerms) {
    $ok = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3, withCard(8, [
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
    $day = candidate('s1-day1.v0.2.json', 's1-outline.v0.2.json', 3, withCard(2, [
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

it('knows when a reading hint is mandatory at all', function () {
    expect($this->validator->scriptsDiffer('ru', 'en'))->toBeTrue()
        ->and($this->validator->scriptsDiffer('ru', 'ro'))->toBeTrue()
        ->and($this->validator->scriptsDiffer('en', 'ro'))->toBeFalse();
});

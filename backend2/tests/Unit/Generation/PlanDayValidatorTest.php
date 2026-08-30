<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\PlanDayValidator;
use App\Modules\Generation\Domain\ValueObject\PlanDayCandidate;
use App\Modules\Generation\Domain\ValueObject\PlanDayItem;
use App\Modules\Generation\Domain\ValueObject\PlanViolation;

beforeEach(fn () => $this->validator = new PlanDayValidator());

/** Load one of the four real model answers from tests/Fixtures/plan. */
function planFixture(string $name, string $supportLang, string $targetLang, int $checkpoints, array $goalTerms = []): PlanDayCandidate
{
    $raw = json_decode((string) file_get_contents(__DIR__ . '/../../Fixtures/plan/' . $name), true);

    $items = [];
    foreach ([...$raw['phrases'], ...$raw['words']] as $card) {
        $items[] = new PlanDayItem(
            text: $card['text'],
            type: $card['type'],
            isLine: $card['is_line'],
            translation: $card['translation'],
            transliteration: $card['transliteration'],
            description: $card['description'],
            example: $card['example'],
            exampleTranslation: $card['example_translation'],
            coversCheckpoint: $card['covers_checkpoint'],
        );
    }

    return new PlanDayCandidate(
        supportLang: $supportLang,
        targetLang: $targetLang,
        termBudget: count($items),
        checkpointCount: $checkpoints,
        goalTerms: $goalTerms,
        items: $items,
    );
}

function codes(array $violations): array
{
    return array_values(array_unique(array_map(static fn (PlanViolation $v): string => $v->code, $violations)));
}

function planItem(array $overrides = []): PlanDayItem
{
    $d = [
        'text' => 'my back hurts', 'type' => 'phrase', 'is_line' => true,
        'translation' => 'спина болит', 'transliteration' => 'май бэк хёртс',
        'description' => 'You say this when something aches.',
        'example' => 'My back hurts in the morning.', 'example_translation' => 'Спина болит по утрам.',
        'covers_checkpoint' => 1,
    ];
    $d = [...$d, ...$overrides];

    return new PlanDayItem(
        $d['text'], $d['type'], $d['is_line'], $d['translation'], $d['transliteration'],
        $d['description'], $d['example'], $d['example_translation'], $d['covers_checkpoint'],
    );
}

function planDay(array $items, int $checkpoints = 1, array $goalTerms = [], string $support = 'ru'): PlanDayCandidate
{
    return new PlanDayCandidate($support, 'en', count($items), $checkpoints, $goalTerms, $items);
}

// ── the four real days ────────────────────────────────────────────────────────────────────────

it('passes S1 day 1 — «Начать приём и описать боль», ru→en, 9 terms, 3 checkpoints', function () {
    $violations = $this->validator->validate(planFixture('s1-day1.v0.1.json', 'ru', 'en', 3));

    expect($violations)->toBe([]);
});

it('passes S1 day 2 — «Уточнить симптомы и помощь», ru→en, 9 terms, 3 checkpoints', function () {
    $violations = $this->validator->validate(planFixture('s1-day2.v0.1.json', 'ru', 'en', 3));

    expect($violations)->toBe([]);
});

it('passes S2 day 1 — «Пройти основные этапы интервью», ru→en, 16 terms, PHP/API as goal terms', function () {
    // The five fields the live purity gate used to flag are the ones carrying `PHP`, `API` and
    // `QA` in a Russian key — which is the only correct way to write them (§7.3). The exemption
    // is what makes this day pass, and without it this test is the proof it would not.
    $violations = $this->validator->validate(
        planFixture('s2-day1.v0.1.json', 'ru', 'en', 3, ['PHP', 'API', 'QA']),
    );

    expect($violations)->toBe([]);
});

it('passes S3 — «Открыть визит и понять назначение», ru→ro, 9 terms', function () {
    $violations = $this->validator->validate(planFixture('s3-day1.v0.1.json', 'ru', 'ro', 3));

    expect($violations)->toBe([]);
});

it('passes S2 even with no goal_terms at all — its Latin is abbreviations', function () {
    // The day's whole Latin vocabulary is `PHP`, `API` and `QA`: two-to-five capitals, exempt by
    // SHAPE. Which is the point of the shape rule — `QA` was never in `goal_terms` because the
    // learner never typed it, and under the earlier, narrower rule this day's keys were violations
    // for being spelled the only correct way.
    $violations = $this->validator->validate(planFixture('s2-day1.v0.1.json', 'ru', 'en', 3));

    expect($violations)->toBe([]);
});

// ── rule 1: the reply share ───────────────────────────────────────────────────────────────────

it('accepts the ceil(0.45 × budget) split at an odd budget, where it lands at 55.6%', function () {
    // 9 terms → the server asks for 5 replies. 5/9 = 55.6%, above the canon band of 40–50% and
    // inside this gate's 35–55. A gate that fired on our own instruction would be the wrong gate.
    $items = [];
    for ($i = 1; $i <= 5; $i++) {
        $items[] = planItem(['text' => "line {$i}", 'translation' => "реплика {$i}", 'example' => "Line {$i} happens.", 'covers_checkpoint' => 1]);
    }
    for ($i = 1; $i <= 4; $i++) {
        $items[] = planItem(['text' => "word{$i}", 'type' => 'word', 'is_line' => false, 'translation' => "слово {$i}", 'example' => "A word{$i} appears.", 'covers_checkpoint' => null]);
    }

    expect($this->validator->validate(planDay($items)))->toBe([]);
});

it('refuses a day that is a vocabulary list with an event date attached', function () {
    // The v0 failure: 5 replies of 16 — 31.3%.
    $items = [planItem(['text' => 'a line', 'translation' => 'реплика', 'example' => 'A line happens.'])];
    for ($i = 1; $i <= 9; $i++) {
        $items[] = planItem(['text' => "word{$i}", 'type' => 'word', 'is_line' => false, 'translation' => "слово {$i}", 'example' => "A word{$i} appears.", 'covers_checkpoint' => null]);
    }

    expect(codes($this->validator->validate(planDay($items))))->toBe([PlanDayValidator::LINE_SHARE]);
});

// ── rule 2/3: checkpoints ─────────────────────────────────────────────────────────────────────

it('refuses a day whose checkpoint no reply can tick — the one failure it cannot ship', function () {
    $items = [
        planItem(['text' => 'line one', 'translation' => 'первая', 'example' => 'Line one happens.', 'covers_checkpoint' => 1]),
        planItem(['text' => 'word', 'type' => 'word', 'is_line' => false, 'translation' => 'слово', 'example' => 'A word appears.', 'covers_checkpoint' => null]),
    ];

    $violations = $this->validator->validate(planDay($items, checkpoints: 2));

    expect(codes($violations))->toContain(PlanDayValidator::CHECKPOINT_UNCOVERED)
        ->and($violations[array_search(PlanDayValidator::CHECKPOINT_UNCOVERED, array_map(fn ($v) => $v->code, $violations), true)]->detail)
        ->toContain('чек-пойнт 2');
});

it('refuses a substitution that claims to close a checkpoint', function () {
    $items = [
        planItem(['text' => 'line one', 'translation' => 'первая', 'example' => 'Line one happens.', 'covers_checkpoint' => 1]),
        planItem(['text' => 'word', 'type' => 'word', 'is_line' => false, 'translation' => 'слово', 'example' => 'A word appears.', 'covers_checkpoint' => 1]),
    ];

    expect(codes($this->validator->validate(planDay($items))))->toContain(PlanDayValidator::CHECKPOINT_ON_WORD);
});

it('refuses a checkpoint index the day does not have', function () {
    $items = [planItem(['covers_checkpoint' => 7])];

    expect(codes($this->validator->validate(planDay($items))))
        ->toContain(PlanDayValidator::CHECKPOINT_OUT_OF_RANGE);
});

// ── rule 4/5: examples ────────────────────────────────────────────────────────────────────────

it('refuses an example that is another card text verbatim — the clone v0 lost', function () {
    $items = [
        planItem(['text' => "It's been like this for a week.", 'translation' => 'Так уже неделю.', 'example' => "It's been like this for a week, and it still hurts."]),
        planItem(['text' => 'week', 'type' => 'word', 'is_line' => false, 'translation' => 'неделя',
            'example' => "It's been like this for a week.", 'covers_checkpoint' => null]),
    ];

    expect(codes($this->validator->validate(planDay($items))))->toContain(PlanDayValidator::EXAMPLE_IS_A_TERM);
});

it('refuses two cards sharing one example', function () {
    $items = [
        planItem(['text' => 'pain', 'type' => 'word', 'is_line' => false, 'translation' => 'боль', 'example' => 'The pain is in my lower back.', 'covers_checkpoint' => null]),
        planItem(['text' => 'lower back', 'type' => 'phrase', 'is_line' => false, 'translation' => 'поясница', 'example' => 'The pain is in my lower back.', 'covers_checkpoint' => null]),
        planItem(['text' => 'a line', 'translation' => 'реплика', 'example' => 'A line happens.']),
    ];

    expect(codes($this->validator->validate(planDay($items))))->toContain(PlanDayValidator::EXAMPLE_DUPLICATED);
});

it('refuses an empty example', function () {
    expect(codes($this->validator->validate(planDay([planItem(['example' => '  '])]))))
        ->toContain(PlanDayValidator::EXAMPLE_MISSING);
});

// ── rule 6: keys ──────────────────────────────────────────────────────────────────────────────

it('refuses a key that is its own term', function () {
    $items = [planItem(['text' => 'Zoom', 'translation' => 'Zoom'])];

    expect(codes($this->validator->validate(planDay($items))))->toContain(PlanDayValidator::KEY_IS_THE_TERM);
});

it('refuses two cards asking the same question', function () {
    $items = [
        planItem(['text' => 'line one', 'translation' => 'спина болит', 'example' => 'Line one happens.']),
        planItem(['text' => 'line two', 'translation' => 'Спина болит!', 'example' => 'Line two happens.']),
    ];

    expect(codes($this->validator->validate(planDay($items))))->toContain(PlanDayValidator::KEY_DUPLICATED);
});

// ── rule 7: the transliteration, repaired rather than thrown away ─────────────────────────────

it('strips the punctuation a reply hint picks up by reflex instead of losing the hint', function () {
    // The live gate refuses this outright (§7.2) and 5 of 16 hints died on one day for it.
    expect($this->validator->normalizedTransliteration('ru', 'куд ю клэрифай уич проджект ю мин?'))
        ->toBe('куд ю клэрифай уич проджект ю мин')
        ->and($this->validator->normalizedTransliteration('ru', 'тушеште де трей зиле, де кытева орь пе зи'))
        ->toBe('тушеште де трей зиле де кытева орь пе зи');
});

it('keeps the marks a spoken word really carries', function () {
    expect($this->validator->normalizedTransliteration('ru', 'чек-ин'))->toBe('чек-ин');
});

it('still refuses a hint with a letter from another alphabet, however clean the punctuation', function () {
    // «комо estás» is unreadable for exactly the reader the field exists for, and no amount of
    // stripping makes it readable.
    expect($this->validator->normalizedTransliteration('ru', 'комо estás'))->toBeNull()
        // The lookalike case: Armenian «ի» inside a Russian hint. Matches by eye, fails by letter.
        ->and($this->validator->normalizedTransliteration('ru', 'ինтёрнэл'))->toBeNull();
});

it('refuses a hint that annotates instead of transliterating', function () {
    expect($this->validator->normalizedTransliteration('ru', 'бэк [bæk]'))->toBeNull()
        ->and($this->validator->normalizedTransliteration('ru', 'уик 2'))->toBeNull();
});

it('reports a broken hint as a violation of the day, not as a silent drop', function () {
    $items = [planItem(['transliteration' => 'my back hurts'])];

    expect(codes($this->validator->validate(planDay($items))))
        ->toContain(PlanDayValidator::TRANSLITERATION_ALPHABET);
});

// ── rule 8: purity, with its two exemptions ───────────────────────────────────────────────────

it('lets a goal term stand in Latin inside a Russian key', function () {
    $items = [planItem([
        'text' => 'I was responsible for the API.',
        'translation' => 'Я отвечал за разработку API.',
        'example' => 'I was responsible for the API and its documentation.',
        'example_translation' => 'Я отвечал за API и его документацию.',
        'transliteration' => '',
    ])];

    // The line-share rule fires on a one-card day; the purity rule is what this test is about.
    expect(codes($this->validator->validate(planDay($items, goalTerms: ['API']))))
        ->not->toContain(PlanDayValidator::KEY_NOT_SUPPORT_LANGUAGE);
});

it('lets a card quote its OWN term in the key when the term is Latin', function () {
    $items = [planItem([
        'text' => 'backend', 'type' => 'word', 'is_line' => false, 'covers_checkpoint' => null,
        'translation' => 'бэкенд (backend)',
        'example' => 'I moved to backend work two years ago.',
        'example_translation' => 'Я перешёл в бэкенд два года назад.',
        'transliteration' => 'бэкенд',
    ])];

    // The line-share rule fires (no replies at all) — the purity rule does not, and that is what
    // this test is about.
    expect(codes($this->validator->validate(planDay($items, checkpoints: 0))))
        ->not->toContain(PlanDayValidator::KEY_NOT_SUPPORT_LANGUAGE);
});

it('still refuses a key that is simply in the wrong language', function () {
    $items = [planItem([
        'translation' => 'My back has been hurting for a week.',
        'example_translation' => 'Спина болит уже неделю.',
    ])];

    expect(codes($this->validator->validate(planDay($items))))
        ->toContain(PlanDayValidator::KEY_NOT_SUPPORT_LANGUAGE);
});

// ── rule 9 + 10 ───────────────────────────────────────────────────────────────────────────────

it('refuses a description that hands over its own term', function () {
    $items = [planItem([
        'text' => 'bank', 'type' => 'word', 'is_line' => false, 'covers_checkpoint' => null,
        'translation' => 'банк', 'transliteration' => 'бэнк',
        'description' => 'A bank is a place where you keep money.',
        'example' => 'I need to go to the bank today.', 'example_translation' => 'Мне нужно в банк сегодня.',
    ])];

    expect(codes($this->validator->validate(planDay($items, checkpoints: 0))))
        ->toContain(PlanDayValidator::DESCRIPTION_GIVES_AWAY);
});

it('refuses a day that came back short of the budget it was asked for', function () {
    $day = new PlanDayCandidate('ru', 'en', termBudget: 9, checkpointCount: 1, goalTerms: [], items: [planItem()]);

    expect(codes($this->validator->validate($day)))->toContain(PlanDayValidator::TERM_COUNT);
});

it('says nothing about an empty day beyond the count, rather than throwing', function () {
    $day = new PlanDayCandidate('ru', 'en', termBudget: 9, checkpointCount: 3, goalTerms: [], items: []);

    expect(codes($this->validator->validate($day)))->toBe([PlanDayValidator::TERM_COUNT]);
});

// ── abbreviations are allowed by SHAPE, with no list to keep ──────────────────────────────────

it('lets an abbreviation stand in Latin inside a Russian key, without being told about it', function () {
    // `QA` was never in `goal_terms` — the learner did not type it. The model produced it anyway,
    // correctly: «работаю с QA-инженерами» is the only way a Russian speaker writes that. Under a
    // rule that only knew the learner's own words, that key was a violation for being right.
    $items = [planItem([
        'text' => 'I usually work closely with QA engineers.',
        'translation' => 'Обычно я тесно работаю с QA-инженерами.',
        'example' => 'In a remote team, I work closely with QA to clarify issues quickly.',
        'example_translation' => 'В удалённой команде я тесно работаю с QA, чтобы быстро уточнять проблемы.',
        'transliteration' => '',
    ])];

    expect(codes($this->validator->validate(planDay($items))))
        ->not->toContain(PlanDayValidator::KEY_NOT_SUPPORT_LANGUAGE);
});

it('accepts two-to-five letters and nothing longer', function () {
    $key = function (string $russian): array {
        return codes($this->validator->validate(planDay([planItem([
            'text' => 'a line', 'translation' => $russian, 'example' => 'A line happens.',
            'example_translation' => 'Реплика случается.', 'transliteration' => '',
        ])])));
    };

    // Two through five: an abbreviation.
    expect($key('Работаю с QA каждый день'))->not->toContain(PlanDayValidator::KEY_NOT_SUPPORT_LANGUAGE)
        ->and($key('Отвечал за API и PHP'))->not->toContain(PlanDayValidator::KEY_NOT_SUPPORT_LANGUAGE)
        ->and($key('Пишу на HTML и REST'))->not->toContain(PlanDayValidator::KEY_NOT_SUPPORT_LANGUAGE)
        // Six is not an abbreviation any more — past five the run stops looking like one.
        ->and($key('Строка ABCDEFG внутри'))->toContain(PlanDayValidator::KEY_NOT_SUPPORT_LANGUAGE);
});

it('does not let a capitalised Latin word through as an abbreviation', function () {
    // One capital letter is a word, not an abbreviation. This is the case the shape rule must not
    // swallow, or the whole purity check stops meaning anything.
    $items = [planItem([
        'text' => 'a line',
        'translation' => 'Я сказал Hello вместо здравствуйте',
        'example' => 'A line happens.',
        'example_translation' => 'Реплика случается.',
        'transliteration' => '',
    ])];

    expect(codes($this->validator->validate(planDay($items))))
        ->toContain(PlanDayValidator::KEY_NOT_SUPPORT_LANGUAGE);
});

it('still needs goal_terms for a mixed-case product name', function () {
    // `Laravel` is not two-to-five capitals, so the shape rule cannot see it. That is what the
    // learner's own list is still for.
    $items = fn (): array => [planItem([
        'text' => 'a line', 'translation' => 'Я работал с Laravel', 'example' => 'A line happens.',
        'example_translation' => 'Реплика случается.', 'transliteration' => '',
    ])];

    expect(codes($this->validator->validate(planDay($items()))))
        ->toContain(PlanDayValidator::KEY_NOT_SUPPORT_LANGUAGE)
        ->and(codes($this->validator->validate(planDay($items(), goalTerms: ['Laravel']))))
        ->not->toContain(PlanDayValidator::KEY_NOT_SUPPORT_LANGUAGE);
});

<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\ContentChecks;
use App\Modules\Generation\Domain\Service\KeyIsomorphism;
use App\Modules\Generation\Domain\ValueObject\CandidateItem;
use App\Modules\Generation\Domain\ValueObject\CheckId;
use App\Modules\Generation\Domain\ValueObject\PromptShape;
use App\Modules\Vocabulary\Application\Service\TranslationKeyRule;
use App\Modules\Vocabulary\Domain\Service\AddresseeIsomorphism;

/**
 * The REAL rule, not a double: a bake-off scored against a stand-in would rank providers by a
 * standard the store does not use, which is the one thing this check must not do.
 */
function realKeyIsomorphism(): KeyIsomorphism
{
    $rule = new TranslationKeyRule(new AddresseeIsomorphism());

    return new class($rule) implements KeyIsomorphism
    {
        public function __construct(private readonly TranslationKeyRule $rule) {}

        public function gaps(string $source, string $translation, string $lang): array
        {
            return $this->rule->gaps($source, $translation, $lang);
        }

        public function knows(string $lang): bool
        {
            return $this->rule->knows($lang);
        }
    };
}

function contentChecks(): ContentChecks
{
    return new ContentChecks(realKeyIsomorphism());
}

function checkItem(int $position, array $overrides = []): CandidateItem
{
    return new CandidateItem(
        position: $position,
        text: $overrides['text'] ?? 'withdraw cash',
        type: array_key_exists('type', $overrides) ? $overrides['type'] : 'phrase',
        translation: array_key_exists('translation', $overrides) ? $overrides['translation'] : 'снять наличные',
        example: array_key_exists('example', $overrides) ? $overrides['example'] : 'I need to withdraw cash today.',
        exampleTranslation: array_key_exists('exampleTranslation', $overrides) ? $overrides['exampleTranslation'] : 'Мне нужно снять наличные сегодня.',
        transcription: array_key_exists('transcription', $overrides) ? $overrides['transcription'] : 'wɪðˈdrɔː kæʃ',
        cefr: array_key_exists('cefr', $overrides) ? $overrides['cefr'] : 'A2',
        options: $overrides['options'] ?? [],
        givenTerm: $overrides['givenTerm'] ?? null,
    );
}

function judgeItems(array $items, PromptShape $shape = PromptShape::Terms, ?int $size = null): App\Modules\Generation\Domain\ValueObject\CheckedBatch
{
    return contentChecks()->judge($items, $shape, 'ru', 'en', $size);
}

it('passes a clean item on every check', function () {
    $batch = judgeItems([checkItem(0)]);

    expect($batch->clean())->toBe(1)
        ->and($batch->verdicts[0]->failed)->toBe([]);
});

it('catches the defect the whole chapter is about — the lost addressee', function () {
    $batch = judgeItems([checkItem(0, [
        'text' => 'Tell us about a challenge you faced',
        'translation' => 'Расскажите о вызове, с которым вы столкнулись',
        'example' => 'Tell us about a challenge you faced at work.',
        'exampleTranslation' => 'Расскажите о вызове, с которым вы столкнулись на работе.',
    ])]);

    expect($batch->verdicts[0]->failed(CheckId::Isomorphism))->toBeTrue()
        ->and($batch->verdicts[0]->reason())->toContain('потеряно')->toContain('us');
});

it('catches the mirror defect too — a translation that adds what the term never said', function () {
    $batch = judgeItems([checkItem(0, [
        'text' => 'I get along with my team',
        'translation' => 'Я хорошо лажу со своей командой',
        'example' => 'I get along with my team on most days.',
        'exampleTranslation' => 'Я лажу со своей командой почти всегда.',
    ])]);

    expect($batch->verdicts[0]->failed(CheckId::Isomorphism))->toBeTrue()
        ->and($batch->verdicts[0]->reason())->toContain('лишнее')->toContain('хорошо');
});

it('catches a Ukrainian word in a Russian translation and Cyrillic in an English term', function () {
    $ua = judgeItems([checkItem(0, ['translation' => 'зняти готівку'])]);
    expect($ua->verdicts[0]->failed(CheckId::LangSource))->toBeTrue();

    $cyr = judgeItems([checkItem(0, ['text' => 'withdraw наличные'])]);
    expect($cyr->verdicts[0]->failed(CheckId::LangTarget))->toBeTrue();
});

/**
 * Without an example a term has no card on the third rung and drops out of the course — the defect
 * this prompt version was written to close, so the check has to see both shapes of it.
 */
it('fails an item with no example, and one whose example is just the term again', function () {
    $missing = judgeItems([checkItem(0, ['example' => null, 'exampleTranslation' => null])]);
    expect($missing->verdicts[0]->failed(CheckId::Example))->toBeTrue()
        ->and($missing->verdicts[0]->reason())->toContain('нет примера');

    $echo = judgeItems([checkItem(0, ['text' => 'Where can I find dog food?', 'example' => 'Where can I find dog food?'])]);
    expect($echo->verdicts[0]->failed(CheckId::Example))->toBeTrue()
        ->and($echo->verdicts[0]->reason())->toContain('повторяет термин');
});

it('flags both items when two share a translation, and only those two', function () {
    $batch = judgeItems([
        checkItem(0, ['text' => 'withdraw cash', 'translation' => 'снять наличные']),
        checkItem(1, ['text' => 'take out cash', 'translation' => 'Снять  наличные']), // same after normalising
        checkItem(2, ['text' => 'deposit money', 'translation' => 'положить деньги']),
    ]);

    expect($batch->verdicts[0]->failed(CheckId::UniqueTranslation))->toBeTrue()
        ->and($batch->verdicts[1]->failed(CheckId::UniqueTranslation))->toBeTrue()
        ->and($batch->verdicts[2]->failed(CheckId::UniqueTranslation))->toBeFalse();
});

it('fails an option set that repeats itself or repeats the right answer', function () {
    $duplicate = judgeItems([checkItem(0, ['options' => ['положить деньги', 'положить  Деньги', 'закрыть счёт']])], PromptShape::Enrich);
    expect($duplicate->verdicts[0]->reason())->toContain('опции повторяются');

    $correct = judgeItems([checkItem(0, ['options' => ['снять наличные', 'положить деньги', 'закрыть счёт']])], PromptShape::Enrich);
    expect($correct->verdicts[0]->reason())->toContain('совпадает с верным ответом');

    $short = judgeItems([checkItem(0, ['options' => ['положить деньги']])], PromptShape::Enrich);
    expect($short->verdicts[0]->reason())->toContain('опций 1');
});

it('does not look for options in the shape that has none', function () {
    $batch = judgeItems([checkItem(0, ['options' => []])], PromptShape::Terms);

    expect($batch->verdicts[0]->failed(CheckId::Options))->toBeFalse()
        ->and($batch->clean())->toBe(1);
});

it('fails an item that rewrote the term it was handed', function () {
    $batch = judgeItems([checkItem(0, [
        'text' => 'withdraw some cash',
        'givenTerm' => 'withdraw cash',
    ])], PromptShape::Enrich);

    expect($batch->verdicts[0]->failed(CheckId::Verbatim))->toBeTrue();
});

it('names missing fields one by one', function () {
    $batch = judgeItems([checkItem(0, ['transcription' => null, 'cefr' => 'Z9', 'type' => 'noun'])]);

    expect($batch->verdicts[0]->reason())
        ->toContain('пусто: transcription')
        ->toContain('уровень: Z9')
        ->toContain('тип: noun');
});

/**
 * A short answer must count as ONE failure, not as one per missing item — otherwise a provider that
 * returned three items instead of twelve outranks one that returned twelve with two flaws.
 */
it('counts a short answer once, at the batch level', function () {
    $batch = judgeItems([
        checkItem(0),
        checkItem(1, ['text' => 'open an account', 'translation' => 'открыть счёт']),
    ], PromptShape::Terms, 12);

    expect($batch->batchFailures)->toBe([CheckId::Size])
        ->and($batch->sizeNote)->toBe('запрошено 12, получено 2')
        ->and($batch->clean())->toBe(2);
});

it('splits the answer into halves so the tail hypothesis is measurable', function () {
    // 4 items: the last two are broken. First half clean, second half entirely bad.
    $batch = judgeItems([
        checkItem(0),
        checkItem(1, ['text' => 'open an account', 'translation' => 'открыть счёт']),
        // Ukrainian, and spelled with a letter Russian does not have — «закрити рахунок» would
        // NOT be caught, because every letter in it is shared. That half of the class is invisible
        // to a script check by construction (see LanguagePurity) and is a limit of the automatic
        // score, not of this test.
        checkItem(2, ['text' => 'close an account', 'translation' => 'потрібно закрити рахунок']),
        checkItem(3, ['text' => 'check the balance', 'translation' => 'проверить баланс', 'example' => null, 'exampleTranslation' => null]),
    ]);

    [$first, $second, $n1, $n2] = $batch->halves();

    expect($n1)->toBe(2)->and($n2)->toBe(2)
        ->and($first)->toBe(0.0)
        ->and($second)->toBe(1.0);
});

/**
 * The v11 headline defect, on the row that named it: «back up» → «подниматься обратно из-за засора»
 * is true about the world and unanswerable as a card.
 */
it('flags a translation that describes the term instead of naming it', function () {
    $batch = judgeItems([checkItem(0, [
        'text' => 'back up',
        'translation' => 'подниматься обратно из-за засора',
        'example' => 'The sink backs up every time it rains.',
        'exampleTranslation' => 'Раковина забивается каждый раз, когда идёт дождь.',
    ])]);

    expect($batch->verdicts[0]->failed(CheckId::Definition))->toBeTrue()
        ->and($batch->verdicts[0]->reason())->toContain('из-за');
});

it('flags a translation three times its term even with no explanatory word in it', function () {
    $batch = judgeItems([checkItem(0, [
        'text' => 'commute',
        'translation' => 'ездить каждый день на работу и обратно',
        'example' => 'I commute by train every morning.',
        'exampleTranslation' => 'Я езжу на работу на поезде каждое утро.',
    ])]);

    expect($batch->verdicts[0]->failed(CheckId::Definition))->toBeTrue()
        ->and($batch->verdicts[0]->reason())->toContain('длиннее термина');
});

/**
 * The guard that makes the rule usable. A long translation of a long term is not a definition, and a
 * marker inside one is innocent — flagging these would bury the real ones.
 */
it('leaves a long key for a long term alone, marker and all', function () {
    $batch = judgeItems([
        checkItem(0, [
            'text' => 'Tell us about a challenge you faced',
            'translation' => 'Расскажите нам о вызове, с которым вы столкнулись',
            'example' => 'Tell us about a challenge you faced at work last year.',
            'exampleTranslation' => 'Расскажите нам о вызове, с которым вы столкнулись на работе.',
        ]),
        checkItem(1, ['text' => 'withdraw cash', 'translation' => 'снять наличные']),
        checkItem(2, ['text' => 'fill out', 'translation' => 'заполнить (форму)']),
    ]);

    foreach ($batch->verdicts as $verdict) {
        expect($verdict->failed(CheckId::Definition))->toBeFalse();
    }
});

/**
 * The calque, which the length metric cannot see: two words against one, no explanatory connective,
 * entirely accurate — and nobody says it, so the learner shown «испытавший облегчение» writes
 * something else and is marked wrong.
 */
it('flags a participial calque of a state adjective, however short and accurate it is', function () {
    $batch = judgeItems([
        checkItem(0, [
            'text' => 'relieved',
            'translation' => 'испытавший облегчение',
            'example' => 'I felt relieved when the results came back.',
            'exampleTranslation' => 'Я почувствовал облегчение, когда пришли результаты.',
        ]),
        checkItem(1, [
            'text' => 'overwhelmed',
            'translation' => 'оказавшийся перегруженным',
            'example' => 'She was overwhelmed by the workload.',
            'exampleTranslation' => 'Она была перегружена объёмом работы.',
        ]),
    ]);

    foreach ($batch->verdicts as $verdict) {
        expect($verdict->failed(CheckId::Definition))->toBeTrue()
            ->and($verdict->reason())->toContain('похоже на кальку');
    }
});

it('leaves an ordinary adjective, a spoken phrase and a participle inside a phrase key alone', function () {
    $batch = judgeItems([
        // What the rule is asking FOR.
        checkItem(0, ['text' => 'relieved', 'translation' => 'с облегчением']),
        checkItem(1, ['text' => 'annoyed', 'translation' => 'раздражённый']),
        // A participle deep inside a legitimate phrase key is the phrase, not a rendering of one
        // word's grammar — anchoring the rule at the head is what keeps this clean.
        checkItem(2, [
            'text' => 'Tell us about a challenge you faced',
            'translation' => 'Расскажите нам о вызове, с которым вы столкнулись',
        ]),
        // Adjectives that merely LOOK participial. A general rule would bury the real findings
        // under these.
        checkItem(3, ['text' => 'next', 'translation' => 'следующий']),
        checkItem(4, ['text' => 'suitable', 'translation' => 'подходящий']),
    ]);

    foreach ($batch->verdicts as $verdict) {
        expect($verdict->failed(CheckId::Definition))->toBeFalse();
    }
});

it('does not judge a core the mechanics shape was forbidden to write', function () {
    // No translation, no example — that is CORRECT for this shape, and scoring it as a defect
    // would rank a compliant answer below a disobedient one.
    $batch = judgeItems([new CandidateItem(
        position: 0,
        text: 'withdraw cash',
        options: ['положить деньги', 'закрыть счёт', 'проверить баланс'],
        forms: ['take out cash'],
    )], PromptShape::Mechanics);

    expect($batch->verdicts[0]->failed(CheckId::Definition))->toBeFalse()
        ->and($batch->verdicts[0]->failed(CheckId::Example))->toBeFalse()
        ->and($batch->verdicts[0]->failed(CheckId::Isomorphism))->toBeFalse()
        ->and(CheckId::forShape(PromptShape::Mechanics))->not->toContain(CheckId::Example);
});

it('fails a form that repeats the term or runs to a clause', function () {
    $echo = judgeItems([new CandidateItem(
        position: 0, text: 'check in', options: ['a', 'b', 'c'], forms: ['Check In'],
    )], PromptShape::Mechanics);
    expect($echo->verdicts[0]->reason())->toContain('повторяет термин');

    $clause = judgeItems([new CandidateItem(
        position: 0, text: 'check in', options: ['a', 'b', 'c'],
        forms: ['to register at the hotel reception'],
    )], PromptShape::Mechanics);
    expect($clause->verdicts[0]->reason())->toContain('длиннее термина вдвое');

    // An empty list is the normal answer for most terms and is never penalised.
    $none = judgeItems([new CandidateItem(
        position: 0, text: 'check in', options: ['a', 'b', 'c'], forms: [],
    )], PromptShape::Mechanics);
    expect($none->verdicts[0]->failed(CheckId::Forms))->toBeFalse();
});

it('stays silent about the key in a language the rule was never taught', function () {
    // German counterparts are not in the rule. Silence, not a false clean bill of health — the
    // report is what has to state that the language was not judged.
    $batch = contentChecks()->judge(
        [new CandidateItem(0, 'thank you', 'phrase', 'danke', 'Thank you very much.', 'Vielen Dank.', 'θæŋk juː', 'A1')],
        PromptShape::Terms,
        'de',
        'en',
    );

    expect($batch->verdicts[0]->failed(CheckId::Isomorphism))->toBeFalse();
});

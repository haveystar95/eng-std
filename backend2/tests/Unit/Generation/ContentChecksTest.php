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

<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\GeneratedCollectionDraft;
use App\Modules\Generation\Application\Dto\GeneratedItem;
use App\Modules\Generation\Application\Dto\GenerationBrief;
use App\Modules\Generation\Application\Service\DraftValidator;
use App\Modules\Generation\Domain\Exception\InvalidGeneratedDraft;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;

function brief(): GenerationBrief
{
    return new GenerationBrief('иду в банк', new LanguageCode('ru'), new LanguageCode('en'), ['A2', 'B1'], 12);
}

/** @param list<GeneratedItem> $items */
function draftOf(array $items): GeneratedCollectionDraft
{
    return new GeneratedCollectionDraft('At the bank', 'desc', $items, 'fake', 10, 20);
}

function anItem(string $text, string $cefr = 'B1', string $type = 'word'): GeneratedItem
{
    return new GeneratedItem($text, $type, 'перевод ' . $text, 'Example ' . $text, $cefr);
}

/** @return list<GeneratedItem> */
function manyItems(int $count, string $cefr = 'B1'): array
{
    $items = [];
    for ($i = 1; $i <= $count; $i++) {
        $items[] = anItem("word{$i}", $cefr);
    }

    return $items;
}

it('rejects a draft with too few usable items', function () {
    expect(fn () => (new DraftValidator())->validate(draftOf(manyItems(3)), brief()))
        ->toThrow(InvalidGeneratedDraft::class);
});

it('drops items outside the requested CEFR range', function () {
    $items = [...manyItems(8, 'B1'), anItem('hardword1', 'C2'), anItem('hardword2', 'C1')];

    $result = (new DraftValidator())->validate(draftOf($items), brief());

    expect($result->items)->toHaveCount(8)
        ->and(array_filter($result->items, fn (GeneratedItem $i): bool => $i->cefr === 'C2'))->toBe([]);
});

it('relaxes the CEFR band rather than rejecting when too few items are in-level (F14)', function () {
    // A basic topic under a narrow band (e.g. restaurant vocab requested at B2): only a handful of
    // items land in-level. Shipping all valid items beats rejecting the whole draft.
    $items = [
        anItem('a1', 'B1'), anItem('a2', 'B1'), anItem('a3', 'B1'), // 3 in-level (brief is A2-B1)
        anItem('b1', 'C1'), anItem('b2', 'C1'), anItem('b3', 'C2'), anItem('b4', 'C2'),
        anItem('b5', 'C1'), anItem('b6', 'C2'), anItem('b7', 'C1'), // 7 out-of-level
    ];

    $result = (new DraftValidator())->validate(draftOf($items), brief());

    // 10 valid items, only 3 in-level (< MIN_ITEMS) → band relaxed, all 10 kept (well under MAX_ITEMS).
    expect($result->items)->toHaveCount(10)
        ->and(array_filter($result->items, fn (GeneratedItem $i): bool => $i->cefr === 'C1'))->not->toBe([]);
});

it('deduplicates by text within the draft', function () {
    $items = [...manyItems(8), anItem('WORD1'), anItem('word2')]; // case-insensitive dupes of word1/word2

    $result = (new DraftValidator())->validate(draftOf($items), brief());

    expect($result->items)->toHaveCount(8);
});

/**
 * Size is approximate (owner decision, 2026-08-18): the validator no longer trims a valid,
 * over-generated batch down toward the requested count — it only caps at the hard ceiling.
 */
it('ships every valid item up to MAX_ITEMS, not just the requested size', function () {
    // brief() asks for 12; the model over-produced 30 valid items — all should ship, capped at 25.
    $result = (new DraftValidator())->validate(draftOf(manyItems(30)), brief());

    expect($result->items)->toHaveCount(DraftValidator::MAX_ITEMS);
});

it('ships all valid items when the count sits between the requested size and MAX_ITEMS', function () {
    // Small(10) asked, 13 valid items survive validation → all 13 ship (10-13 range from the naряд).
    $small = new GenerationBrief('иду в банк', new LanguageCode('ru'), new LanguageCode('en'), ['A2', 'B1'], 10);
    $result = (new DraftValidator())->validate(draftOf(manyItems(13)), $small);

    expect($result->items)->toHaveCount(13);
});

it('caps at exactly MAX_ITEMS when the requested size is already the ceiling', function () {
    // Large(25) asked — MAX_ITEMS is the ceiling either way, so even 30 valid items cap at 25.
    $large = new GenerationBrief('иду в банк', new LanguageCode('ru'), new LanguageCode('en'), ['A2', 'B1'], 25);
    $result = (new DraftValidator())->validate(draftOf(manyItems(30)), $large);

    expect($result->items)->toHaveCount(25);
});

it('accepts a below-floor batch in supplemental mode without throwing', function () {
    // A top-up returning 2 fresh items is valid, not a truncated-response failure.
    $result = (new DraftValidator())->validate(draftOf(manyItems(2)), brief(), supplemental: true);

    expect($result->items)->toHaveCount(2);
});

it('keeps under-generation as-is when still above the floor', function () {
    $result = (new DraftValidator())->validate(draftOf(manyItems(10)), brief());

    expect($result->items)->toHaveCount(10);
});

it('preserves idiom and phrasal_verb types', function () {
    $items = [...manyItems(8), anItem('break the ice', 'B1', 'idiom'), anItem('run into', 'B1', 'phrasal_verb')];

    $result = (new DraftValidator())->validate(draftOf($items), brief());

    $types = array_map(fn (GeneratedItem $i): string => $i->type, $result->items);
    expect($types)->toContain('idiom')->toContain('phrasal_verb');
});

it('infers phrase type from whitespace when omitted', function () {
    $items = [...manyItems(8), new GeneratedItem('open an account', 'unknown', 'открыть счёт', null, 'B1')];

    $result = (new DraftValidator())->validate(draftOf($items), brief());

    $phrase = $result->items[count($result->items) - 1];
    expect($phrase->type)->toBe('phrase');
});

/**
 * QA-7: an example that IS the term teaches nothing — the intro card shows the same sentence twice.
 * Three of ten items in the acceptance collection came back that way, while two other phrases and an
 * idiom in the very same response got proper contextual examples: the model being inconsistent,
 * which is what validation is for.
 */
it('refuses an example that merely repeats the term', function () {
    $echo = new GeneratedItem(
        'Where can I find dog food?', 'phrase', 'Где я могу найти корм для собак?',
        'Where can I find dog food?', 'B1', null, 'Где я могу найти корм для собак?',
    );
    $items = [...manyItems(8), $echo];

    $result = (new DraftValidator())->validate(draftOf($items), brief());

    $item = $result->items[count($result->items) - 1];
    expect($item->text)->toBe('Where can I find dog food?', 'the TERM is kept — only its example was bad');
    expect($item->example)->toBeNull();
    expect($item->exampleTranslation)->toBeNull('a translation of a sentence that is not there');
});

it('ignores case and surrounding space when deciding what an echo is', function () {
    $echo = new GeneratedItem('do you have any discounts?', 'phrase', 'у вас есть скидки?',
        '  Do you have any discounts?  ', 'B1');
    $items = [...manyItems(8), $echo];

    $result = (new DraftValidator())->validate(draftOf($items), brief());

    expect($result->items[count($result->items) - 1]->example)->toBeNull();
});

it('KEEPS an example that contains the term — that is what using a word in a sentence means', function () {
    // The rule is equality, never similarity: «Do you have any discounts on dog food?» is exactly
    // the example the term wants, and a threshold-based check would throw it away.
    $good = new GeneratedItem('do you have any discounts?', 'phrase', 'у вас есть скидки?',
        'Do you have any discounts on dog food?', 'B1', null, 'У вас есть скидки на корм для собак?');
    $items = [...manyItems(8), $good];

    $result = (new DraftValidator())->validate(draftOf($items), brief());

    $item = $result->items[count($result->items) - 1];
    expect($item->example)->toBe('Do you have any discounts on dog food?');
    expect($item->exampleTranslation)->toBe('У вас есть скидки на корм для собак?');
});

it('never drops the ITEM over its example', function () {
    // The term, translation and level are all fine; only the example was refused. Dropping the item
    // would silently shorten the collection the learner asked for.
    $items = array_map(
        static fn (GeneratedItem $i): GeneratedItem => new GeneratedItem(
            $i->text, $i->type, $i->translation, $i->text, $i->cefr,
        ),
        manyItems(10),
    );

    $result = (new DraftValidator())->validate(draftOf($items), brief());

    expect($result->items)->toHaveCount(10);
    expect(array_filter($result->items, static fn (GeneratedItem $i): bool => $i->example !== null))->toBe([]);
});

/**
 * QA-2: «do you have any discounts?» arrived lowercase among nine correctly capitalised
 * neighbours of the same response — «Where can I find dog food?», «Is this suitable for small
 * breeds?». On the card it read as a typo the app had made. The model is inconsistent about
 * exactly this, which is what makes it a job for validation rather than for the prompt.
 */
it('capitalises a sentence-like phrase that came back lowercase', function () {
    $lower = new GeneratedItem('do you have any discounts?', 'phrase', 'у вас есть скидки?',
        'Do you have any discounts on dog food?', 'B1');
    $items = [...manyItems(8), $lower];

    $result = (new DraftValidator())->validate(draftOf($items), brief());

    $item = $result->items[count($result->items) - 1];
    expect($item->text)->toBe('Do you have any discounts?')
        ->and($item->example)->toBe('Do you have any discounts on dog food?', 'only the case moved');
});

it('leaves a lowercase NOUN phrase alone — that is how it is written', function () {
    // «aisle seat», «baggage claim», «check-in desk» are all type=phrase and all correctly
    // lowercase. Capitalising every phrase would disfigure them; the sentence mark is the signal.
    $noun = new GeneratedItem('aisle seat', 'phrase', 'место у прохода', 'I prefer an aisle seat.', 'B1');
    $items = [...manyItems(8), $noun];

    $result = (new DraftValidator())->validate(draftOf($items), brief());

    expect($result->items[count($result->items) - 1]->text)->toBe('aisle seat');
});

it('never touches a word or an idiom', function () {
    // «grain-free» is legal exactly as it stands.
    $items = [
        ...manyItems(8),
        new GeneratedItem('grain-free', 'word', 'беззерновой', 'We buy grain-free food.', 'B1'),
        new GeneratedItem('tailor to their needs', 'idiom', 'подстроить под нужды', 'We tailor to their needs.', 'B1'),
    ];

    $result = (new DraftValidator())->validate(draftOf($items), brief());

    $texts = array_map(static fn (GeneratedItem $i): string => $i->text, $result->items);
    expect($texts)->toContain('grain-free')->toContain('tailor to their needs');
});

it('capitalises AFTER deduplication, so casing cannot split one item into two', function () {
    $items = [
        ...manyItems(8),
        new GeneratedItem('do you have any discounts?', 'phrase', 'у вас есть скидки?', null, 'B1'),
        new GeneratedItem('Do you have any discounts?', 'phrase', 'скидки?', null, 'B1'),
    ];

    $result = (new DraftValidator())->validate(draftOf($items), brief());

    $texts = array_map(static fn (GeneratedItem $i): string => $i->text, $result->items);
    expect(array_count_values($texts)['Do you have any discounts?'])->toBe(1);
});

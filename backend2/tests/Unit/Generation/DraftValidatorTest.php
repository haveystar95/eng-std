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

it('deduplicates by text within the draft', function () {
    $items = [...manyItems(8), anItem('WORD1'), anItem('word2')]; // case-insensitive dupes of word1/word2

    $result = (new DraftValidator())->validate(draftOf($items), brief());

    expect($result->items)->toHaveCount(8);
});

it('trims over-generation down to the requested size', function () {
    // brief() asks for 12; the model over-produced 30.
    $result = (new DraftValidator())->validate(draftOf(manyItems(30)), brief());

    expect($result->items)->toHaveCount(12);
});

it('keeps under-generation as-is when still above the floor', function () {
    $result = (new DraftValidator())->validate(draftOf(manyItems(10)), brief());

    expect($result->items)->toHaveCount(10);
});

it('infers phrase type from whitespace when omitted', function () {
    $items = [...manyItems(8), new GeneratedItem('open an account', 'unknown', 'открыть счёт', null, 'B1')];

    $result = (new DraftValidator())->validate(draftOf($items), brief());

    $phrase = $result->items[count($result->items) - 1];
    expect($phrase->type)->toBe('phrase');
});

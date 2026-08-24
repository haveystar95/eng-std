<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\WordLookupResult;
use App\Modules\Generation\Application\Service\LookupBarrier;
use App\Modules\Generation\Domain\Exception\LookupRefused;
use App\Modules\Generation\Domain\Service\DescriptionSelfReference;

function lookupAnswer(
    string $text = 'invoice',
    string $translation = 'счёт',
    string $description = 'A paper that says how much money you must pay for something.',
    ?string $example = 'They sent the invoice by email.',
    ?string $exampleTranslation = 'Они прислали счёт по почте.',
    array $synonyms = [],
    array $otherTranslations = [],
): WordLookupResult {
    return new WordLookupResult(
        text: $text,
        type: 'word',
        translation: $translation,
        description: $description,
        example: $example,
        exampleTranslation: $exampleTranslation,
        cefr: 'B1',
        transcription: null,
        imageApiPrompt: 'office desk paperwork',
        synonyms: $synonyms,
        otherTranslations: $otherTranslations,
        model: 'test',
        promptVersion: 'lookup.test',
    );
}

it('passes a clean answer through unchanged', function () {
    $screened = (new LookupBarrier())->screen(lookupAnswer(), 'en', 'ru');

    expect($screened->example)->toBe('They sent the invoice by email.')
        ->and($screened->exampleTranslation)->toBe('Они прислали счёт по почте.');
});

it('refuses a description written in the wrong language', function () {
    (new LookupBarrier())->screen(
        lookupAnswer(description: 'Бумага, где написано, сколько нужно заплатить.'),
        'en',
        'ru',
    );
})->throws(LookupRefused::class);

it('refuses a translation written in the wrong language', function () {
    (new LookupBarrier())->screen(lookupAnswer(translation: 'a bill'), 'en', 'ru');
})->throws(LookupRefused::class);

it('refuses a description that contains the word it describes', function () {
    (new LookupBarrier())->screen(
        lookupAnswer(description: 'An invoice is a paper that asks for money.'),
        'en',
        'ru',
    );
})->throws(LookupRefused::class);

it('drops an example that does not contain the term, keeping the card', function () {
    $screened = (new LookupBarrier())->screen(
        lookupAnswer(example: 'They sent the bill by email.'),
        'en',
        'ru',
    );

    expect($screened->example)->toBeNull()
        ->and($screened->exampleTranslation)->toBeNull()
        // The half that made the card worth showing survives.
        ->and($screened->description)->not->toBe('')
        ->and($screened->translation)->toBe('счёт');
});

it('drops an example translation written in the wrong language', function () {
    $screened = (new LookupBarrier())->screen(
        lookupAnswer(exampleTranslation: 'They sent it by email.'),
        'en',
        'ru',
    );

    expect($screened->example)->not->toBeNull()
        ->and($screened->exampleTranslation)->toBeNull();
});

it('catches the obvious ways a description gives its word away', function (string $description, bool $gives) {
    expect(DescriptionSelfReference::givesAway($description, 'bank'))->toBe($gives);
})->with([
    ['A place where you keep your money.', false],
    ['A bank is where you keep your money.', true],
    ['People put money in banks.', true],
    // A derived form gives it away just as plainly, so the suffix family catches it too.
    ['This is about banking and money.', true],
    // Word boundaries: the letters are there, the word is not.
    ['You can walk along the embankment near the river.', false],
]);

it('does not inflect a multi-word phrase, but still catches it whole', function () {
    expect(DescriptionSelfReference::givesAway('You do this when you give up on a plan.', 'give up'))->toBeTrue()
        // «give» and «up» each appear; the phrase does not. Describing a phrase by using its
        // individual words is normal and must not be refused.
        ->and(DescriptionSelfReference::givesAway('When you give someone a lift up the hill.', 'give up'))->toBeFalse();
});


// ---- synonyms and other readings: DEGRADED, never fatal (SYN-1 Ч.2) -----------------------------

it('keeps clean synonyms and other readings', function () {
    $screened = (new LookupBarrier())->screen(
        lookupAnswer(synonyms: ['bill', 'receipt'], otherTranslations: ['квитанция']),
        'en',
        'ru',
    );

    expect($screened->synonyms)->toBe(['bill', 'receipt'])
        ->and($screened->otherTranslations)->toBe(['квитанция']);
});

it('drops a synonym written in the learner\'s language instead of refusing the card', function () {
    $screened = (new LookupBarrier())->screen(lookupAnswer(synonyms: ['bill', 'квитанция']), 'en', 'ru');

    // The card the learner came for survives; only the third-tier field loses a row.
    expect($screened->synonyms)->toBe(['bill'])
        ->and($screened->text)->toBe('invoice');
});

it('drops a synonym that is really the word itself', function () {
    $screened = (new LookupBarrier())->screen(lookupAnswer(synonyms: ['Invoice', 'bill']), 'en', 'ru');

    expect($screened->synonyms)->toBe(['bill']);
});

it('drops an "other reading" that repeats the answer the card asks', function () {
    $screened = (new LookupBarrier())->screen(lookupAnswer(otherTranslations: ['Счёт', 'квитанция']), 'en', 'ru');

    expect($screened->otherTranslations)->toBe(['квитанция']);
});

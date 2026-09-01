<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\TranslationKeyPresence;

beforeEach(fn () => $this->presence = new TranslationKeyPresence());

it('finds the key through Russian inflection — the whole reason for stems', function () {
    // The card says «долгое проживание в новой стране»; the line says «для долгого проживания…».
    expect($this->presence->holds(
        'ru',
        'Да, я ищу жильё для долгого проживания в новой стране',
        'долгое проживание в новой стране',
    ))->toBeTrue();
});

it('refuses a translation that renders everything except the key — the owner’s card', function () {
    // The key is «a place to rent» / «место для аренды», and the Russian under the line never says
    // which kind of place. The learner reads the question and cannot know what to produce.
    expect($this->presence->holds(
        'ru',
        'Да, я ищу жильё для долгого проживания в новой стране',
        'место для аренды',
    ))->toBeFalse();
});

it('accepts a translation that keeps the key and drops the rest of it', function () {
    // «рядом с центром города» inside «Есть ли что-нибудь рядом с центром?» — the Russian is what a
    // person says, and demanding «города» would refuse a good card.
    expect($this->presence->holds(
        'ru',
        'Есть ли что-нибудь рядом с центром?',
        'рядом с центром города',
    ))->toBeTrue();
});

it('does not count a preposition as having found the key', function () {
    // «для» is in both and means nothing. Only significant words count.
    expect($this->presence->holds('ru', 'Я взял это для тебя', 'место для аренды'))->toBeFalse();
});

it('says nothing at all about a language whose rule is not written', function () {
    expect($this->presence->judges('ru'))->toBeTrue()
        ->and($this->presence->judges('de'))->toBeFalse()
        // And «cannot judge» is TRUE, never a refusal: an unwritten rule must not read as a broken
        // day, the way an empty repair-marker list does not.
        ->and($this->presence->holds('de', 'Ich suche eine Wohnung', 'völlig anderes'))->toBeTrue();
});

it('stays silent when there is nothing to look for', function () {
    expect($this->presence->holds('ru', 'Что-нибудь', ''))->toBeTrue();
});

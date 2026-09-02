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

/**
 * THE FOUR LINES OF THE OWNER'S LIVE DAY 2, verbatim — plan `01M1HZF4…`, 02–03.09.
 *
 * `card.translation_missing_key` refused this day FOUR times over these rows: twice from P2R at
 * 21:14:50, once from P2 at 22:38:37, and the fourth time through the rebuild handle this наряд
 * added. Every one of the translations below is correct Russian; every one of them was refused,
 * because a fixed five-letter stem of «тихий» is «тихий» and the language says «тихо».
 */
it('finds a short adjective through its ending — the live day 2 rows', function () {
    expect($this->presence->holds('ru', 'Здесь довольно тихо.', 'тихий'))->toBeTrue()
        ->and($this->presence->holds('ru', 'Тихое место мне подходит.', 'тихий'))->toBeTrue()
        ->and($this->presence->holds('ru', 'Это важно для меня.', 'важный'))->toBeTrue()
        // The fourth generation, bought by the rebuild handle, died on this one.
        ->and($this->presence->holds('ru', 'Это выглядит тихо.', 'тихий'))->toBeTrue();
});

it('still refuses a line that says nothing of the key, short word or not', function () {
    // The adaptive stem widens what counts as «the same word»; it does not make the gate vacuous.
    expect($this->presence->holds('ru', 'Здесь довольно шумно.', 'тихий'))->toBeFalse()
        ->and($this->presence->holds('ru', 'Мне это подходит.', 'важный'))->toBeFalse();
});

it('requires a three-letter key to be there exactly — there is no stem to take', function () {
    expect($this->presence->holds('ru', 'Это мой дом', 'дом'))->toBeTrue()
        // «домой» is a different word, and two letters of «дом» would have matched it.
        ->and($this->presence->holds('ru', 'Я иду домой', 'дом'))->toBeFalse();
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

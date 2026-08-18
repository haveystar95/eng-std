<?php

declare(strict_types=1);

use App\Modules\Vocabulary\Domain\Service\AddresseeIsomorphism;

/**
 * QA-17: the detector that finds translations which have stopped pointing at their own term.
 *
 * What it protects: a translation is the KEY, not prose. Drop «нам» from «Tell us about a challenge
 * you faced» and `Tell me…`, `Tell us…` and `Describe…` all answer the question equally — so an
 * honest answer is logged as a lapse.
 *
 * It is coarse and it never fixes anything, and the tests below say so in both directions: the cases
 * it must catch, and the cases where it stays quiet because Russian carries a person some other way.
 */
beforeEach(function () {
    $this->rule = new AddresseeIsomorphism();
});

it("catches the owner's own case: «нам» dropped from a `Tell us…`", function () {
    expect($this->rule->violations(
        'Tell us about a challenge you faced',
        'Расскажите о вызове, с которым вы столкнулись',
    ))->toBe(['us/me']);
});

it('clears the same term once the addressee is back', function () {
    expect($this->rule->violations(
        'Tell us about a challenge you faced',
        'Расскажите нам о вызове, с которым вы столкнулись',
    ))->toBe([]);
});

it("catches «your» smoothed into «своём»", function () {
    // «Расскажите о своём опыте» is a legitimate Russian sentence and a bad key: `Tell us about
    // your experience`, `Tell me about your experience` and `Describe your experience` all fit it.
    expect($this->rule->violations('Tell us about your experience', 'Расскажите о своём опыте'))
        ->toBe(['us/me', 'you/your']);
});

it('matches groups, never one flat list of pronouns', function () {
    // «вы» is present and «нам» is not. A flat list would clear this row; the whole point of the
    // grouping is that a term saying `us` is not rescued by a translation saying «вы».
    expect($this->rule->violations('Tell us what you think', 'Скажите, что вы думаете'))
        ->toBe(['us/me']);
});

it('stays quiet when the term addresses nobody', function () {
    expect($this->rule->violations('withdraw cash', 'снять наличные'))->toBe([]);
});

it('only reads STANDALONE words — a pronoun buried in another word is not one', function () {
    // `us` lives inside «campus» and «discuss»; «я» lives inside almost every Russian verb ending.
    // A substring search would flag or clear nearly everything, which is a detector that says
    // nothing at all.
    expect($this->rule->violations('discuss the campus bus route', 'обсудить маршрут автобуса по кампусу'))
        ->toBe([]);
});

it('does not read «нас» inside «насос»', function () {
    // The Cyrillic side is where a word-boundary mistake is easiest to make and hardest to see.
    expect($this->rule->violations('Tell us about the pump', 'Расскажите про насос'))
        ->toBe(['us/me'], 'насос must not count as «нас»');
});

it('is case-insensitive on both sides', function () {
    expect($this->rule->violations('TELL US ABOUT IT', 'РАССКАЖИТЕ НАМ ОБ ЭТОМ'))->toBe([]);
});

it('names every group it knows, so a report can show the empty ones too', function () {
    expect(AddresseeIsomorphism::groupNames())->toBe(['us/me', 'you/your', 'we/our']);
});

it('reports each tripped group once, in a stable order', function () {
    expect($this->rule->violations('Can you tell me about our plans?', 'Расскажите про планы'))
        ->toBe(['us/me', 'you/your', 'we/our']);
});

// Ukrainian (QA, 2026-08-18): extends the same detector with a second counterpart list per group
// rather than a second rule — «us/me» happens to share its Russian and Ukrainian forms («нам»,
// «нас»), the other two groups are Ukrainian-specific words that would never appear as Russian
// counterparts.

it('catches «нам» dropped from a `Tell us…` in Ukrainian too — us/me forms overlap Russian', function () {
    expect($this->rule->violations(
        'Tell us about a challenge you faced',
        'Розкажіть про виклик, з яким ви зіткнулися',
        'uk',
    ))->toBe(['us/me']);
});

it('clears the Ukrainian us/me case once «нам» is back', function () {
    expect($this->rule->violations(
        'Tell us about a challenge you faced',
        'Розкажіть нам про виклик, з яким ви зіткнулися',
        'uk',
    ))->toBe([]);
});

it('catches `me` dropped from a Ukrainian translation via мені/мене', function () {
    expect($this->rule->violations('Tell me about yourself', 'Розкажіть про себе', 'uk'))
        ->toBe(['us/me']);
    expect($this->rule->violations('Tell me about yourself', 'Розкажіть мені про себе', 'uk'))
        ->toBe([]);
});

it('catches `your` dropped from a Ukrainian translation, and clears on ваш or твій', function () {
    expect($this->rule->violations('Describe your experience', 'Опишіть досвід', 'uk'))
        ->toBe(['you/your']);
    expect($this->rule->violations('Describe your experience', 'Опишіть ваш досвід', 'uk'))
        ->toBe([]);
    expect($this->rule->violations('What is your name?', 'Як тебе звати?', 'uk'))
        ->toBe(['you/your'], 'тебе is not one of the listed Ukrainian counterparts, only ви/вас/вам/ваш*/твій/тобі');
    expect($this->rule->violations('What is your name?', 'Як твій друг?', 'uk'))
        ->toBe([]);
});

it('catches `our` dropped from a Ukrainian translation via ми/наш*', function () {
    expect($this->rule->violations('Tell us about our plans', 'Розкажіть про плани', 'uk'))
        ->toBe(['us/me', 'we/our']);
    expect($this->rule->violations('Tell us about our plans', 'Розкажіть нам про наші плани', 'uk'))
        ->toBe([]);
});

it('does not read «нас» inside a Ukrainian word either', function () {
    expect($this->rule->violations('Tell us about the pump', 'Розкажіть про насос', 'uk'))
        ->toBe(['us/me'], 'насос must not count as «нас» in Ukrainian any more than in Russian');
});

it('defaults to Russian when no language is given, unchanged from before Ukrainian support', function () {
    // Judged with no $lang: falls back to Russian counterparts, so a Ukrainian translation (which
    // shares none of the Russian pronoun spellings tested here) trips every group the term uses.
    expect($this->rule->violations('Tell us about a challenge you faced', 'Розкажіть про виклик'))
        ->toBe(['us/me', 'you/your']);
});

it('skips every group for a language it has no counterpart list for, rather than false-hitting', function () {
    expect($this->rule->violations('Tell us about a challenge you faced', 'Скажи-nous quelque chose', 'fr'))
        ->toBe([]);
});

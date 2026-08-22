<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\SearchQueryLength;
use App\Modules\Generation\Domain\Service\TranslationDirection;

/**
 * Which half of the pair did they type? Two answers, and only one of them is trusted.
 */
describe('the alphabet\'s guess', function () {
    $direction = new TranslationDirection();

    it('reads Cyrillic as the learner\'s own language', function () use ($direction) {
        $guess = $direction->guess('случай', 'ru', 'en');

        expect($guess->source)->toBe('ru')->and($guess->target)->toBe('en');
    });

    it('reads Latin as the language being learned', function () use ($direction) {
        $guess = $direction->guess('occasion', 'ru', 'en');

        expect($guess->source)->toBe('en')->and($guess->target)->toBe('ru');
    });

    it('has no opinion at all about a script it cannot read, and assumes the learner\'s own', function () use ($direction) {
        // Romanian and English are one alphabet. The check knows two scripts and says nothing about
        // the rest, so the guess falls back to «they typed their own language» — the direction the
        // field is mostly used in.
        expect($direction->guess('ocazie', 'ro', 'en')->pair())->toBe('ro:en');
    });

    it('is therefore WRONG whenever such a learner types English — which is why it is only a guess', function () use ($direction) {
        // The guess says ro→en; the provider will say the source was English, and the answer has to
        // be bought again as en→ro. That second call is the price of never showing somebody their
        // own language back.
        $guess = $direction->guess('occasion', 'ro', 'en');

        expect($guess->pair())->toBe('ro:en')
            ->and($direction->resolve('en', 'ro', 'en')->pair())->toBe('en:ro')
            ->and($direction->resolve('en', 'ro', 'en')->equals($guess))->toBeFalse();
    });
});

describe('the detector\'s verdict', function () {
    $direction = new TranslationDirection();

    it('flips the direction only for the learner\'s own language', function () use ($direction) {
        $resolved = $direction->resolve('RU', 'ru', 'en');

        expect($resolved->source)->toBe('ru')->and($resolved->target)->toBe('en');
    });

    it('answers in the learner\'s language for the language being learned', function () use ($direction) {
        expect($direction->resolve('en', 'ru', 'en')->pair())->toBe('en:ru');
    });

    it('treats a third language as the language being learned', function () use ($direction) {
        // Somebody who typed something we cannot place still gets told what it means, in the
        // language they read. That is the useful failure.
        expect($direction->resolve('ro', 'ru', 'en')->pair())->toBe('en:ru');
    });

    it('treats silence the same way', function () use ($direction) {
        expect($direction->resolve(null, 'ru', 'en')->pair())->toBe('en:ru');
    });
});

describe('how long a query may be', function () {
    it('counts characters and not bytes, so Cyrillic is not cut at half the length', function () {
        $limit = new SearchQueryLength(10);

        expect($limit->exceeded(str_repeat('я', 10)))->toBeFalse()
            ->and($limit->exceeded(str_repeat('я', 11)))->toBeTrue();
    });

    it('ignores surrounding whitespace, the way the query itself is normalised', function () {
        expect((new SearchQueryLength(3))->exceeded('  abc  '))->toBeFalse();
    });

    it('falls back to the default rather than accepting a nonsense ceiling', function () {
        expect((new SearchQueryLength(0))->max())->toBe(SearchQueryLength::DEFAULT_MAX);
    });
});

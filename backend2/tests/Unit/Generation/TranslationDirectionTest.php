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
        $resolved = $direction->resolve('en', 'ro', 'en', $guess);

        expect($guess->pair())->toBe('ro:en')
            ->and($resolved->pair())->toBe('en:ro')
            ->and($resolved->equals($guess))->toBeFalse();
    });
});

describe('the detector\'s verdict', function () {
    $direction = new TranslationDirection();

    it('answers in English when the learner typed their own language', function () use ($direction) {
        $resolved = $direction->resolve('RU', 'ru', 'en', $direction->guess('случай', 'ru', 'en'));

        expect($resolved->pair())->toBe('ru:en');
    });

    it('answers in the learner\'s language for the language being learned', function () use ($direction) {
        expect($direction->resolve('en', 'ru', 'en', $direction->guess('occasion', 'ru', 'en'))->pair())
            ->toBe('en:ru');
    });

    it('overrules the alphabet, which is the whole reason it is asked', function () use ($direction) {
        // Latin script, but the detector says it is the learner's own language. The script loses.
        $guess = $direction->guess('ocazie', 'ro', 'en');

        expect($direction->resolve('ro', 'ro', 'en', $guess)->pair())->toBe('ro:en');
    });

    describe('a language that is neither half of the pair', function () use ($direction) {
        it('falls back to the alphabet rather than to a fixed side', function () use ($direction) {
            // DeepL detects «случай» as BULGARIAN — it is also a Bulgarian word. Found on the first
            // live call against the real vendor. A fixed «third language means answer in Russian»
            // rule handed a Russian speaker their own word back, which is the main use case of the
            // feature broken by a detector being right about a language nobody asked about.
            $guess = $direction->guess('случай', 'ru', 'en');

            expect($direction->resolve('bg', 'ru', 'en', $guess)->pair())->toBe('ru:en');
        });

        it('still answers in the learner\'s language for a Latin-script third language', function () use ($direction) {
            // The outcome the old fixed rule was actually describing, kept intact: somebody who
            // typed something we cannot place gets told what it means, in the language they read.
            $guess = $direction->guess('Gelegenheit', 'ru', 'en');

            expect($direction->resolve('de', 'ru', 'en', $guess)->pair())->toBe('en:ru');
        });

        it('treats a silent detector the same way', function () use ($direction) {
            expect($direction->resolve(null, 'ru', 'en', $direction->guess('случай', 'ru', 'en'))->pair())
                ->toBe('ru:en');
        });
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

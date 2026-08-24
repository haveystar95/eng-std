<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\SupportedLanguages;

/**
 * The pair rule, in the shape the search endpoints apply it: valid ⟺ one side is a language this
 * product teaches, the other is a language the catalogue names, and the two are different
 * (DECISIONS пп. 85, 134).
 */
beforeEach(function (): void {
    $this->supported = new SupportedLanguages();
});

it('accepts a pair either way round', function () {
    expect($this->supported->supports('en', 'ru'))->toBeTrue()
        ->and($this->supported->supports('ru', 'en'))->toBeTrue();
});

it('accepts a pair with no English in it', function () {
    // The v1 limit this class carried until RS-3. `ro` is taught, `ru` and `uk` are read.
    expect($this->supported->supports('ru', 'ro'))->toBeTrue()
        ->and($this->supported->supports('ro', 'uk'))->toBeTrue()
        ->and($this->supported->supports('tr', 'de'))->toBeTrue();
});

it('refuses a pair with nothing taught in it', function () {
    expect($this->supported->supports('ru', 'uk'))->toBeFalse()
        ->and($this->supported->supports('zh', 'ru'))->toBeFalse();
});

it('refuses a language outside the catalogue, on either side', function () {
    expect($this->supported->supports('en', 'sv'))->toBeFalse()
        ->and($this->supported->supports('sv', 'en'))->toBeFalse();
});

it('refuses a language into itself', function () {
    expect($this->supported->supports('en', 'en'))->toBeFalse()
        ->and($this->supported->supports('EN', ' en '))->toBeFalse();
});

it('names the taught side when only one side is taught', function () {
    expect($this->supported->soleTaughtSide('ru', 'ro'))->toBe('ro')
        ->and($this->supported->soleTaughtSide('en', 'ru'))->toBe('en');
});

it('refuses to guess when both sides are taught', function () {
    // `de → en` is German studied with English support OR English studied with German support, and
    // the direction cannot tell them apart. Null is the honest answer; the caller breaks the tie
    // with the profile (п. 147).
    expect($this->supported->soleTaughtSide('de', 'en'))->toBeNull();
});

it('still names one taught language for the app already on the phone', function () {
    expect(SupportedLanguages::LEGACY_TARGET)->toBe('en')
        ->and($this->supported->targets())->toContain(SupportedLanguages::LEGACY_TARGET);
});

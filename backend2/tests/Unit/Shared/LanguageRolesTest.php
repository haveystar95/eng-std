<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Service\LanguageCatalog;
use App\Modules\Shared\Domain\Service\LanguageRoles;

it('teaches exactly the seven languages of the capability list', function () {
    // Written out rather than derived: a test that reads its expectation out of the thing under
    // test proves nothing. DECISIONS п. 83.
    expect(LanguageRoles::taught())->toEqualCanonicalizing(['en', 'ro', 'pl', 'de', 'es', 'it', 'fr']);
});

it('does not teach the reference-only languages', function () {
    // zh and ja are a collection, an audio and a translation — no trainer carries them (пп. 84,
    // 136), so they cannot be the term side of a searched pair either. They are still languages a
    // learner may READ.
    expect(LanguageRoles::isTaught('zh'))->toBeFalse()
        ->and(LanguageRoles::isTaught('ja'))->toBeFalse()
        ->and(LanguageRoles::isSupport('zh'))->toBeTrue()
        ->and(LanguageRoles::isSupport('ja'))->toBeTrue();
});

it('lets the learner read in ANY language the catalogue names', function () {
    // The audience is not restricted (п. 85): reading takes a name, not a grader. This is the whole
    // difference from the `APP_NATIVE_LANGS` list this replaced, which was two languages by accident.
    expect(LanguageRoles::support())->toBe(LanguageCatalog::codes());

    foreach (LanguageCatalog::codes() as $code) {
        expect(LanguageRoles::isSupport($code))->toBeTrue();
    }
});

it('offers only languages it can name', function () {
    // A taught language the catalogue does not know would reach the model as a bare ISO code and
    // reach the learner's picker as nothing at all.
    foreach (LanguageRoles::taught() as $code) {
        expect(LanguageCatalog::knows($code))->toBeTrue();
    }
});

it('knows nothing about a language outside the catalogue', function () {
    expect(LanguageRoles::isTaught('sv'))->toBeFalse()
        ->and(LanguageRoles::isSupport('sv'))->toBeFalse();
});

it('reads a code the same however it is spelled', function () {
    expect(LanguageRoles::isTaught(' EN '))->toBeTrue()
        ->and(LanguageRoles::isSupport(' RU '))->toBeTrue();
});

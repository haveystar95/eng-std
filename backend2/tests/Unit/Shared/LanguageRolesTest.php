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

it('builds a plan in the seven targets of LANG-1, in the order the entry screen offers them', function () {
    // Written out, and ORDERED: the entry screen lists them in this order (наряд LANG-1 §7).
    expect(LanguageRoles::planTargets())->toBe(['en', 'pl', 'ro', 'es', 'it', 'de', 'fr']);
});

it('reads a plan in the nine natives of LANG-1, Belarusian among them', function () {
    expect(LanguageRoles::planNatives())->toBe(['ru', 'uk', 'be', 'pl', 'ro', 'es', 'it', 'de', 'fr'])
        ->and(LanguageRoles::planNatives())->toContain('be');
});

it('teaches in a plan only what the product teaches', function () {
    // The plan's list is typed out, so this is what keeps it honest: a plan target no trainer can
    // carry would be a plan whose every card is refused.
    foreach (LanguageRoles::planTargets() as $code) {
        expect(LanguageRoles::isTaught($code))->toBeTrue("{$code} is a plan target but is not taught");
    }
});

it('reads a plan only in a language the catalogue can name', function () {
    // A plan native the catalogue does not know would reach the entry screen as a bare code and the
    // model's prompt as «write in be».
    foreach (LanguageRoles::planNatives() as $code) {
        expect(LanguageCatalog::codes())->toContain($code);
    }
});

it('has no duplicate in either plan list', function () {
    expect(LanguageRoles::planTargets())->toHaveCount(count(array_unique(LanguageRoles::planTargets())))
        ->and(LanguageRoles::planNatives())->toHaveCount(count(array_unique(LanguageRoles::planNatives())));
});

it('does not read a plan in English, and does not read one in a language it only names', function () {
    // English is a target and not a native of a plan (наряд LANG-1 §7); Turkish and Portuguese are in the
    // catalogue — a collection may be read in them (п. 85) — but no plan pack exists for them.
    expect(LanguageRoles::planNatives())->not->toContain('en')
        ->and(LanguageRoles::planNatives())->not->toContain('tr')
        ->and(LanguageRoles::planNatives())->not->toContain('pt');
});

it('takes the first plan native of the device languages, by the primary subtag', function (array $locales, ?string $native) {
    expect(LanguageRoles::planNativeFromLocales($locales))->toBe($native);
})->with([
    // What Symfony's Request::getLanguages() gives for the header, best first.
    'Ukrainian phone' => [['uk_UA', 'uk'], 'uk'],
    'Belarusian phone' => [['be_BY'], 'be'],
    'Polish, bare' => [['pl'], 'pl'],
    'a hyphen, as a client may spell it' => [['de-AT'], 'de'],
    'upper case' => [['FR'], 'fr'],
    'English only — the framework default' => [['en_US', 'en'], null],
    'English first, a native after it' => [['en_US', 'ro'], 'ro'],
    'a language the catalogue names but no plan reads' => [['tr_TR', 'tr'], null],
    'nothing at all' => [[], null],
]);

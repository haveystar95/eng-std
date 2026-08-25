<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\EnrichmentValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * SYN-1d — the two gates the core's per-pair products pass, and the switches over them.
 *
 * The pilot numbers behind these rules are in `docs/syn-1-findings.md` §8: the transliteration is
 * judged by a question a machine can answer (which alphabet is this written in) and passed at 100%;
 * the synonym is judged by one it cannot (is this the same thing or a kind of it) and did not.
 */
it('accepts a hint written in the support language\'s own alphabet', function () {
    $v = new EnrichmentValidator();

    expect($v->transliterationFor('ru', 'комо эстас'))->toBe('комо эстас')
        ->and($v->transliterationFor('ru', 'чек-ин'))->toBe('чек-ин')
        ->and($v->transliterationFor('en', 'komo estas'))->toBe('komo estas');
});

it('refuses a hint carrying the alphabet the learner cannot read', function () {
    $v = new EnrichmentValidator();

    // One Latin word in a Russian hint defeats the field for the only reader it exists for — so
    // this is stricter than the majority rule the translation barrier uses («пароль от Wi-Fi»).
    expect($v->transliterationFor('ru', 'комо estás'))->toBeNull()
        ->and($v->transliterationFor('ru', 'como estas'))->toBeNull()
        ->and($v->transliterationFor('en', 'комо эстас'))->toBeNull();
});

it('refuses a hint that is an annotation rather than a reading', function () {
    $v = new EnrichmentValidator();

    expect($v->transliterationFor('ru', 'комо эстас (исп.)'))->toBeNull()
        ->and($v->transliterationFor('ru', 'ко́мо эста́с [2]'))->toBeNull()
        ->and($v->transliterationFor('ru', ''))->toBeNull()
        ->and($v->transliterationFor('ru', null))->toBeNull();
});

it('has no opinion about a language whose script it was never taught', function () {
    // Silence is a pass everywhere else in LanguagePurity, and an invented alphabet would be worse
    // than none: a support language the catalogue names but this check does not know still gets a
    // hint rather than losing the field entirely.
    expect((new EnrichmentValidator())->transliterationFor('xx', 'whatever this is'))->toBe('whatever this is');
});

it('refuses a synonym that is a KIND of the term — the rule three prompts could not teach', function () {
    $v = new EnrichmentValidator();

    // Measured on the SYN-1a pilot rows: kills 4 of 5 bad on v14.1 and 3 of 3 on v14.2 — and costs
    // 4 genuine ones in the same set. See the findings doc; the trade is deliberate.
    [$kept] = $v->synonymsFor(['bank account'], ['savings account']);
    expect($kept)->toBe([]);

    [$kept] = $v->synonymsFor(['credit card'], ['charge card']);
    expect($kept)->toBe([]);

    // A single word cannot share a head with anything, and that is where most real synonyms live.
    [$kept] = $v->synonymsFor(['purpose'], ['goal']);
    expect($kept)->toBe(['goal']);
});

it('ships the reading hint ON and the synonym OFF — the pilot decided each separately', function () {
    expect(config('services.generation.write_transliteration'))->toBeTrue()
        ->and(config('services.generation.write_synonyms'))->toBeFalse();
});

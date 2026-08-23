<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Service\LanguageCatalog;
use App\Modules\Shared\Domain\Service\LanguageName;

it('covers every language the capability matrix names', function () {
    // The list `docs/research/language-capability-matrix.md` names, split the way the matrix splits
    // it. Written out here rather than derived from the catalogue: a test that reads its
    // expectation out of the thing under test proves nothing.
    $taught = ['en', 'ro', 'pl', 'de', 'es', 'it', 'fr'];
    $referenceOnly = ['zh', 'ja'];
    $support = ['ru', 'uk', 'en'];

    $codes = LanguageCatalog::codes();

    foreach ([...$taught, ...$referenceOnly, ...$support] as $code) {
        expect($codes)->toContain($code);
    }
});

it('has no duplicate codes', function () {
    $codes = LanguageCatalog::codes();

    expect($codes)->toHaveCount(count(array_unique($codes)));
});

it('fills every column of every row', function () {
    foreach (LanguageCatalog::all() as $code => $entry) {
        expect($code)->toMatch('/^[a-z]{2}$/');
        expect(trim($entry['name']))->not->toBe('');
        expect(trim($entry['endonym']))->not->toBe('');
        expect(trim($entry['nameRu']))->not->toBe('');
        expect(trim($entry['flag']))->not->toBe('');
    }
});

it('knows Romanian, and names the LANGUAGE rather than the country', function () {
    // The app's picker shipped `România` — the country — as the endonym for months (QA-OBS-16),
    // while every backend copy of the table had no `ro` row at all and sent the model a bare code.
    expect(LanguageCatalog::entry('ro'))
        ->toBe(['name' => 'Romanian', 'endonym' => 'Română', 'nameRu' => 'Румынский', 'flag' => '🇷🇴']);
});

it('does not guess at a code it does not know', function () {
    // A prompt naming `sw` is a visible defect; a prompt naming "Swedish" for `sw` is an invisible one.
    expect(LanguageCatalog::knows('sw'))->toBeFalse();
    expect(LanguageCatalog::entry('sw'))->toBeNull();
    expect(LanguageName::of('sw'))->toBe('sw');
});

it('is the one table the prompt-side reader speaks for', function () {
    expect(LanguageName::of('ru'))->toBe('Russian');
    expect(LanguageName::of('ro'))->toBe('Romanian');
});

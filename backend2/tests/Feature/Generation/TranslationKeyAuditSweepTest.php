<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * QA-17, whole-store sweep. The audit used to judge ONE language per run — the one passed on the
 * command line — and the store's answer lived in two files taken at two different times. The
 * language nobody re-ran is exactly where an unreadable key survives, so the default is now: every
 * language the CONTENT has, discovered from the content itself.
 *
 * The other half tested here is the evidence. «группа: us/me» counts a row; the export has to say
 * WHICH word of the term went unanswered, or the proof-reader re-derives it by eye for every line.
 */
function seedSweepTerm(string $termId, string $text, string $lang, string $translation, string $trId): void
{
    DB::table('terms')->insert([
        'id' => $termId,
        'lang' => 'en',
        'text' => $text,
        'normalized_text' => mb_strtolower($text),
        'type' => 'phrase',
        'source' => 'ai',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('term_translations')->insert([
        'id' => $trId,
        'term_id' => $termId,
        'lang' => $lang,
        'text' => $translation,
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function sweepExportPath(): string
{
    return storage_path('app/testing/translation-keys-sweep.md');
}

beforeEach(function () {
    // The owner's own case, still broken — «нам» dropped.
    seedSweepTerm(
        str_pad('01SWEEPRU', 26, '0'),
        'Tell us about a challenge you faced',
        'ru',
        'Расскажите о вызове, с которым вы столкнулись',
        str_pad('01SWEEPTRU', 26, '0'),
    );
    // A Ukrainian key with the same defect. Nothing on the command line will mention `uk`.
    seedSweepTerm(
        str_pad('01SWEEPUK', 26, '0'),
        'Describe your experience',
        'uk',
        'Опишіть досвід',
        str_pad('01SWEEPTUK', 26, '0'),
    );
    // A clean pair, to prove the sweep judges rather than dumps.
    seedSweepTerm(
        str_pad('01SWEEPOK', 26, '0'),
        'withdraw cash',
        'ru',
        'снять наличные',
        str_pad('01SWEEPTOK', 26, '0'),
    );

    @unlink(sweepExportPath());
});

it('sweeps every language in the store without being told which ones exist', function () {
    $this->artisan('vocab:audit-translation-keys', ['--out' => sweepExportPath()])
        ->assertSuccessful();

    $export = (string) file_get_contents(sweepExportPath());

    expect($export)
        ->toContain('Просмотрено пар: **3**')
        ->toContain('Кандидатов: **2**')
        ->toContain('Tell us about a challenge you faced')
        ->toContain('Describe your experience')
        ->not->toContain('withdraw cash');
});

it('names the missing word of the term, with the forms that would have cleared it', function () {
    $this->artisan('vocab:audit-translation-keys', ['--out' => sweepExportPath()])
        ->assertSuccessful();

    $export = (string) file_get_contents(sweepExportPath());

    expect($export)
        ->toContain('`us` → нам/нас/мне/меня/я')
        ->toContain('`your` → ви/вас/вам/ваш/ваша/ваше/ваші/твій/тобі');
});

it('counts pairs per language and says where the rule is silent rather than clean', function () {
    // `es` has translations and no counterpart list. Zero candidates there is «not checked», and an
    // export that printed it like `ru`'s zero would be quietly wrong.
    seedSweepTerm(
        str_pad('01SWEEPES', 26, '0'),
        'Tell us about it',
        'es',
        'Cuéntenos sobre esto',
        str_pad('01SWEEPTES', 26, '0'),
    );

    $this->artisan('vocab:audit-translation-keys', ['--out' => sweepExportPath()])
        ->assertSuccessful();

    $export = (string) file_get_contents(sweepExportPath());

    expect($export)
        ->toContain('| `ru` | 2 | 1 | да |')
        ->toContain('| `uk` | 1 | 1 | да |')
        ->toContain('| `es` | 1 | 0 | **НЕТ — детектор здесь молчит** |');
});

it('still narrows to one language when asked, and says so in the header', function () {
    $this->artisan('vocab:audit-translation-keys', ['--source-lang' => 'ru', '--out' => sweepExportPath()])
        ->assertSuccessful();

    $export = (string) file_get_contents(sweepExportPath());

    expect($export)
        ->toContain('направление: `en` → `ru`')
        ->toContain('Просмотрено пар: **2**')
        ->not->toContain('Describe your experience');
});

it('breaks the candidates down by the decks that ask them', function () {
    DB::table('collections')->insert([
        'id' => str_pad('01SWEEPCOL', 26, '0'),
        'type' => 'system',
        'title' => 'Собеседование в IT',
        'source_lang' => 'ru',
        'target_lang' => 'en',
        'visibility' => 'public',
        'source' => 'curated',
        'items_count' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('collection_items')->insert([
        'id' => str_pad('01SWEEPITM', 26, '0'),
        'collection_id' => str_pad('01SWEEPCOL', 26, '0'),
        'term_id' => str_pad('01SWEEPRU', 26, '0'),
        'position' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('vocab:audit-translation-keys', ['--out' => sweepExportPath()])
        ->assertSuccessful();

    $export = (string) file_get_contents(sweepExportPath());

    expect($export)
        ->toContain('## Разбивка по коллекциям')
        ->toContain('| Собеседование в IT | 1 |')
        ->toContain('| — вне колод — | 1 |');
});

it('writes the export with the standard header, so the snapshot dates the data', function () {
    $this->artisan('vocab:audit-translation-keys', ['--out' => sweepExportPath()])
        ->assertSuccessful();

    $export = (string) file_get_contents(sweepExportPath());

    expect($export)->toStartWith('<!-- snapshot: ')
        ->and($export)->toContain('· head: ');
});

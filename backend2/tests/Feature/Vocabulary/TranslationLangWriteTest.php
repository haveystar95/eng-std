<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\CurateTerm;
use App\Modules\Vocabulary\Application\Command\CurateTermHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The other half of D-2: the writer.
 *
 * The retrospective language repair (RepairContentLanguageHandler) asks the model for Russian and
 * writes it back through CurateTerm. It used to rewrite the row's TEXT and leave its `lang` alone,
 * so 118 live rows ended up holding correct Russian under a `uk` or `de` label — which is what made
 * a language-aware reader see "no Russian translation" for terms that had one all along.
 */
const WRITE_TERM = '01TERMWRT0000000000000000W';

function seedMislabelled(): void
{
    DB::table('terms')->insert([
        'id' => WRITE_TERM,
        'lang' => 'en',
        'text' => 'Can I pay by card?',
        'normalized_text' => 'can i pay by card',
        'type' => 'phrase',
        'source' => 'ai',
        'created_at' => now(),
        'updated_at' => now()->subDay(),
    ]);

    DB::table('term_translations')->insert([
        'id' => str_pad('01TRW', 26, '0'),
        'term_id' => WRITE_TERM,
        'lang' => 'uk',
        'text' => 'Чи можу я заплатити карткою?',
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('moves the label with the text when a translation is rewritten', function () {
    seedMislabelled();

    app(CurateTermHandler::class)(new CurateTerm(
        TermId::fromString(WRITE_TERM),
        translation: 'Могу я оплатить картой?',
        translationLang: 'ru',
    ));

    $row = DB::table('term_translations')->where('term_id', WRITE_TERM)->first();

    expect($row->text)->toBe('Могу я оплатить картой?');
    expect($row->lang)->toBe('ru');                 // this is the half that used to be missed
    expect(DB::table('term_translations')->where('term_id', WRITE_TERM)->count())->toBe(1);
});

it('rewrites the row of the language it was asked for, not whichever sorted first', function () {
    seedMislabelled();
    // A Russian row with a HIGHER id: a writer picking by is_primary alone could land on the uk one.
    DB::table('term_translations')->insert([
        'id' => str_pad('01TRX', 26, '0'),
        'term_id' => WRITE_TERM,
        'lang' => 'ru',
        'text' => 'Могу я заплатить картой?',
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CurateTermHandler::class)(new CurateTerm(
        TermId::fromString(WRITE_TERM),
        translation: 'Можно оплатить картой?',
        translationLang: 'ru',
    ));

    $uk = DB::table('term_translations')->where('id', str_pad('01TRW', 26, '0'))->first();
    $ru = DB::table('term_translations')->where('id', str_pad('01TRX', 26, '0'))->first();

    // The Ukrainian row is legal data and is left exactly as it was.
    expect($uk->text)->toBe('Чи можу я заплатити карткою?')->and($uk->lang)->toBe('uk');
    expect($ru->text)->toBe('Можно оплатить картой?');
});

it('creates the translation in the asked-for language when the term has none', function () {
    DB::table('terms')->insert([
        'id' => WRITE_TERM,
        'lang' => 'en',
        'text' => 'to go',
        'normalized_text' => 'to go',
        'type' => 'phrase',
        'source' => 'ai',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    app(CurateTermHandler::class)(new CurateTerm(
        TermId::fromString(WRITE_TERM),
        translation: 'з собою',
        translationLang: 'uk',
    ));

    $row = DB::table('term_translations')->where('term_id', WRITE_TERM)->first();

    expect($row->lang)->toBe('uk')->and($row->is_primary)->toBeTrue();
});

// The delta sync detects a changed term by terms.updated_at alone — a translation edit that forgets
// to bump it is an edit the phone never hears about.
it('bumps the term so the change reaches the phone', function () {
    seedMislabelled();
    $before = DB::table('terms')->where('id', WRITE_TERM)->value('updated_at');

    app(CurateTermHandler::class)(new CurateTerm(
        TermId::fromString(WRITE_TERM),
        translation: 'Могу я оплатить картой?',
        translationLang: 'ru',
    ));

    expect(DB::table('terms')->where('id', WRITE_TERM)->value('updated_at'))->toBeGreaterThan($before);
});

<?php

declare(strict_types=1);

use App\Modules\Vocabulary\Application\Command\RelabelRepairedTranslations;
use App\Modules\Vocabulary\Application\Command\RelabelRepairedTranslationsHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

const RELABEL_TERM = '01TERMRELABEL0000000000000';

function seedTranslation(string $rowId, string $lang, string $text, bool $rewritten): void
{
    DB::table('term_translations')->insert([
        'id' => str_pad($rowId, 26, '0'),
        'term_id' => RELABEL_TERM,
        'lang' => $lang,
        'text' => $text,
        'is_primary' => true,
        'created_at' => now()->subDays(2),
        'updated_at' => $rewritten ? now() : now()->subDays(2),
    ]);
}

beforeEach(function (): void {
    DB::table('terms')->insert([
        'id' => RELABEL_TERM,
        'lang' => 'en',
        'text' => 'busy as a bee',
        'normalized_text' => 'busy as a bee',
        'type' => 'idiom',
        'source' => 'ai',
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);
});

function relabel(bool $apply = true): App\Modules\Vocabulary\Application\Dto\RelabelOutcome
{
    return app(RelabelRepairedTranslationsHandler::class)(new RelabelRepairedTranslations('ru', $apply));
}

it('relabels a row whose Russian text the repair already wrote', function () {
    seedTranslation('01TRR', 'de', 'Я хотел бы заказать', rewritten: true);

    $outcome = relabel();

    expect($outcome->relabelledCount())->toBe(1);
    expect(DB::table('term_translations')->where('id', str_pad('01TRR', 26, '0'))->value('lang'))->toBe('ru');
    // The text is not touched — this command asserts nothing about content.
    expect(DB::table('term_translations')->where('id', str_pad('01TRR', 26, '0'))->value('text'))
        ->toBe('Я хотел бы заказать');
});

// The whole risk of this command. «працьовитий як бджола» contains no Ukrainian-only letter, so the
// charset check alone would call it Russian — and relabelling it would destroy legitimate data. The
// row was never rewritten, so it is original content and stays.
it('leaves a genuinely Ukrainian row alone even though the charset check passes it', function () {
    seedTranslation('01TRU', 'uk', 'працьовитий як бджола', rewritten: false);

    $outcome = relabel();

    expect($outcome->relabelledCount())->toBe(0);
    expect($outcome->keptCount())->toBe(1);
    expect($outcome->kept[0]['why'])->toContain('никогда не переписывали');
    expect(DB::table('term_translations')->where('id', str_pad('01TRU', 26, '0'))->value('lang'))->toBe('uk');
});

it('leaves a rewritten row that still carries Ukrainian letters', function () {
    seedTranslation('01TRI', 'uk', 'потрібно з\'ясувати', rewritten: true);

    $outcome = relabel();

    expect($outcome->relabelledCount())->toBe(0);
    expect($outcome->kept[0]['why'])->toContain('буквы');
});

it('leaves a rewritten row written in another script', function () {
    seedTranslation('01TRG', 'de', 'Kann ich mit Karte bezahlen?', rewritten: true);

    $outcome = relabel();

    expect($outcome->relabelledCount())->toBe(0);
    expect($outcome->kept[0]['why'])->toContain('не письмом');
});

it('writes nothing on a dry run', function () {
    seedTranslation('01TRR', 'de', 'Я хотел бы заказать', rewritten: true);

    $outcome = relabel(apply: false);

    expect($outcome->relabelledCount())->toBe(1);      // it would relabel this one
    expect($outcome->applied)->toBeFalse();
    expect(DB::table('term_translations')->where('id', str_pad('01TRR', 26, '0'))->value('lang'))->toBe('de');
});

// The delta sync detects a changed term by terms.updated_at alone: a relabel the phone never hears
// about leaves the wrongly-labelled row in the local mirror forever.
it('bumps the term so the relabel reaches the phone', function () {
    seedTranslation('01TRR', 'de', 'Я хотел бы заказать', rewritten: true);
    $before = DB::table('terms')->where('id', RELABEL_TERM)->value('updated_at');

    relabel();

    expect(DB::table('terms')->where('id', RELABEL_TERM)->value('updated_at'))->toBeGreaterThan($before);
});

it('is idempotent — a second pass finds nothing', function () {
    seedTranslation('01TRR', 'de', 'Я хотел бы заказать', rewritten: true);

    relabel();

    expect(relabel()->relabelledCount())->toBe(0);
});

it('never touches rows already labelled with the target language', function () {
    seedTranslation('01TRN', 'ru', 'трудолюбивый как пчёлка', rewritten: true);

    $outcome = relabel();

    expect($outcome->relabelledCount())->toBe(0)->and($outcome->keptCount())->toBe(0);
});

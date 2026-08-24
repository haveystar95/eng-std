<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Query\EnrichmentTargetReader;
use App\Modules\Vocabulary\Application\Query\TermContentReader;
use App\Modules\Vocabulary\Application\Query\TermEnrichmentExportReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * D-2. A term is global and collects translation rows: a regeneration merges another one in, and
 * rows in different languages sit side by side legitimately. Every reader used to take
 * `orderByDesc('is_primary')` and the first row back, with no language and no second key — so which
 * translation a learner was ASKED was decided by physical row order.
 *
 * The rows below are seeded so that a language-blind reader fails immediately: the Ukrainian row has
 * the LOWER id, so "first row" without a language filter is the Ukrainian one.
 */
const PICK_TERM = '01TERMPCK0000000000000000K';

function seedTwoPrimaries(): void
{
    DB::table('terms')->insert([
        'id' => PICK_TERM,
        'lang' => 'en',
        'text' => 'busy as a bee',
        'normalized_text' => 'busy as a bee',
        'type' => 'idiom',
        'source' => 'ai',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Both primary — which is exactly the live shape: ImportTerm marks every merged translation
    // primary, so 28 terms in the live database carry more than one.
    foreach ([['01TRA', 'uk', 'працьовитий як бджола'], ['01TRB', 'ru', 'трудолюбивый как пчёлка']] as [$id, $lang, $text]) {
        DB::table('term_translations')->insert([
            'id' => str_pad($id, 26, '0'),
            'term_id' => PICK_TERM,
            'lang' => $lang,
            'text' => $text,
            'is_primary' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    seedExample([
        'id' => str_pad('01EXP', 26, '0'),
        'term_id' => PICK_TERM,
        'sentence' => 'She is busy as a bee today.',
        'translation' => 'Она сегодня трудится как пчёлка.',
        'source' => 'ai',
    ]);
}

it('gives each language its own translation when two primaries sit side by side', function () {
    seedTwoPrimaries();
    $ids = [TermId::fromString(PICK_TERM)];

    $ru = app(TermContentReader::class)->byIds($ids, 'ru')[PICK_TERM];
    $uk = app(TermContentReader::class)->byIds($ids, 'uk')[PICK_TERM];

    expect($ru->translation)->toBe('трудолюбивый как пчёлка');
    // The Ukrainian row is legitimate data — it is simply not the row a Russian speaker is asked.
    expect($uk->translation)->toBe('працьовитий як бджола');
});

it('keeps the same answer across repeated reads and after the table is churned', function () {
    seedTwoPrimaries();
    $ids = [TermId::fromString(PICK_TERM)];
    $read = fn (): ?string => app(TermContentReader::class)->byIds($ids, 'ru')[PICK_TERM]->translation;

    expect($read())->toBe('трудолюбивый как пчёлка');

    // An UPDATE rewrites the tuple at the end of the page, so an unordered scan starts handing back
    // a different row from here on. This is the churn that made the live API and psql disagree.
    DB::table('term_translations')->where('term_id', PICK_TERM)->update(['updated_at' => now()->addMinute()]);

    expect($read())->toBe('трудолюбивый как пчёлка');
});

it('falls back to another language deterministically when the asked-for one is absent', function () {
    seedTwoPrimaries();
    DB::table('term_translations')->where('lang', 'ru')->delete();
    $ids = [TermId::fromString(PICK_TERM)];

    // A card whose question is in the wrong language still beats a card with no question — but the
    // fallback must be the SAME row every time, not a coin flip.
    $read = fn (): ?string => app(TermContentReader::class)->byIds($ids, 'ru')[PICK_TERM]->translation;

    expect($read())->toBe('працьовитий як бджола')
        ->and($read())->toBe('працьовитий як бджола');
});

it('breaks a tie inside one language by id, not by insertion order', function () {
    seedTwoPrimaries();
    // A second Russian row with a HIGHER id: the first-written one keeps the card.
    DB::table('term_translations')->insert([
        'id' => str_pad('01TRZ', 26, '0'),
        'term_id' => PICK_TERM,
        'lang' => 'ru',
        'text' => 'занятой как пчела',
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $got = app(TermContentReader::class)->byIds([TermId::fromString(PICK_TERM)], 'ru')[PICK_TERM];

    expect($got->translation)->toBe('трудолюбивый как пчёлка');
});

it('prefers a primary row over a non-primary one inside the asked-for language', function () {
    seedTwoPrimaries();
    // Lower id than the primary Russian row, but not primary — primary still wins.
    DB::table('term_translations')->insert([
        'id' => str_pad('01TR0', 26, '0'),
        'term_id' => PICK_TERM,
        'lang' => 'ru',
        'text' => 'вкалывает',
        'is_primary' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $got = app(TermContentReader::class)->byIds([TermId::fromString(PICK_TERM)], 'ru')[PICK_TERM];

    expect($got->translation)->toBe('трудолюбивый как пчёлка');
});

// The станок and the export read the same term through their own readers. If they disagreed with the
// card, the model would be briefed on one translation while the learner is asked another, and a
// proofreader would be correcting a row nobody sees.
it('gives the станок and the export the same row the card shows', function () {
    seedTwoPrimaries();
    $ids = [TermId::fromString(PICK_TERM)];

    $card = app(TermContentReader::class)->byIds($ids, 'ru')[PICK_TERM];
    $target = app(EnrichmentTargetReader::class)->byIds($ids, 'ru')[PICK_TERM];
    $export = app(TermEnrichmentExportReader::class)->byIds($ids, 'ru')[PICK_TERM];

    expect($target->translation)->toBe($card->translation)
        ->and($export->translation)->toBe($card->translation);
});

// The brief the станок sends the model has to say what it actually found, not what was asked for:
// on the fallback path the translation is Ukrainian, and telling the model it is Russian would
// describe content that is not there.
it('reports the language actually found, not the one requested', function () {
    seedTwoPrimaries();
    $ids = [TermId::fromString(PICK_TERM)];

    expect(app(EnrichmentTargetReader::class)->byIds($ids, 'ru')[PICK_TERM]->translationLang)->toBe('ru');

    DB::table('term_translations')->where('lang', 'ru')->delete();

    expect(app(EnrichmentTargetReader::class)->byIds($ids, 'ru')[PICK_TERM]->translationLang)->toBe('uk');
});

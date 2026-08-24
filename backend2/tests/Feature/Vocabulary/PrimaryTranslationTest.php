<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * The dedup merge is where a term's translations accumulate, so both halves of the rule have to
 * hold through the WRITE and not only inside the aggregate:
 *
 *  - A7: a language never ends up with two primaries;
 *  - SYN-1: the primary a term already has is not re-decided by a later generation. The newcomer
 *    is kept as an alternative, so nothing is lost and the question on the card does not move
 *    under a learner who is already answering it.
 */
it('keeps the existing primary and files the newcomer beside it on a dedup merge', function () {
    $import = app(ImportTermHandler::class);

    $first = $import(new ImportTerm(
        lang: new LanguageCode('en'),
        text: 'stay calm',
        type: 'phrase',
        pos: null,
        source: 'ai',
        translations: [new TranslationInput(new LanguageCode('ru'), 'Оставайтесь спокойны', isPrimary: true)],
        promptVersion: 'v9',
        generationModel: 'gpt-4o',
    ));

    $second = $import(new ImportTerm(
        lang: new LanguageCode('en'),
        text: 'Stay calm',
        type: 'phrase',
        pos: null,
        source: 'ai',
        translations: [new TranslationInput(new LanguageCode('ru'), 'оставаться спокойным', isPrimary: true)],
        promptVersion: 'v11',
        generationModel: 'gpt-5.4',
    ));

    expect($second->value)->toBe($first->value);

    $rows = DB::table('term_translations')->where('term_id', $first->value)->orderBy('text')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->where('is_primary', true))->toHaveCount(1)
        ->and($rows->firstWhere('is_primary', true)->text)->toBe('Оставайтесь спокойны');

    // The newcomer is stored, not thrown away — and it carries its own provenance.
    $alternative = $rows->firstWhere('text', 'оставаться спокойным');
    expect($alternative->is_primary)->toBeFalse()
        ->and($alternative->prompt_version)->toBe('v11');
});

it('touches the term so a reading added beside the primary reaches an already-synced device', function () {
    $import = app(ImportTermHandler::class);

    $id = $import(new ImportTerm(
        lang: new LanguageCode('en'),
        text: 'cash register',
        type: 'phrase',
        pos: null,
        source: 'ai',
        translations: [new TranslationInput(new LanguageCode('ru'), 'касса', isPrimary: true)],
    ));

    DB::table('terms')->where('id', $id->value)->update(['updated_at' => now()->subDay()]);
    $before = DB::table('terms')->where('id', $id->value)->value('updated_at');

    $import(new ImportTerm(
        lang: new LanguageCode('en'),
        text: 'cash register',
        type: 'phrase',
        pos: null,
        source: 'ai',
        translations: [new TranslationInput(new LanguageCode('ru'), 'кассовый аппарат', isPrimary: true)],
    ));

    expect(DB::table('terms')->where('id', $id->value)->value('updated_at'))->not->toBe($before);
});

it('re-importing exactly the same translation changes nothing at all', function () {
    $import = app(ImportTermHandler::class);

    $id = $import(new ImportTerm(
        lang: new LanguageCode('en'),
        text: 'invoice',
        type: 'word',
        pos: null,
        source: 'ai',
        translations: [new TranslationInput(new LanguageCode('ru'), 'счёт', isPrimary: true)],
    ));

    DB::table('terms')->where('id', $id->value)->update(['updated_at' => now()->subDay()]);
    $before = DB::table('terms')->where('id', $id->value)->value('updated_at');

    $import(new ImportTerm(
        lang: new LanguageCode('en'),
        text: 'invoice',
        type: 'word',
        pos: null,
        source: 'ai',
        translations: [new TranslationInput(new LanguageCode('ru'), 'счёт', isPrimary: true)],
    ));

    expect(DB::table('term_translations')->where('term_id', $id->value)->count())->toBe(1)
        ->and(DB::table('terms')->where('id', $id->value)->value('updated_at'))->toBe($before);
});

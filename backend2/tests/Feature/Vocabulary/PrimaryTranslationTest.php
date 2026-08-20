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
 * A7 on the production path: the dedup merge is where the doubled primaries came from, so the rule
 * has to hold through the WRITE, not only inside the aggregate. The repository stamps provenance on
 * create only — `is_primary` is the one column an existing row still has to hear about.
 */
it('demotes the previous primary in the table when a dedup hit brings a new one', function () {
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
        ->and($rows->firstWhere('is_primary', true)->text)->toBe('оставаться спокойным');

    // The demoted row keeps its text AND its provenance: it is still the row that prompt wrote, it
    // has only stopped being the question on the card.
    $demoted = $rows->firstWhere('text', 'Оставайтесь спокойны');
    expect($demoted->is_primary)->toBeFalse()
        ->and($demoted->prompt_version)->toBe('v9');
});

it('touches the term so a re-pinned primary reaches an already-synced device', function () {
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

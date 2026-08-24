<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermEnrichment;
use App\Modules\Vocabulary\Application\Command\ImportTermEnrichmentHandler;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Dto\TermSynonymInput;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;
use App\Modules\Shared\Domain\ValueObject\TermId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function synonymTerm(string $text = 'purpose'): TermId
{
    return app(ImportTermHandler::class)(new ImportTerm(
        lang: new LanguageCode('en'),
        text: $text,
        type: 'word',
        pos: null,
        source: 'ai',
        translations: [new TranslationInput(new LanguageCode('ru'), 'цель', isPrimary: true)],
    ));
}

it('stores synonyms as rows, in the term\'s own language', function () {
    $termId = synonymTerm();

    app(ImportTermEnrichmentHandler::class)(new ImportTermEnrichment(
        termId: $termId,
        exampleId: null,
        variants: [],
        distractors: [],
        generatorVersion: 'mech-v14',
        synonyms: [new TermSynonymInput('goal'), new TermSynonymInput('aim')],
    ));

    $rows = DB::table('term_synonyms')->where('term_id', $termId->value)->orderBy('text')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('text')->all())->toBe(['aim', 'goal'])
        // The language is the TERM's — read off the term, never taken from the caller.
        ->and($rows->pluck('lang')->unique()->all())->toBe(['en'])
        ->and($rows->pluck('source')->unique()->all())->toBe(['auto']);
});

it('is idempotent and never downgrades a curated row to auto', function () {
    $termId = synonymTerm();
    $handler = app(ImportTermEnrichmentHandler::class);

    $handler(new ImportTermEnrichment(
        termId: $termId, exampleId: null, variants: [], distractors: [],
        generatorVersion: 'mech-v14',
        synonyms: [new TermSynonymInput('goal', 'curated')],
    ));
    $handler(new ImportTermEnrichment(
        termId: $termId, exampleId: null, variants: [], distractors: [],
        generatorVersion: 'mech-v14',
        synonyms: [new TermSynonymInput('goal'), new TermSynonymInput('aim')],
    ));

    $rows = DB::table('term_synonyms')->where('term_id', $termId->value)->orderBy('text')->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->firstWhere('text', 'goal')->source)->toBe('curated');
});

it('touches the term so synonyms reach an already-synced device', function () {
    $termId = synonymTerm();
    DB::table('terms')->where('id', $termId->value)->update(['updated_at' => now()->subDay()]);
    $before = DB::table('terms')->where('id', $termId->value)->value('updated_at');

    app(ImportTermEnrichmentHandler::class)(new ImportTermEnrichment(
        termId: $termId, exampleId: null, variants: [], distractors: [],
        generatorVersion: 'mech-v14',
        synonyms: [new TermSynonymInput('goal')],
    ));

    expect(DB::table('terms')->where('id', $termId->value)->value('updated_at'))->not->toBe($before);
});

it('canonicalises a synonym on the way in, like every other content string', function () {
    $termId = synonymTerm('factură');

    app(ImportTermEnrichmentHandler::class)(new ImportTermEnrichment(
        termId: $termId, exampleId: null, variants: [], distractors: [],
        generatorVersion: 'mech-v14',
        // The CEDILLA spelling, which is what a keyboard and a model both produce.
        synonyms: [new TermSynonymInput("chitan\u{0163}ă")],
    ));

    expect(DB::table('term_synonyms')->where('term_id', $termId->value)->value('text'))
        ->toBe("chitan\u{021B}ă");
});

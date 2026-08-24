<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\FreshCore;
use App\Modules\Generation\Application\Service\CoreReplacement;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Dto\ExampleInput;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;
use App\Modules\Vocabulary\Application\Query\StaleCoreReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** A term as an older prompt left it: a key, a pinned example, and machinery hanging off it. */
function staleCoreTerm(string $promptVersion = 'v9'): string
{
    $id = app(ImportTermHandler::class)(new ImportTerm(
        lang: new LanguageCode('en'),
        text: 'bank',
        type: 'word',
        pos: null,
        source: 'ai',
        translations: [new TranslationInput(new LanguageCode('ru'), 'банк', isPrimary: true)],
        ipa: 'bæŋk',
        examples: [new ExampleInput('I opened a bank account.', 'Я открыл счёт в банке.', new LanguageCode('ru'))],
        cefr: 'B1',
        promptVersion: $promptVersion,
        generationModel: 'gpt-4o',
    ))->value;

    $exampleId = (string) DB::table('term_examples')->where('term_id', $id)->orderBy('id')->value('id');

    // One distractor that will still describe the NEW sentence, one that only describes the old.
    DB::table('example_distractors')->insert([
        [
            'id' => Ulid::generate(),
            'example_id' => $exampleId,
            'sentence' => 'The bank close at five.',
            'error_type' => 'tense',
            'error_span' => 'close',
            'correction' => 'closes',
            'generator_version' => 'mech-v12',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => Ulid::generate(),
            'example_id' => $exampleId,
            'sentence' => 'I opened bank account.',
            'error_type' => 'article',
            'error_span' => 'opened bank',
            'correction' => 'opened a bank',
            'generator_version' => 'mech-v12',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    return $id;
}

it('reads a term as stale only when its passport is not the answering prompt', function () {
    $stale = staleCoreTerm('v9');
    $current = app(ImportTermHandler::class)(new ImportTerm(
        lang: new LanguageCode('en'),
        text: 'deposit',
        type: 'word',
        pos: null,
        source: 'ai',
        translations: [new TranslationInput(new LanguageCode('ru'), 'вклад', isPrimary: true)],
        promptVersion: 'v11',
    ))->value;

    $reader = app(StaleCoreReader::class);

    expect($reader->idsNotWrittenBy([$stale, $current], 'v11'))->toBe([$stale])
        ->and($reader->idsNotWrittenBy([$stale, $current], 'v9'))->toBe([$current])
        ->and($reader->idsNotWrittenBy([], 'v11'))->toBe([]);
});

it('replaces a stale core in place: the passport moves, the example row does not', function () {
    $termId = staleCoreTerm('v9');
    $exampleIdBefore = (string) DB::table('term_examples')->where('term_id', $termId)->orderBy('id')->value('id');

    app(CoreReplacement::class)->apply(
        \App\Modules\Shared\Domain\ValueObject\TermId::fromString($termId),
        new FreshCore(
            translation: 'банк (учреждение)',
            ipa: 'bæŋk',
            cefr: 'A2',
            example: 'The bank closes at five.',
            exampleTranslation: 'Банк закрывается в пять.',
        ),
        'ru',
        'v11',
        'gpt-5.4',
    );

    $term = DB::table('terms')->where('id', $termId)->first();
    expect($term->prompt_version)->toBe('v11')
        ->and($term->generation_model)->toBe('gpt-5.4')
        ->and($term->cefr)->toBe('A2');

    // The key is rewritten and it is the ONLY primary (A7).
    $translations = DB::table('term_translations')->where('term_id', $termId)->get();
    expect($translations)->toHaveCount(1)
        ->and($translations[0]->text)->toBe('банк (учреждение)')
        ->and($translations[0]->is_primary)->toBeTrue()
        ->and($translations[0]->prompt_version)->toBe('v11');

    // The example row is UPDATED, not deleted and re-inserted — the A1 repair. Its id is what the
    // distractors point at with cascadeOnDelete, so a new id would take them all down with it.
    $example = DB::table('term_examples')->where('term_id', $termId)->first();
    expect($example->id)->toBe($exampleIdBefore)
        ->and($example->sentence)->toBe('The bank closes at five.')
        ->and($example->source)->toBe('ai')
        ->and($example->prompt_version)->toBe('v11');

    // …and only the distractor the new sentence orphaned is gone.
    $distractors = DB::table('example_distractors')->where('example_id', $exampleIdBefore)->pluck('sentence')->all();
    expect($distractors)->toBe(['The bank close at five.']);
});

it('leaves progress and the review log untouched when it replaces a core', function () {
    $termId = staleCoreTerm('v9');

    $before = [
        DB::table('user_term_progress')->count(),
        DB::table('reviews')->count(),
    ];

    app(CoreReplacement::class)->apply(
        \App\Modules\Shared\Domain\ValueObject\TermId::fromString($termId),
        new FreshCore(translation: 'банк (учреждение)', example: 'The bank closes at five.'),
        'ru',
        'v11',
        'gpt-5.4',
    );

    expect([DB::table('user_term_progress')->count(), DB::table('reviews')->count()])->toBe($before);
});

<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Service\ExampleReplacement;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\CurateTerm;
use App\Modules\Vocabulary\Application\Command\CurateTermHandler;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Dto\ExampleInput;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;
use App\Modules\Vocabulary\Application\Port\TermReviewWriter;
use App\Modules\Vocabulary\Application\Dto\SupportLanguages;
use App\Modules\Vocabulary\Application\Query\TermContentReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * `term_examples.sentence_translation` is DEPRECATED and nothing may write it.
 *
 * The column is still there on purpose — phase A of the multilanguage move lands in several steps
 * and a column dropped mid-flight is a rollback nobody can perform — which is exactly the situation
 * where a forgotten writer keeps filling it and the two copies drift. So the guard is a test rather
 * than a schema constraint: every path that writes an example is run for real, and the column has to
 * come out of it NULL.
 *
 * It is deliberately a behaviour test and not a grep. A grep over the source proves nobody typed the
 * column name; running the writers proves nobody wrote the column, which is the thing that matters
 * and the thing a mass-assignment or a `SELECT *` round-trip could still do by accident.
 */
function everyExampleTranslationColumnIsNull(): bool
{
    return DB::table('term_examples')->whereNotNull('sentence_translation')->count() === 0;
}

it('leaves the deprecated column alone when a term is imported', function () {
    app(ImportTermHandler::class)(new ImportTerm(
        lang: new LanguageCode('en'),
        text: 'withdraw cash',
        type: 'phrase',
        pos: null,
        source: 'ai',
        translations: [new TranslationInput(new LanguageCode('ru'), 'снять наличные', isPrimary: true)],
        examples: [new ExampleInput(
            'I need to withdraw cash.',
            'Мне нужно снять наличные.',
            new LanguageCode('ru'),
        )],
    ));

    expect(everyExampleTranslationColumnIsNull())->toBeTrue()
        ->and(DB::table('example_translations')->where('lang', 'ru')->value('text'))
        ->toBe('Мне нужно снять наличные.');
});

it('leaves the deprecated column alone when an example is replaced', function () {
    [$user] = learner();
    [, $termId] = seedCollectionWith($user, 'withdraw cash', 'снять наличные');
    seedExample(['term_id' => $termId, 'sentence' => 'An old sentence.', 'translation' => 'Старое.']);

    app(ExampleReplacement::class)->apply(
        TermId::fromString($termId),
        'I need to withdraw cash from the account.',
        'Мне нужно снять наличные со счёта.',
        'ru',
        'ex-regen.v2',
        'gpt-4o-mini',
    );

    expect(everyExampleTranslationColumnIsNull())->toBeTrue()
        ->and(DB::table('example_translations')->where('lang', 'ru')->value('text'))
        ->toBe('Мне нужно снять наличные со счёта.');
});

it('leaves the deprecated column alone when the panel curates an example', function () {
    [$user] = learner();
    [, $termId] = seedCollectionWith($user, 'withdraw cash', 'снять наличные');
    $exampleId = seedExample(['term_id' => $termId, 'sentence' => 'An old sentence.', 'translation' => 'Старое.']);

    app(CurateTermHandler::class)(new CurateTerm(
        TermId::fromString($termId),
        exampleId: $exampleId,
        exampleSentence: 'A curated sentence.',
        exampleTranslation: 'Правленое предложение.',
    ));

    expect(everyExampleTranslationColumnIsNull())->toBeTrue()
        ->and(DB::table('example_translations')->where('term_example_id', $exampleId)->where('lang', 'ru')->value('text'))
        ->toBe('Правленое предложение.');
});

it('leaves the deprecated column alone when a review corrects a gloss', function () {
    [$user] = learner();
    [, $termId] = seedCollectionWith($user, 'withdraw cash', 'снять наличные');
    $exampleId = seedExample(['term_id' => $termId, 'sentence' => 'A sentence.', 'translation' => 'Старое.']);

    $hit = app(TermReviewWriter::class)->setPinnedExampleTranslation($termId, 'Исправленное.', 'ru');

    expect($hit)->toBe(1)
        ->and(everyExampleTranslationColumnIsNull())->toBeTrue()
        ->and(DB::table('example_translations')->where('term_example_id', $exampleId)->where('lang', 'ru')->value('text'))
        ->toBe('Исправленное.');
});

/**
 * The wire did not move.
 *
 * The gloss changed table underneath `/sync`; the promise is that the phone cannot tell. Written
 * against the delta rather than against the reader, because the reader is only the mechanism —
 * what must not change is what the device receives.
 */
it('serves the same example_translation through /sync as before the move', function () {
    [$user, $token] = learner();
    [, $termId] = seedCollectionWith($user, 'withdraw cash', 'снять наличные');
    seedExample([
        'term_id' => $termId,
        'sentence' => 'I need to withdraw cash.',
        'translation' => 'Мне нужно снять наличные.',
    ]);

    $term = collect(sync($this, $token)['changes']['terms'])->firstWhere('id', $termId);
    $onCard = app(TermContentReader::class)->byIds([TermId::fromString($termId)], SupportLanguages::uniform('ru'))[$termId];

    expect($term['example'])->toBe('I need to withdraw cash.')
        ->and($term['example_translation'])->toBe('Мне нужно снять наличные.')
        ->and($term['example_translation'])->toBe($onCard->exampleTranslation);
});

it('shows the learner their OWN language when an example is glossed in two', function () {
    [$user, $token] = learner();
    [, $termId] = seedCollectionWith($user, 'withdraw cash', 'снять наличные');
    $exampleId = seedExample([
        'term_id' => $termId,
        'sentence' => 'I need to withdraw cash.',
        'translation' => 'Мне нужно снять наличные.',
    ]);
    DB::table('example_translations')->insert([
        'id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
        'term_example_id' => $exampleId,
        'lang' => 'uk',
        'text' => 'Мені треба зняти готівку.',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $read = fn (string $lang): ?string => app(TermContentReader::class)
        ->byIds([TermId::fromString($termId)], SupportLanguages::uniform($lang))[$termId]->exampleTranslation;

    // The whole point of the move: one example, two glosses, and each learner gets theirs. The old
    // column could hold only one of these and said nothing about whose it was.
    // Which row the fallback lands on is «lowest id», and the two rows here are written in the same
    // millisecond — inside which a ULID is 16 random characters and not an order (see Ulid). So the
    // expectation is READ, not written out: the claim under test is that the fallback is
    // deterministic and picks the lowest id, and a literal here made the test a coin flip.
    $lowestId = (string) DB::table('example_translations')
        ->where('term_example_id', $exampleId)->orderBy('id')->value('text');

    expect($read('ru'))->toBe('Мне нужно снять наличные.')
        ->and($read('uk'))->toBe('Мені треба зняти готівку.')
        // A language with no gloss falls back rather than blanking the card — see
        // ExampleTranslationPick.
        ->and($read('de'))->toBe($lowestId);

    // And the device receives the learner's own, not whichever row came first.
    $term = collect(sync($this, $token)['changes']['terms'])->firstWhere('id', $termId);
    expect($term['example_translation'])->toBe('Мне нужно снять наличные.');
});

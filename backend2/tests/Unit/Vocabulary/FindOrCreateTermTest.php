<?php

declare(strict_types=1);

use App\Modules\Vocabulary\Application\Command\FindOrCreateTerm;
use App\Modules\Vocabulary\Application\Command\FindOrCreateTermHandler;
use App\Modules\Vocabulary\Domain\Service\TermNormalizer;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Domain\ValueObject\PartOfSpeech;
use App\Modules\Vocabulary\Domain\ValueObject\TermSource;
use App\Modules\Vocabulary\Domain\ValueObject\TermText;
use App\Modules\Vocabulary\Domain\ValueObject\TermType;
use App\Modules\Vocabulary\Domain\ValueObject\Translation;
use Tests\Doubles\FixedClock;
use Tests\Doubles\InMemoryTermRepository;

function makeHandler(InMemoryTermRepository $repo): FindOrCreateTermHandler
{
    return new FindOrCreateTermHandler($repo, new TermNormalizer(), new FixedClock(new DateTimeImmutable('2026-07-27T00:00:00Z')));
}

it('creates a new term and returns its id', function () {
    $repo = new InMemoryTermRepository();
    $id = makeHandler($repo)(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('Bank'), TermType::Word, PartOfSpeech::Noun, TermSource::Ai,
        [new Translation(new LanguageCode('ru'), 'банк', true)],
    ));

    expect($repo->count())->toBe(1)
        ->and($repo->findById($id))->not->toBeNull();
});

it('deduplicates a term differing only by case and whitespace', function () {
    $repo = new InMemoryTermRepository();
    $handler = makeHandler($repo);

    $first = $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('Bank'), TermType::Word, PartOfSpeech::Noun, TermSource::Ai,
    ));
    $second = $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('  bank '), TermType::Word, PartOfSpeech::Noun, TermSource::User,
    ));

    expect($second->value)->toBe($first->value)
        ->and($repo->count())->toBe(1);
});

it('merges new translations into an existing term', function () {
    $repo = new InMemoryTermRepository();
    $handler = makeHandler($repo);

    $id = $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('bank'), TermType::Word, PartOfSpeech::Noun, TermSource::Ai,
        [new Translation(new LanguageCode('ru'), 'банк', true)],
    ));
    $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('bank'), TermType::Word, PartOfSpeech::Noun, TermSource::User,
        [new Translation(new LanguageCode('ru'), 'берег', false)],
    ));

    expect($repo->findById($id)?->translations())->toHaveCount(2);
});

it('leaves exactly one primary translation when a dedup hit brings a different one (A7)', function () {
    $repo = new InMemoryTermRepository();
    $handler = makeHandler($repo);

    $id = $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('stay calm'), TermType::Phrase, null, TermSource::Ai,
        [new Translation(new LanguageCode('ru'), 'Оставайтесь спокойны', true)],
    ));
    $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('stay calm'), TermType::Phrase, null, TermSource::Ai,
        [new Translation(new LanguageCode('ru'), 'оставаться спокойным', true)],
    ));

    $translations = $repo->findById($id)?->translations() ?? [];
    $primary = array_values(array_filter($translations, static fn (Translation $t): bool => $t->isPrimary));

    // The older reading is demoted, never dropped: it stays a legitimate alternative.
    expect($translations)->toHaveCount(2)
        ->and($primary)->toHaveCount(1)
        ->and($primary[0]->text)->toBe('оставаться спокойным');
});

it('demotes only the primary of the SAME language', function () {
    $repo = new InMemoryTermRepository();
    $handler = makeHandler($repo);

    $id = $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('bank'), TermType::Word, PartOfSpeech::Noun, TermSource::Ai,
        [new Translation(new LanguageCode('uk'), 'банк', true)],
    ));
    $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('bank'), TermType::Word, PartOfSpeech::Noun, TermSource::Ai,
        [new Translation(new LanguageCode('ru'), 'банк', true)],
    ));

    $primary = array_values(array_filter(
        $repo->findById($id)?->translations() ?? [],
        static fn (Translation $t): bool => $t->isPrimary,
    ));

    // A Ukrainian primary beside a Russian one is not a defect — it is one question per language.
    expect($primary)->toHaveCount(2);
});

it('keeps the existing primary when a dedup hit repeats the same translation', function () {
    $repo = new InMemoryTermRepository();
    $handler = makeHandler($repo);

    $id = $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('bank'), TermType::Word, PartOfSpeech::Noun, TermSource::Ai,
        [new Translation(new LanguageCode('ru'), 'банк', true)],
    ));
    // A non-primary line arriving beside it must not become the question…
    $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('bank'), TermType::Word, PartOfSpeech::Noun, TermSource::User,
        [new Translation(new LanguageCode('ru'), 'берег', false)],
    ));
    // …and re-saying what was already said is a no-op, not a re-pin.
    $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('bank'), TermType::Word, PartOfSpeech::Noun, TermSource::Ai,
        [new Translation(new LanguageCode('ru'), 'банк', true)],
    ));

    $primary = array_values(array_filter(
        $repo->findById($id)?->translations() ?? [],
        static fn (Translation $t): bool => $t->isPrimary,
    ));

    expect($repo->findById($id)?->translations())->toHaveCount(2)
        ->and($primary)->toHaveCount(1)
        ->and($primary[0]->text)->toBe('банк');
});

it('persists a normalized cefr level, and stores null for an invalid one', function () {
    $repo = new InMemoryTermRepository();
    $handler = makeHandler($repo);

    $good = $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('bank'), TermType::Word, PartOfSpeech::Noun, TermSource::Ai,
        cefr: 'b1',
    ));
    $bad = $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('overdraft'), TermType::Word, PartOfSpeech::Noun, TermSource::Ai,
        cefr: 'nonsense',
    ));

    expect($repo->findById($good)?->cefr())->toBe('B1')
        ->and($repo->findById($bad)?->cefr())->toBeNull();
});

it('back-fills a missing cefr on dedup but never overwrites an existing one', function () {
    $repo = new InMemoryTermRepository();
    $handler = makeHandler($repo);

    // First created without a level, second supplies one → back-filled.
    $filled = $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('withdraw'), TermType::Word, PartOfSpeech::Verb, TermSource::User,
    ));
    $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('withdraw'), TermType::Word, PartOfSpeech::Verb, TermSource::Ai,
        cefr: 'B2',
    ));

    // First created with a level, second differs → original kept.
    $kept = $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('deposit'), TermType::Word, PartOfSpeech::Noun, TermSource::Ai,
        cefr: 'A2',
    ));
    $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('deposit'), TermType::Word, PartOfSpeech::Noun, TermSource::Ai,
        cefr: 'C1',
    ));

    expect($repo->findById($filled)?->cefr())->toBe('B2')
        ->and($repo->findById($kept)?->cefr())->toBe('A2');
});

it('back-fills a missing image_api_prompt on dedup but never overwrites an existing one', function () {
    $repo = new InMemoryTermRepository();
    $handler = makeHandler($repo);

    // Created without a query, second supplies one → back-filled.
    $filled = $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('withdraw'), TermType::Word, PartOfSpeech::Verb, TermSource::Ai,
    ));
    $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('withdraw'), TermType::Word, PartOfSpeech::Verb, TermSource::Ai,
        imageApiPrompt: 'atm cash withdrawal',
    ));

    // Created with a query, second differs → original kept (a shared term is searched once).
    $kept = $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('deposit'), TermType::Word, PartOfSpeech::Noun, TermSource::Ai,
        imageApiPrompt: 'bank deposit slip',
    ));
    $handler(new FindOrCreateTerm(
        new LanguageCode('en'), new TermText('deposit'), TermType::Word, PartOfSpeech::Noun, TermSource::Ai,
        imageApiPrompt: 'something else',
    ));

    expect($repo->findById($filled)?->imageApiPrompt())->toBe('atm cash withdrawal')
        ->and($repo->findById($kept)?->imageApiPrompt())->toBe('bank deposit slip');
});

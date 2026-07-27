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

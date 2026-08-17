<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Domain\Entity\Term;
use App\Modules\Vocabulary\Domain\ValueObject\TermSource;
use App\Modules\Vocabulary\Domain\ValueObject\TermText;
use App\Modules\Vocabulary\Domain\ValueObject\TermType;

function bareTerm(): Term
{
    return Term::create(
        id: TermId::generate(),
        lang: new LanguageCode('en'),
        text: new TermText('withdraw cash'),
        normalizedText: 'withdraw cash',
        type: TermType::Phrase,
        pos: null,
        source: TermSource::Ai,
        createdAt: new DateTimeImmutable('2026-08-04T00:00:00Z'),
        imageApiPrompt: 'atm cash withdrawal',
    );
}

it('attaches an image with attribution when none is set', function () {
    $term = bareTerm();

    $term->attachImage('https://img/1.jpg', 'Jane', 'https://p/@jane');

    expect($term->imageUrl())->toBe('https://img/1.jpg')
        ->and($term->imageAuthor())->toBe('Jane')
        ->and($term->imageAuthorUrl())->toBe('https://p/@jane');
});

it('never overwrites an existing image', function () {
    $term = bareTerm();
    $term->attachImage('https://img/first.jpg', 'First', 'https://p/@first');

    $term->attachImage('https://img/second.jpg', 'Second', 'https://p/@second');

    expect($term->imageUrl())->toBe('https://img/first.jpg')
        ->and($term->imageAuthor())->toBe('First');
});

it('ignores a blank image url', function () {
    $term = bareTerm();

    $term->attachImage('   ', 'x', 'y');

    expect($term->imageUrl())->toBeNull();
});

it('back-fills an image_api_prompt only when absent', function () {
    $term = bareTerm();                 // already has a prompt
    $term->ensureImageApiPrompt('different');
    expect($term->imageApiPrompt())->toBe('atm cash withdrawal');

    $blank = Term::create(
        id: TermId::generate(), lang: new LanguageCode('en'), text: new TermText('the'),
        normalizedText: 'the', type: TermType::Word, pos: null, source: TermSource::Ai,
        createdAt: new DateTimeImmutable('2026-08-04T00:00:00Z'),
    );
    $blank->ensureImageApiPrompt('word the letter');
    expect($blank->imageApiPrompt())->toBe('word the letter');
});

<?php

declare(strict_types=1);

use App\Modules\Vocabulary\Domain\ValueObject\TermType;

it('parses all four term types', function () {
    expect(TermType::from('word'))->toBe(TermType::Word)
        ->and(TermType::from('phrase'))->toBe(TermType::Phrase)
        ->and(TermType::from('idiom'))->toBe(TermType::Idiom)
        ->and(TermType::from('phrasal_verb'))->toBe(TermType::PhrasalVerb);
});

it('treats everything but a single word as phrase-like', function () {
    expect(TermType::Word->isPhraseLike())->toBeFalse()
        ->and(TermType::Phrase->isPhraseLike())->toBeTrue()
        ->and(TermType::Idiom->isPhraseLike())->toBeTrue()
        ->and(TermType::PhrasalVerb->isPhraseLike())->toBeTrue();
});

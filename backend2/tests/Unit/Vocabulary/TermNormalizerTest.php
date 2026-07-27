<?php

declare(strict_types=1);

use App\Modules\Vocabulary\Domain\Service\TermNormalizer;
use App\Modules\Vocabulary\Domain\ValueObject\TermType;

beforeEach(function () {
    $this->normalizer = new TermNormalizer();
});

it('trims, collapses whitespace and casefolds', function () {
    expect($this->normalizer->normalize("  Boarding   Pass\t", TermType::Phrase))
        ->toBe('boarding pass');
});

it('strips a leading article for phrases', function () {
    expect($this->normalizer->normalize('The Bank', TermType::Phrase))->toBe('bank');
});

it('does not strip articles for single words', function () {
    expect($this->normalizer->normalize('The', TermType::Word))->toBe('the');
});

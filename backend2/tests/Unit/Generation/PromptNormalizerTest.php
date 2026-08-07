<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\PromptNormalizer;

beforeEach(function () {
    $this->normalize = new PromptNormalizer();
});

it('collapses case, whitespace and trailing punctuation to one key', function () {
    $n = $this->normalize;

    expect($n->normalize('Иду в банк!'))->toBe('иду в банк')
        ->and($n->normalize('иду  в   банк'))->toBe('иду в банк')
        ->and($n->normalize('  иду в банк…  '))->toBe('иду в банк');
});

it('strips trailing punctuation without shearing a multibyte letter (valid UTF-8)', function () {
    // "…" is E2 80 A6; a byte-wise rtrim mask containing it would strip the 0x80 byte off a
    // trailing Cyrillic "р" (D1 80) and leave invalid UTF-8. The result must stay well-formed.
    $result = $this->normalize->normalize('собеседование: опыт, навыки, оффер');

    expect($result)->toBe('собеседование: опыт, навыки, оффер')
        ->and(mb_check_encoding($result, 'UTF-8'))->toBeTrue();
});

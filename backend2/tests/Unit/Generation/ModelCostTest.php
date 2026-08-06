<?php

declare(strict_types=1);

use App\Modules\Generation\Domain\Service\ModelCost;

it('prices a known model from its token counts', function () {
    // gpt-4o-mini: $0.00015/1K in, $0.0006/1K out → 1000*0.00015/1000 + 2000*0.0006/1000.
    expect((new ModelCost())->estimate('gpt-4o-mini', 1000, 2000))->toBe('0.001350');
});

it('returns null for an unknown model or missing token counts', function () {
    $cost = new ModelCost();

    expect($cost->estimate('fake', 100, 200))->toBeNull()
        ->and($cost->estimate('gpt-4o', null, 200))->toBeNull()
        ->and($cost->estimate('gpt-4o', 100, null))->toBeNull();
});

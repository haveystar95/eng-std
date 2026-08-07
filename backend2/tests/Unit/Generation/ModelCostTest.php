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

it('estimates a realtime session from its duration plus transcript tokens', function () {
    // gpt-realtime-mini: $0.05/min audio + $0.0006/1K text-in + $0.0024/1K text-out.
    // 120s = 2 min → 0.10 audio; 1000 in → 0.0006; 2000 out → 0.0048 → 0.105400.
    expect((new ModelCost())->estimateRealtime('gpt-realtime-mini', 120, 1000, 2000))->toBe('0.105400');
});

it('returns null for an unknown realtime model', function () {
    expect((new ModelCost())->estimateRealtime('fake', 200, 100, 100))->toBeNull();
});

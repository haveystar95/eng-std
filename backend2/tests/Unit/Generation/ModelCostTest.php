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

it('estimates a realtime session from separate in/out audio seconds plus transcript text', function () {
    // 2.1-mini: audio $10/$20 per 1M; 10 in / 20 out audio tokens per sec.
    // input 120s → 1200 tok = 0.012; output 60s → 1200 tok = 0.024;
    // text 1000 in = 0.0006, 2000 out = 0.0048 → total 0.041400.
    expect((new ModelCost())->estimateRealtime('gpt-realtime-2.1-mini', 120, 60, 1000, 2000))->toBe('0.041400');
});

it('returns null for an unknown realtime model', function () {
    expect((new ModelCost())->estimateRealtime('fake', 200, 100, 100, 100))->toBeNull();
});

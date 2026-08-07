<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Service\PracticeCostEstimator;
use App\Modules\Generation\Domain\Service\ModelCost;
use App\Modules\Generation\Domain\ValueObject\TranscriptLine;
use App\Modules\Generation\Domain\ValueObject\TranscriptRole;

function estimator(): PracticeCostEstimator
{
    return new PracticeCostEstimator(new ModelCost());
}

function costLine(TranscriptRole $role, int $chars): TranscriptLine
{
    return new TranscriptLine($role, str_repeat('a', $chars), 0);
}

it('bills input audio for the whole session but output only for the agent speaking time', function () {
    $lesson = ['model' => 'gpt-realtime-2.1-mini'];
    // User said 400 chars → tokens_in 100. Agent said 1500 chars → ~100s of speech (1500/15).
    $lines = [
        costLine(TranscriptRole::User, 400),
        costLine(TranscriptRole::Assistant, 1500),
    ];

    // input 200s → 2000 tok = $0.02; output 100s → 2000 tok = $0.04;
    // text in 100 tok = $0.00006, out 375 tok = $0.0009 → $0.060960.
    $estimate = estimator()->estimate($lesson, $lines, 200);

    expect($estimate->costUsd)->toBe('0.060960')
        ->and($estimate->tokensIn)->toBe(100)
        ->and($estimate->tokensOut)->toBe(375);
});

it('caps the agent speaking time at the billed session length', function () {
    $lesson = ['model' => 'gpt-realtime-2.1-mini'];
    // 6000 chars ≈ 400s of speech, but the session only lasted 200s → output audio capped at 200s.
    $lines = [costLine(TranscriptRole::Assistant, 6000)];

    // input 200s → $0.02; output capped 200s → 4000 tok = $0.08; text out 1500 tok = $0.0036.
    expect(estimator()->estimate($lesson, $lines, 200)->costUsd)->toBe('0.103600');
});

it('bills only input audio when the agent never spoke', function () {
    $lesson = ['model' => 'gpt-realtime-2.1-mini'];
    $lines = [costLine(TranscriptRole::User, 300)];

    // output 0s → $0; input 200s → $0.02; text in 75 tok → $0.000045 → $0.020045.
    expect(estimator()->estimate($lesson, $lines, 200)->costUsd)->toBe('0.020045');
});

it('has no cost for an unknown model', function () {
    expect(estimator()->estimate(['model' => 'fake'], [costLine(TranscriptRole::Assistant, 100)], 60)->costUsd)
        ->toBeNull();
});

<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Service\PracticeCostEstimator;
use App\Modules\Shared\Domain\Service\ModelCost;
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

it('caps audio to the real transcript span — a long TTL / expired dialog does not balloon', function () {
    $lesson = ['model' => 'gemini-3.1-flash-live-preview'];
    // A ~60s conversation (timestamps 60s apart) but billableSeconds is a huge TTL (expired dialog).
    $lines = [
        new TranscriptLine(TranscriptRole::User, str_repeat('a', 300), 1_000_000_000_000),
        new TranscriptLine(TranscriptRole::Assistant, str_repeat('a', 900), 1_000_000_060_000),
    ];

    $huge = estimator()->estimate($lesson, $lines, 40_000)->costUsd; // TTL far beyond the real call
    $span = estimator()->estimate($lesson, $lines, 60)->costUsd;     // TTL == the real span

    // The TTL past the transcript span makes no difference, and the cost stays in cents — without
    // the cap, 40000s of input audio at 25 tok/s × $3/1M would bill ~$3.
    expect($huge)->toBe($span)
        ->and((float) $huge)->toBeLessThan(0.05);
});

it('has no cost for an unknown model', function () {
    expect(estimator()->estimate(['model' => 'fake'], [costLine(TranscriptRole::Assistant, 100)], 60)->costUsd)
        ->toBeNull();
});

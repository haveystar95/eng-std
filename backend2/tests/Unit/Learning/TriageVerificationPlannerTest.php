<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Service\TriageVerificationPlanner;
use App\Modules\Learning\Domain\ValueObject\CefrLevel;

beforeEach(fn () => $this->planner = new TriageVerificationPlanner());

it('flags a term above the user level as risky — an early check', function () {
    $plan = $this->planner->plan(CefrLevel::C1, CefrLevel::B1, null, false);

    expect($plan->risky)->toBeTrue()->and($plan->dueInDays)->toBe(7);
});

it('trusts an obvious term below the user level for a long time', function () {
    $plan = $this->planner->plan(CefrLevel::A2, CefrLevel::B1, null, false);

    expect($plan->risky)->toBeFalse()->and($plan->dueInDays)->toBe(90);
});

it('treats an unknown (null) cefr as neutral, not risky', function () {
    $plan = $this->planner->plan(null, CefrLevel::A1, null, false);

    expect($plan->risky)->toBeFalse()->and($plan->dueInDays)->toBe(90);
});

it('never uses latency for words — even an impossibly fast word swipe is not risky', function () {
    // Single words have no "didn't finish reading" state, and on-device the honest floor (~490 ms)
    // sits above any usable word threshold, so word risk is cefr-only. cefr below level → not risky.
    expect($this->planner->plan(CefrLevel::A1, CefrLevel::C2, 200, false)->risky)->toBeFalse()
        ->and($this->planner->plan(CefrLevel::A1, CefrLevel::C2, 400, false)->risky)->toBeFalse();
});

it('flags the same 400 ms on a phrase — not enough time to read it', function () {
    $plan = $this->planner->plan(CefrLevel::A1, CefrLevel::C2, 400, true);

    expect($plan->risky)->toBeTrue();
});

it('accepts an unhurried swipe on a phrase', function () {
    $plan = $this->planner->plan(CefrLevel::A1, CefrLevel::C2, 1000, true);

    expect($plan->risky)->toBeFalse();
});

it('treats a missing or zero latency as neutral, not impossibly fast', function () {
    // No client latency (null) or a 0/negative placeholder must not flag every "known" verdict.
    expect($this->planner->plan(CefrLevel::A1, CefrLevel::C2, null, false)->risky)->toBeFalse()
        ->and($this->planner->plan(CefrLevel::A1, CefrLevel::C2, 0, false)->risky)->toBeFalse()
        ->and($this->planner->plan(CefrLevel::A1, CefrLevel::C2, 0, true)->risky)->toBeFalse();
});

it('flags a revealed «known» as risky — the hint was needed, so check it soon', function () {
    // An otherwise-safe verdict (below level, unhurried) becomes risky once the card was flipped:
    // «known» reached by reading the translation/example is recognition of a hint, not recall.
    $plan = $this->planner->plan(CefrLevel::A1, CefrLevel::C2, 1000, true, true);

    expect($plan->risky)->toBeTrue()->and($plan->dueInDays)->toBe(7);
});

it('applies the revealed factor to words too, not just phrases', function () {
    // Unlike latency (phrases only), peeking is a valid tell for a single word as well.
    expect($this->planner->plan(CefrLevel::A1, CefrLevel::C2, null, false, true)->risky)->toBeTrue();
});

it('does not flag a not-revealed «known» on that account', function () {
    // false and null are both "no peek" → neutral; risk must come from level/latency instead.
    expect($this->planner->plan(CefrLevel::A1, CefrLevel::C2, 1000, true, false)->risky)->toBeFalse()
        ->and($this->planner->plan(CefrLevel::A1, CefrLevel::C2, 1000, true, null)->risky)->toBeFalse();
});

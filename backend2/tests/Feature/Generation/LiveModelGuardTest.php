<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\CollectionGeneratorPort;
use App\Modules\Generation\Application\Port\ContentModelCatalog;
use App\Modules\Generation\Application\Port\EnrichmentPackerPort;
use App\Modules\Generation\Application\Port\WordLookupPort;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Generation\Infrastructure\Adapter\FakeCollectionGenerator;
use App\Modules\Generation\Infrastructure\Adapter\LiveModelGuard;
use App\Modules\Generation\Infrastructure\Adapter\LiveModelRefusedInTests;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiCompatibleContentModel;
use App\Modules\Learning\Application\Port\PlanOutlinePort;

/**
 * THE GATE — no live vendor adapter is built under `APP_ENV=testing`.
 *
 * This file is the receipt for an incident, not a hypothetical. In PLAN-1b two test files bound
 * their fake under `Generation\Application\Port\PlanOutlinePort` — a class that does not exist,
 * because the port lives in `Learning`. The container took the string, the binding resolved nothing,
 * the real adapter stayed in place, and ≈39 `gpt-5.4` calls left the suite. The only symptom was a
 * seven-second test.
 *
 * Two things are asserted here and they are different: that a live adapter CANNOT be built by
 * accident, and that a fake bound under a name nobody resolves cannot pass for a fake that works.
 */

// ── the gate ──────────────────────────────────────────────────────────────────────────────────

it('refuses to build a live content model under APP_ENV=testing, whatever the driver says', function () {
    // Exactly the state the incident ran in: a driver pointing at a vendor and a key in place.
    config(['services.generation.driver' => 'openai', 'services.openai.api_key' => 'sk-live-looking']);

    expect(fn () => app(ContentModelCatalog::class)->get(ProviderId::OpenAi, 'gpt-5.4', 'plan'))
        ->toThrow(LiveModelRefusedInTests::class);
});

it('says the fix in the message, because the message is the whole point of a gate', function () {
    config(['services.generation.driver' => 'openai', 'services.openai.api_key' => 'sk-live-looking']);

    try {
        app(ContentModelCatalog::class)->get(ProviderId::OpenAi);
        $this->fail('The gate let a live content model through.');
    } catch (LiveModelRefusedInTests $e) {
        expect($e->getMessage())->toContain('use fake driver in tests')
            ->and($e->getMessage())->toContain('allowLiveAdapters()');
    }
});

it('closes every port that can reach a vendor, not just the content model', function () {
    config([
        'services.generation.driver' => 'openai',
        'services.openai.api_key' => 'sk-live-looking',
    ]);

    foreach ([EnrichmentPackerPort::class, WordLookupPort::class, CollectionGeneratorPort::class] as $port) {
        // The Feature suite binds fakes over some of these; forgetting the instance is what asks
        // the provider to build one, which is the moment the gate exists for.
        app()->forgetInstance($port);

        expect(fn () => app($port))->toThrow(LiveModelRefusedInTests::class);
    }
});

it('lets a test that is ABOUT the real adapter through, once it says so out loud', function () {
    config(['services.generation.driver' => 'openai', 'services.openai.api_key' => 'sk-live-looking']);
    allowLiveAdapters();

    expect(app(ContentModelCatalog::class)->get(ProviderId::OpenAi))
        ->toBeInstanceOf(OpenAiCompatibleContentModel::class);
});

it('is not in the way outside the test environment', function () {
    // The gate is about APP_ENV, not about the driver: production builds live adapters all day.
    app()->detectEnvironment(fn (): string => 'production');
    config(['services.generation.driver' => 'openai', 'services.openai.api_key' => 'sk-live-looking']);

    expect(app(ContentModelCatalog::class)->get(ProviderId::OpenAi))
        ->toBeInstanceOf(OpenAiCompatibleContentModel::class);

    app()->detectEnvironment(fn (): string => 'testing');
});

it('keeps the whole suite on the fake driver by default', function () {
    // `phpunit.xml` pins it. If this ever fails, every other guard in this file is load-bearing.
    expect(config('services.generation.driver'))->toBe('fake')
        ->and(config(LiveModelGuard::OPT_IN))->toBeFalse();
});

// ── the binding that resolved nothing ─────────────────────────────────────────────────────────

it('proves a fake is actually installed by RESOLVING it back, not by binding it', function () {
    // THE incident, reproduced. The port lives in Learning; this is the Generation-shaped name the
    // two test files reached for. The container accepts it as a key and stores the instance —
    // binding «succeeds» — but nothing in the app asks for that key, so the real adapter is what
    // gets used.
    $wrongKey = 'App\Modules\Generation\Application\Port\PlanOutlinePort';
    $fake = new FakeCollectionGenerator();
    app()->instance($wrongKey, $fake);

    // Binding under a name nobody resolves cannot be told from binding under the right one by
    // looking at the binding. It can be told by RESOLVING THE PORT and asking what came back —
    // which is the assertion every «I bound a fake» test owes.
    expect(class_exists($wrongKey))->toBeFalse()
        ->and(interface_exists($wrongKey))->toBeFalse()
        // The real port is untouched: whatever the wrong key holds, the app still resolves this one.
        ->and(app()->bound(PlanOutlinePort::class))->toBeTrue()
        ->and(app(PlanOutlinePort::class))->not->toBe($fake);
});

it('resolves the fake back when the port name is the real one', function () {
    $fake = new FakeCollectionGenerator();
    app()->instance(CollectionGeneratorPort::class, $fake);

    expect(app(CollectionGeneratorPort::class))->toBe($fake);
});

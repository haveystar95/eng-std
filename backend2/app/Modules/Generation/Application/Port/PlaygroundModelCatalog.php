<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\PlaygroundProvider;
use App\Modules\Generation\Domain\ValueObject\ProviderId;

/** Which providers and models the sandbox offers, and how to get one. Config-driven; no table. */
interface PlaygroundModelCatalog
{
    /**
     * Every provider the sandbox knows, available or not — an unavailable one is still listed, with
     * the env var that would fix it.
     *
     * @return list<PlaygroundProvider>
     */
    public function providers(): array;

    /**
     * A callable adapter, or null when the provider has no key OR the model is not one this registry
     * offers. The model check is deliberate: the picker is a closed list, and honouring an arbitrary
     * model name would let a typo bill a model nobody chose.
     */
    public function get(ProviderId $provider, string $model): ?PlaygroundModelPort;
}

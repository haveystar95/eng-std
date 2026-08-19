<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\ProviderAvailability;
use App\Modules\Generation\Domain\ValueObject\ProviderId;

/**
 * Which providers this deployment can actually reach, and how to get one.
 *
 * A missing key is a normal state, not a failure: the point of a bake-off is to compare whoever
 * answers, and a run that aborted because one vendor was unconfigured would produce nothing on the
 * night it mattered. So the catalogue reports availability with a reason, and the caller decides.
 */
interface ContentModelCatalog
{
    /**
     * Every known provider with its configured model and whether it can be called at all.
     *
     * Every provider appears, available or not — a report has to be able to say "Anthropic was not
     * run because no key is configured" rather than silently listing two columns where three were
     * expected.
     *
     * @return list<ProviderAvailability>
     */
    public function availability(): array;

    /** @return list<ContentModelPort> only the providers that can be called */
    public function available(): array;

    /** One provider by name, or null when it is not configured. */
    public function get(ProviderId $provider): ?ContentModelPort;
}

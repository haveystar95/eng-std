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

    /**
     * One provider by name, or null when it is not configured.
     *
     * @param  string|null  $model  run this provider on a DIFFERENT model than its configured one.
     *        Needed because the interesting comparison is often within one vendor — the same core
     *        enriched by a cheap model and an expensive one — and re-pointing the shared config to
     *        do that would move every other caller with it.
     * @param  string|null  $purpose  what the request log should say this spend was FOR. Null keeps
     *        the adapter's default (`generation`), which is what every caller but the learning plan
     *        means. It is a parameter and not a constant because one adapter now serves two
     *        products with two budgets, and a cost screen that could not tell them apart would be
     *        the same hole the `term_reading` whitelist migration was written to close.
     * @param  int|null  $timeoutSeconds  a per-call rope of this caller's own, instead of the shared
     *        `model_timeout`. The learning plan promises its client an answer within 90 seconds
     *        and must fail at ITS limit, not sit on a 180-second one that the comparison stack
     *        needs for a reasoning model. Null keeps the shared value.
     */
    public function get(ProviderId $provider, ?string $model = null, ?string $purpose = null, ?int $timeoutSeconds = null): ?ContentModelPort;
}

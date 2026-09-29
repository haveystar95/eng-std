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
     * @param  int|null  $retries  how many HTTP ATTEMPTS the call may make, the first one included. The
     *        plan's slot judge answers a learner who is waiting on a card (наряд SESSION-1a, D-28): one
     *        attempt within its 8 seconds, and past them the verdict is the code's — a retry would
     *        only double the wait for an answer nobody reads any more. Null keeps the adapter's own
     *        escalating retries, which every other caller wants.
     * @param  string|null  $journalPurpose  what the JOURNAL of model calls should say this call was for, when that is
     *        finer than the spend label. The learning plan's every call is `plan` money, but four different things
     *        (наряд BACK-TAILS-1 §3.3) — the plan, a lesson, a repair, a judge — and a journal that called them all
     *        `plan` could not say which of them a lost call had been. Null: the journal says what the log says.
     * @param  string|null  $reasoningEffort  the model's `reasoning_effort` for this call (наряд GEN-4: the learning plan sets
     *        one per purpose). Only the OpenAI-compatible wire has the field; null sends nothing — the model's own default.
     */
    public function get(ProviderId $provider, ?string $model = null, ?string $purpose = null, ?int $timeoutSeconds = null, ?int $retries = null, ?string $journalPurpose = null, ?string $reasoningEffort = null): ?ContentModelPort;
}

<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Domain\ValueObject\ProviderId;

/**
 * One vendor, asked for one structured JSON answer. The narrowest seam that still hides every
 * difference between vendors: how they authenticate, what their endpoint is called, and the three
 * mutually incompatible ways they spell "return JSON matching this schema".
 *
 * It is deliberately smaller than {@see CollectionGeneratorPort}. That port speaks the language of
 * collections — a brief in, a draft out — and there is exactly one product it can express. This one
 * speaks the language of a model call, so the same three adapters serve a term list, an enrichment
 * of existing terms, and a one-shot full collection without a new adapter per product.
 *
 * Anything that fails — transport, auth, a refusal, a reply that is not the requested shape — throws.
 * Deciding whether one dead provider ends a run is the caller's business, not the adapter's.
 */
interface ContentModelPort
{
    /** Which vendor this adapter talks to — so a result can say who produced it. */
    public function provider(): ProviderId;

    /** The model it will ask for, as configured. What actually answered is on the {@see ModelAnswer}. */
    public function model(): string;

    /**
     * Ask for one JSON object matching `$schema`.
     *
     * @param  RenderedPrompt  $prompt  the system side: rules, already rendered and digested
     * @param  string  $userMessage  the data side — a topic, a term list — always delimited by the
     *                               caller and never phrased as an instruction
     * @param  array<string, mixed>  $schema  JSON Schema for the whole answer object
     */
    public function complete(RenderedPrompt $prompt, string $userMessage, array $schema): ModelAnswer;
}

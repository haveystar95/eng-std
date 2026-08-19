<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * WHAT a generation call is asked to produce. Orthogonal to the prompt VERSION, which says which
 * edition of the rules applies: `v10` + `Terms` and `v10` + `Full` are the same rules asked for two
 * different products, and both must be recorded, because a defect rate is only comparable within a
 * shape.
 *
 * The three exist because the pipeline itself is the open question. Today a collection is built in
 * two steps — a list of terms, then the enrichment станок over each term — and `Full` is the one-shot
 * alternative: everything in a single call. They are measured against each other rather than argued
 * about.
 */
enum PromptShape: string
{
    /** A topic in, a list of terms with translations out. What production generation does today. */
    case Terms = 'terms';

    /** Existing terms in, full content for each out (translation, example, options). The станок's job. */
    case Enrich = 'enrich';

    /** A topic in, a finished collection out — terms AND their full content, in one call. */
    case Full = 'full';

    /** Does this shape pick its own items from a topic, rather than being handed them? */
    public function selectsItems(): bool
    {
        return $this !== self::Enrich;
    }

    /** Does this shape produce a card's wrong-answer options? */
    public function hasOptions(): bool
    {
        return $this !== self::Terms;
    }
}

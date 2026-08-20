<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\ValueObject;

/**
 * The three pipelines being compared, which are NOT three variants of one thing.
 *
 * A and B are the two halves of the pipeline as it works today: a collection is generated as a list
 * of terms, and the enrichment станок then fills each term in. They are measured separately because
 * they can have different winners — a provider that picks the best word list is not automatically
 * the one that writes the best key for a term it was handed — and a single "best model" verdict
 * would force the worse choice on one of the two halves.
 *
 * C is the experiment: the same collection produced in ONE call, content and all. It does not
 * replace A or B, and a good C result is an argument about the shape of the pipeline, not a
 * conclusion — the numbers go to a person in the morning.
 */
enum BakeoffTrack: string
{
    /** Topic in, term list out — what production generation does today. */
    case Collections = 'a';

    /** Existing terms in, full content out — what the enrichment станок does today. */
    case Enrichment = 'b';

    /** Topic in, finished collection out, one call. The experiment. */
    case OneShot = 'c';

    /**
     * A FINISHED core in, exercise machinery out. The second half of the "core + mechanics" split,
     * and it exists to be measured against Enrichment — which produces the same machinery by
     * regenerating the core it was handed.
     */
    case Mechanics = 'm';

    public function shape(): PromptShape
    {
        return match ($this) {
            self::Collections => PromptShape::Terms,
            self::Enrichment => PromptShape::Enrich,
            self::OneShot => PromptShape::Full,
            self::Mechanics => PromptShape::Mechanics,
        };
    }

    public function title(): string
    {
        return match ($this) {
            self::Collections => 'Трек А — генерация коллекций',
            self::Enrichment => 'Трек Б — обогащение существующих терминов',
            self::OneShot => 'Трек В — one-shot (эксперимент)',
            self::Mechanics => 'Трек М — механика поверх готового ядра',
        };
    }
}

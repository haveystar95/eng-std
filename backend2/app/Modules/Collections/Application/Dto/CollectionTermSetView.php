<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

/**
 * A collection reduced to what another module needs to clone its term set: the title, description
 * and the ordered term ids. Used by Generation's prompt cache — reuse the terms, build a fresh
 * personal collection. Not owner-scoped: the caller has already decided reuse is allowed.
 */
final readonly class CollectionTermSetView
{
    /** @param list<string> $termIds */
    public function __construct(
        public string $title,
        public ?string $description,
        public array $termIds,
        public ?string $imageApiPrompt = null,   // carried so a cache-hit collection can re-derive its cover
        // The language this deck teaches FROM — which of a term's translations the станок shows the
        // model and the export shows a proofreader. Carried here because every caller that needs it
        // is already reading the collection through this view.
        public string $sourceLang = 'ru',
    ) {}
}

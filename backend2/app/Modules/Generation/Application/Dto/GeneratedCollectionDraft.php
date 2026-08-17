<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Dto;

/** What the generator returns: the proposed collection plus provider usage for cost tracking. */
final readonly class GeneratedCollectionDraft
{
    /** @param list<GeneratedItem> $items */
    public function __construct(
        public string $title,
        public ?string $description,
        public array $items,
        public string $model,
        public ?int $tokensIn,
        public ?int $tokensOut,
        public ?string $rawResponse = null,     // truncated model output, kept for failure diagnosis
        public ?string $imageApiPrompt = null,  // model's cover-image search query for the collection (v4+)
    ) {}
}

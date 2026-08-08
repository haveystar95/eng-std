<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Dto;

/**
 * A store collection's preview: the first few terms plus the full item count, so the client can
 * show a taster ("N слов") before subscribing. Available to everyone — the premium gate is only
 * on adding, not on looking.
 */
final readonly class StoreCollectionPreview
{
    /** @param list<PreviewTerm> $terms */
    public function __construct(
        public string $collectionId,
        public int $total,
        public array $terms,
    ) {}
}

<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Query;

use App\Modules\Collections\Application\Dto\CollectionItemView;
use App\Modules\Collections\Application\Dto\CollectionView;
use App\Modules\Collections\Domain\Entity\Collection;
use App\Modules\Collections\Domain\Repository\CollectionRepository;
use App\Modules\Vocabulary\Application\Query\TermContentReader;

final readonly class GetCollectionHandler
{
    public function __construct(
        private CollectionRepository $collections,
        private TermContentReader $termContent,
    ) {}

    public function __invoke(GetCollection $query): ?CollectionView
    {
        $collection = $this->collections->findById($query->collectionId);

        // Owner-only for now; a non-owner (or missing id) gets 404, not existence disclosure.
        if ($collection === null
            || $collection->ownerId() === null
            || ! $collection->ownerId()->equals($query->actorId)
        ) {
            return null;
        }

        return $this->toView($collection);
    }

    private function toView(Collection $collection): CollectionView
    {
        $termIds = array_map(static fn ($item) => $item->termId, $collection->items());
        $content = $this->termContent->byIds($termIds);

        $items = [];
        foreach ($collection->items() as $item) {
            $view = $content[$item->termId->value] ?? null;
            $items[] = new CollectionItemView(
                termId: $item->termId->value,
                position: $item->position,
                note: $item->note,
                text: $view?->text,
                type: $view?->type,
                transcription: $view?->transcription,
                translation: $view?->translation,
                example: $view?->example,
                exampleTranslation: $view?->exampleTranslation,
            );
        }

        return new CollectionView(
            id: $collection->id()->value,
            type: $collection->type()->value,
            source: $collection->source()->value,
            title: $collection->title(),
            description: $collection->description(),
            sourceLang: $collection->sourceLang()->value,
            targetLang: $collection->targetLang()->value,
            visibility: $collection->visibility()->value,
            itemsCount: $collection->itemsCount(),
            createdAt: $collection->createdAt(),
            items: $items,
        );
    }
}

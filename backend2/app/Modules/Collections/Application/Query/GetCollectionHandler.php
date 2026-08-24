<?php

declare(strict_types=1);

namespace App\Modules\Collections\Application\Query;

use App\Modules\Collections\Application\Dto\CollectionItemView;
use App\Modules\Collections\Application\Dto\CollectionView;
use App\Modules\Collections\Application\Port\CollectionSubscriptions;
use App\Modules\Collections\Domain\Entity\Collection;
use App\Modules\Collections\Domain\Repository\CollectionRepository;
use App\Modules\Vocabulary\Application\Dto\SupportLanguages;
use App\Modules\Vocabulary\Application\Query\TermContentReader;

final readonly class GetCollectionHandler
{
    public function __construct(
        private CollectionRepository $collections,
        private TermContentReader $termContent,
        private CollectionSubscriptions $subscriptions,
    ) {}

    public function __invoke(GetCollection $query): ?CollectionView
    {
        $collection = $this->collections->findById($query->collectionId);
        if ($collection === null) {
            return null;
        }

        // READ access = owner ∪ active subscriber (a store deck added to the user's library).
        // Editing stays owner-only (Collection::assertEditableBy) — a subscriber can't mutate a
        // store deck. A non-owner without an active subscription gets 404, not existence disclosure.
        $isOwner = $collection->ownerId() !== null && $collection->ownerId()->equals($query->actorId);
        if (! $isOwner && ! $this->subscriptions->isActive($query->actorId, $query->collectionId)) {
            return null;
        }

        return $this->toView($collection);
    }

    private function toView(Collection $collection): CollectionView
    {
        $termIds = array_map(static fn ($item) => $item->termId, $collection->items());
        // The COLLECTION's language, not the reader's profile. It used to be the reader's, on the
        // reasoning that «the question on the card belongs to the person answering it» — which was
        // sound while a deck's pair was a suggestion. It is not: the pair is a property of the
        // collection (DECISIONS п. 81), a subscriber to an `en→uk` deck subscribed to a Ukrainian
        // deck, and reading it in their profile language is how the same word answered differently
        // on the shelf and in the session.
        $content = $this->termContent->byIds(
            $termIds,
            SupportLanguages::uniform($collection->sourceLang()->value),
        );

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
            isDefault: $collection->isDefault(),
        );
    }
}

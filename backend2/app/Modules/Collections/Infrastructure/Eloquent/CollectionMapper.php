<?php

declare(strict_types=1);

namespace App\Modules\Collections\Infrastructure\Eloquent;

use App\Modules\Collections\Domain\Entity\Collection;
use App\Modules\Collections\Domain\Entity\CollectionItem;
use App\Modules\Collections\Domain\ValueObject\CollectionSource;
use App\Modules\Collections\Domain\ValueObject\CollectionType;
use App\Modules\Collections\Domain\ValueObject\Visibility;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class CollectionMapper
{
    public function toDomain(CollectionModel $model): Collection
    {
        $items = [];
        foreach ($model->items as $row) {
            $items[] = new CollectionItem(TermId::fromString($row->term_id), $row->position, $row->note);
        }

        return Collection::reconstitute(
            id: CollectionId::fromString($model->id),
            ownerId: $model->owner_id !== null ? UserId::fromString($model->owner_id) : null,
            type: CollectionType::from($model->type),
            title: $model->title,
            description: $model->description,
            topic: $model->topic,
            sourceLang: new LanguageCode($model->source_lang),
            targetLang: new LanguageCode($model->target_lang),
            visibility: Visibility::from($model->visibility),
            source: CollectionSource::from($model->source),
            createdAt: $model->created_at->toDateTimeImmutable(),
            items: $items,
            imageUrl: $model->image_url,
            imageApiPrompt: $model->image_api_prompt,
            imageAuthor: $model->image_author,
            imageAuthorUrl: $model->image_author_url,
            isPremium: (bool) $model->is_premium,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(Collection $collection): array
    {
        return [
            'owner_id' => $collection->ownerId()?->value,
            'type' => $collection->type()->value,
            'title' => $collection->title(),
            'description' => $collection->description(),
            'topic' => $collection->topic(),
            'source_lang' => $collection->sourceLang()->value,
            'target_lang' => $collection->targetLang()->value,
            'visibility' => $collection->visibility()->value,
            'source' => $collection->source()->value,
            'items_count' => $collection->itemsCount(),
            'image_url' => $collection->imageUrl(),
            'image_api_prompt' => $collection->imageApiPrompt(),
            'image_author' => $collection->imageAuthor(),
            'image_author_url' => $collection->imageAuthorUrl(),
            'is_premium' => $collection->isPremium(),
            'created_at' => $collection->createdAt(),
        ];
    }
}

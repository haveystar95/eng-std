<?php

declare(strict_types=1);

namespace App\Modules\Collections\Domain\Entity;

use App\Modules\Collections\Domain\Exception\NotCollectionOwner;
use App\Modules\Collections\Domain\ValueObject\CollectionSource;
use App\Modules\Collections\Domain\ValueObject\CollectionType;
use App\Modules\Collections\Domain\ValueObject\Visibility;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use InvalidArgumentException;

/** Aggregate root: a collection and its ordered items (term references). */
final class Collection
{
    /** @var list<CollectionItem> */
    private array $items;

    /** @param list<CollectionItem> $items */
    private function __construct(
        private readonly CollectionId $id,
        private readonly ?UserId $ownerId,
        private readonly CollectionType $type,
        private string $title,
        private ?string $description,
        private ?string $topic,
        private readonly LanguageCode $sourceLang,
        private readonly LanguageCode $targetLang,
        private readonly Visibility $visibility,
        private readonly CollectionSource $source,
        private readonly DateTimeImmutable $createdAt,
        array $items,
    ) {
        $this->items = $items;
    }

    public static function createCustom(
        CollectionId $id,
        UserId $ownerId,
        string $title,
        LanguageCode $sourceLang,
        LanguageCode $targetLang,
        DateTimeImmutable $createdAt,
        ?string $description = null,
        ?string $topic = null,
    ): self {
        return new self(
            $id, $ownerId, CollectionType::Custom, self::cleanTitle($title), $description, $topic,
            $sourceLang, $targetLang, Visibility::Private, CollectionSource::User, $createdAt, [],
        );
    }

    /** A personal collection produced by AI generation: still custom + owned, but source=ai. */
    public static function createGenerated(
        CollectionId $id,
        UserId $ownerId,
        string $title,
        LanguageCode $sourceLang,
        LanguageCode $targetLang,
        DateTimeImmutable $createdAt,
        ?string $description = null,
        ?string $topic = null,
    ): self {
        return new self(
            $id, $ownerId, CollectionType::Custom, self::cleanTitle($title), $description, $topic,
            $sourceLang, $targetLang, Visibility::Private, CollectionSource::Ai, $createdAt, [],
        );
    }

    /**
     * Rebuild an existing collection from persistence.
     *
     * @param list<CollectionItem> $items
     */
    public static function reconstitute(
        CollectionId $id,
        ?UserId $ownerId,
        CollectionType $type,
        string $title,
        ?string $description,
        ?string $topic,
        LanguageCode $sourceLang,
        LanguageCode $targetLang,
        Visibility $visibility,
        CollectionSource $source,
        DateTimeImmutable $createdAt,
        array $items,
    ): self {
        return new self(
            $id, $ownerId, $type, $title, $description, $topic,
            $sourceLang, $targetLang, $visibility, $source, $createdAt, $items,
        );
    }

    public function assertEditableBy(UserId $actor): void
    {
        if ($this->type !== CollectionType::Custom || $this->ownerId === null || ! $this->ownerId->equals($actor)) {
            throw NotCollectionOwner::make();
        }
    }

    /** Idempotent: adding a term already present is a no-op (offline-retry-friendly). */
    public function addTerm(TermId $termId, ?string $note = null): void
    {
        foreach ($this->items as $item) {
            if ($item->termId->equals($termId)) {
                return;
            }
        }
        $this->items[] = new CollectionItem($termId, $this->nextPosition(), $note);
    }

    public function removeTerm(TermId $termId): void
    {
        $this->items = array_values(
            array_filter($this->items, static fn (CollectionItem $i): bool => ! $i->termId->equals($termId)),
        );
    }

    public function rename(string $title): void
    {
        $this->title = self::cleanTitle($title);
    }

    public function id(): CollectionId
    {
        return $this->id;
    }

    public function ownerId(): ?UserId
    {
        return $this->ownerId;
    }

    public function type(): CollectionType
    {
        return $this->type;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function topic(): ?string
    {
        return $this->topic;
    }

    public function sourceLang(): LanguageCode
    {
        return $this->sourceLang;
    }

    public function targetLang(): LanguageCode
    {
        return $this->targetLang;
    }

    public function visibility(): Visibility
    {
        return $this->visibility;
    }

    public function source(): CollectionSource
    {
        return $this->source;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return list<CollectionItem> */
    public function items(): array
    {
        return $this->items;
    }

    public function itemsCount(): int
    {
        return count($this->items);
    }

    private function nextPosition(): int
    {
        $max = 0;
        foreach ($this->items as $item) {
            $max = max($max, $item->position);
        }

        return $max + 1;
    }

    private static function cleanTitle(string $title): string
    {
        $clean = trim($title);
        if ($clean === '') {
            throw new InvalidArgumentException('Collection title cannot be empty.');
        }

        return $clean;
    }
}

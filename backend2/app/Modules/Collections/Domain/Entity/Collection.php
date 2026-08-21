<?php

declare(strict_types=1);

namespace App\Modules\Collections\Domain\Entity;

use App\Modules\Collections\Domain\Exception\DefaultCollectionNotDeletable;
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

    private ?string $imageUrl;

    private ?string $imageApiPrompt;

    private ?string $imageAuthor;

    private ?string $imageAuthorUrl;

    /** @param list<CollectionItem> $items */
    private function __construct(
        private readonly CollectionId $id,
        private ?UserId $ownerId,
        private CollectionType $type,
        private string $title,
        private ?string $description,
        private ?string $topic,
        private readonly LanguageCode $sourceLang,
        private readonly LanguageCode $targetLang,
        private Visibility $visibility,
        private CollectionSource $source,
        private readonly DateTimeImmutable $createdAt,
        array $items,
        ?string $imageUrl = null,
        ?string $imageApiPrompt = null,
        ?string $imageAuthor = null,
        ?string $imageAuthorUrl = null,
        private bool $isPremium = false,
        /**
         * «Сохранённые» — the one folder of this owner's that an unaddressed save lands in.
         *
         * Everything else about it is an ordinary custom collection: it is renameable, it is
         * practised over, its words are pool words. The flag buys exactly two behaviours, and both
         * are stated here rather than inferred from the title, which the owner may change:
         * {@see assertDeletableBy()} refuses to remove it, and the search's «+ Сохранённые» finds
         * it by this flag.
         */
        private readonly bool $isDefault = false,
    ) {
        $this->items = $items;
        $this->imageUrl = self::clean($imageUrl);
        $this->imageApiPrompt = self::clean($imageApiPrompt);
        $this->imageAuthor = self::clean($imageAuthor);
        $this->imageAuthorUrl = self::clean($imageAuthorUrl);
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

    /**
     * The learner's «Сохранённые» folder — a custom collection carrying the default flag.
     *
     * Created LAZILY, on the first save that names no folder, so a learner who never uses search
     * never gets an empty shelf item. There is exactly one per owner, and the database says so
     * (a partial unique index), not just this factory.
     */
    public static function createDefault(
        CollectionId $id,
        UserId $ownerId,
        string $title,
        LanguageCode $sourceLang,
        LanguageCode $targetLang,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            $id, $ownerId, CollectionType::Custom, self::cleanTitle($title), null, null,
            $sourceLang, $targetLang, Visibility::Private, CollectionSource::User, $createdAt, [],
            isDefault: true,
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
        ?string $imageApiPrompt = null,
    ): self {
        return new self(
            $id, $ownerId, CollectionType::Custom, self::cleanTitle($title), $description, $topic,
            $sourceLang, $targetLang, Visibility::Private, CollectionSource::Ai, $createdAt, [],
            imageApiPrompt: $imageApiPrompt,
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
        ?string $imageUrl = null,
        ?string $imageApiPrompt = null,
        ?string $imageAuthor = null,
        ?string $imageAuthorUrl = null,
        bool $isPremium = false,
        bool $isDefault = false,
    ): self {
        return new self(
            $id, $ownerId, $type, $title, $description, $topic,
            $sourceLang, $targetLang, $visibility, $source, $createdAt, $items,
            $imageUrl, $imageApiPrompt, $imageAuthor, $imageAuthorUrl, $isPremium, $isDefault,
        );
    }

    public function assertEditableBy(UserId $actor): void
    {
        if ($this->type !== CollectionType::Custom || $this->ownerId === null || ! $this->ownerId->equals($actor)) {
            throw NotCollectionOwner::make();
        }
    }

    /**
     * Deleting is editing PLUS one thing: «Сохранённые» stays.
     *
     * Not because the rows are precious — deleting a folder never touched the pool or the review
     * log, and still does not — but because it is the destination the app promises when it says
     * «сохранено в Сохранённые». A one-tap save with nowhere to land is a broken button, and
     * re-creating the folder silently on the next save would mean the rename the owner made is
     * quietly gone. Renaming it is allowed and does not move the flag: the flag is the destination,
     * the title is the label.
     */
    public function assertDeletableBy(UserId $actor): void
    {
        $this->assertEditableBy($actor);

        if ($this->isDefault) {
            throw DefaultCollectionNotDeletable::make();
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

    /** Partial update: only non-null fields change. An empty-string description clears it. */
    public function updateDetails(?string $title, ?string $description): void
    {
        if ($title !== null) {
            $this->title = self::cleanTitle($title);
        }
        if ($description !== null) {
            $this->description = $description === '' ? null : $description;
        }
    }

    /**
     * Attach a found cover photo. Never overwrites an existing one (idempotent re-runs of the
     * attach job); a blank url is ignored. Attribution rides along.
     */
    public function attachImage(?string $url, ?string $author, ?string $authorUrl): void
    {
        if ($this->imageUrl !== null) {
            return;
        }
        $cleanUrl = self::clean($url);
        if ($cleanUrl === null) {
            return;
        }
        $this->imageUrl = $cleanUrl;
        $this->imageAuthor = self::clean($author);
        $this->imageAuthorUrl = self::clean($authorUrl);
    }

    /**
     * Promote this collection to curated store content: ownerless, system-typed, public, with a
     * curated source. `$isPremium` puts it behind the subscription gate. Idempotent — re-publishing
     * an already-published collection only re-affirms the store fields / flips the premium flag.
     * Items, title, topic, language pair and cover image are preserved. Used by `store:publish`.
     */
    public function publishToStore(bool $isPremium): void
    {
        $this->ownerId = null;
        $this->type = CollectionType::System;
        $this->visibility = Visibility::Public;
        $this->source = CollectionSource::Curated;
        $this->isPremium = $isPremium;
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

    /** Store content gated behind a subscription. App-created collections are always false. */
    public function isPremium(): bool
    {
        return $this->isPremium;
    }

    /** Is this the owner's «Сохранённые» folder — the destination of an unaddressed save? */
    public function isDefault(): bool
    {
        return $this->isDefault;
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

    public function imageUrl(): ?string
    {
        return $this->imageUrl;
    }

    /** The model's cover-image search query (server-internal), or null. */
    public function imageApiPrompt(): ?string
    {
        return $this->imageApiPrompt;
    }

    public function imageAuthor(): ?string
    {
        return $this->imageAuthor;
    }

    public function imageAuthorUrl(): ?string
    {
        return $this->imageAuthorUrl;
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

    /** Trim to null: empty/whitespace-only strings become null. */
    private static function clean(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}

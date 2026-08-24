<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

use App\Modules\Shared\Domain\Service\LanguageRoles;
use DateTimeImmutable;

/**
 * A collection change for the sync feed, in Learning's own terms so the Presentation layer never
 * reaches into Collections' Application DTOs (deptrac: LearningPresentation → CollectionsApplication
 * is not allowed). Mapped from Collections' CollectionSyncRow in the handler.
 */
final readonly class CollectionChange
{
    public function __construct(
        public string $id,
        public bool $deleted,
        public DateTimeImmutable $updatedAt,
        public ?string $title,
        public ?string $description,
        public ?string $topic,
        public ?string $sourceLang,
        public ?string $targetLang,
        public int $itemsCount,
        public ?string $source,
        public ?string $type,
        public ?string $imageUrl = null,
        public ?string $imageAuthor = null,
        public ?string $imageAuthorUrl = null,
        public bool $isDefault = false,
    ) {}

    /**
     * A REFERENCE collection: a phrasebook, not a course.
     *
     * DERIVED from the studied language and never stored (DECISIONS п. 136) — zh and ja carry no
     * trainer, so a collection teaching one of them has a term, a translation and an audio and
     * nothing else: no triage, no pool, no schedule, no daily goal (п. 84). It rides the sync feed
     * rather than being worked out on the device because «which languages this deployment can
     * teach» is a server capability that moves without a client release; a phone holding its own
     * copy of that list would hide the training buttons on a language that had just gained a
     * trainer, or offer them on one that never had.
     */
    public function isReference(): bool
    {
        return $this->targetLang !== null && LanguageRoles::isReference($this->targetLang);
    }
}

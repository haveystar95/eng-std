<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

/**
 * «+ Сохранённые» — the one tap that turns a search result into a word the learner is studying.
 *
 * Exactly one of `lookupId` / `termId` is set. The first saves a freshly looked-up word (the cached
 * answer becomes a term); the second saves a word the database already had. They are separate
 * fields rather than one polymorphic id because the two do genuinely different things, and a
 * handler that had to guess which kind of id it was holding would guess wrong on the day a lookup
 * id and a term id were both valid ULIDs — which is every day.
 *
 * `collectionId` null means «Сохранённые», created on the spot if this is the first save.
 */
final readonly class AddSearchResult
{
    public function __construct(
        public UserId $actorId,
        public ?string $lookupId = null,
        public ?TermId $termId = null,
        public ?CollectionId $collectionId = null,
    ) {}
}

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
 *
 * `fixedTranslation` is the line the learner was reading in the translator when they pressed
 * «Собрать карточку». It is sent again HERE, and not only on the lookup, because the lookup may
 * legitimately have been a free cache hit — a card somebody else's call paid for, worded their way —
 * and the confirmation still has to reach the term the learner is about to study. See the handler.
 *
 * `enroll` is the SECOND half of the save, and the caller now states it. The shelf and the queue are
 * different things (полка ≠ очередь): saving a word files it, and only a deliberate act puts it in
 * the trainer's queue. The translator offers both as two named buttons — «Сохранить» (shelf, then
 * the swipe pass sorts it) and «Учить сразу» (shelf AND queue) — so the choice belongs to the
 * learner rather than to this handler.
 *
 * It defaults to TRUE, which is this door's old behaviour to the letter. That default is for the
 * app already on somebody's phone, which sends no such field and must go on working exactly as it
 * did; the current client always says which one it means.
 */
final readonly class AddSearchResult
{
    public function __construct(
        public UserId $actorId,
        public ?string $lookupId = null,
        public ?TermId $termId = null,
        public ?CollectionId $collectionId = null,
        public ?string $fixedTranslation = null,
        public bool $enroll = true,
    ) {}
}

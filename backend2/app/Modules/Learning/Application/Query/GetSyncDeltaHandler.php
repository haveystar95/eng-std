<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Collections\Application\Dto\CollectionItemSyncRow;
use App\Modules\Collections\Application\Dto\CollectionSyncRow;
use App\Modules\Collections\Application\Port\CollectionSyncReader;
use App\Modules\Learning\Application\Dto\CollectionChange;
use App\Modules\Learning\Application\Dto\CollectionItemChange;
use App\Modules\Learning\Application\Dto\ProgressSyncRow;
use App\Modules\Learning\Application\Dto\SyncCursor;
use App\Modules\Learning\Application\Dto\SyncDeltaView;
use App\Modules\Learning\Application\Dto\TermSyncView;
use App\Modules\Learning\Application\Port\ProgressSyncReader;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Dto\TermChangeRef;
use App\Modules\Vocabulary\Application\Query\TermChangeReader;
use App\Modules\Vocabulary\Application\Query\TermContentReader;

/**
 * Assembles one page of a delta sync across Collections, Vocabulary and Learning. Everything is
 * bounded by (since, upper], where `upper` is frozen on the first page (in the cursor) so the
 * change-set doesn't shift under pagination. The four ordered streams are concatenated in a fixed
 * order and offset-sliced — deterministic paging without a per-type cursor. Only the terms on the
 * current page are hydrated. `since` null = full snapshot (readers return upserts only).
 *
 * Note: it materialises the full change-set per request before slicing — fine for this app's
 * per-user volume; revisit if a user's change-set ever grows large.
 */
final readonly class GetSyncDeltaHandler
{
    public function __construct(
        private CollectionSyncReader $collectionSync,
        private TermChangeReader $termChanges,
        private TermContentReader $termContent,
        private ProgressSyncReader $progressSync,
        private Clock $clock,
    ) {}

    public function __invoke(GetSyncDelta $query): SyncDeltaView
    {
        $upper = $query->cursor !== null ? $query->cursor->upper : $this->clock->now();
        $since = $query->since;

        $collections = $this->collectionSync->changedCollections($query->userId, $since, $upper);
        $items = $this->collectionSync->changedItems($query->userId, $since, $upper);
        $termRefs = $this->termChanges->changedTermIds($this->collectionSync->liveTermIds($query->userId), $since, $upper);
        $progress = $this->progressSync->changedProgress($query->userId, $since, $upper);

        // Fixed order → deterministic offset paging across a heterogeneous stream.
        $all = [...$collections, ...$items, ...$termRefs, ...$progress];
        $offset = $query->cursor !== null ? $query->cursor->offset : 0;
        $limit = max(1, $query->limit);
        $page = array_slice($all, $offset, $limit);
        $hasMore = ($offset + $limit) < count($all);
        $nextCursor = $hasMore ? (new SyncCursor($upper, $offset + $limit))->encode() : null;

        // Partition the page back by type, mapping Collections' DTOs into Learning-owned ones so
        // the Presentation layer never reaches into another module's Application.
        /** @var list<CollectionChange> $pCollections */
        $pCollections = [];
        /** @var list<CollectionItemChange> $pItems */
        $pItems = [];
        /** @var list<TermChangeRef> $pTermRefs */
        $pTermRefs = [];
        /** @var list<ProgressSyncRow> $pProgress */
        $pProgress = [];
        foreach ($page as $row) {
            if ($row instanceof CollectionSyncRow) {
                $pCollections[] = new CollectionChange(
                    $row->id, $row->deleted, $row->updatedAt, $row->title, $row->description,
                    $row->topic, $row->sourceLang, $row->targetLang, $row->itemsCount,
                    $row->source, $row->type,
                    $row->imageUrl, $row->imageAuthor, $row->imageAuthorUrl,
                );
            } elseif ($row instanceof CollectionItemSyncRow) {
                $pItems[] = new CollectionItemChange(
                    $row->collectionId, $row->termId, $row->deleted, $row->updatedAt, $row->position, $row->note,
                );
            } elseif ($row instanceof TermChangeRef) {
                $pTermRefs[] = $row;
            } elseif ($row instanceof ProgressSyncRow) {
                $pProgress[] = $row;
            }
        }

        $content = $this->termContent->byIds(array_map(
            static fn (TermChangeRef $r): TermId => TermId::fromString($r->id),
            $pTermRefs,
        ));
        $terms = array_map(
            static fn (TermChangeRef $r): TermSyncView => new TermSyncView($r->id, $r->updatedAt, $content[$r->id] ?? null),
            $pTermRefs,
        );

        return new SyncDeltaView($upper, $nextCursor, $hasMore, $pCollections, $pItems, $terms, $pProgress);
    }
}

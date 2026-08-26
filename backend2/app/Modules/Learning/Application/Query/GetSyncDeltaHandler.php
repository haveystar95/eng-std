<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Collections\Application\Dto\CollectionItemSyncRow;
use App\Modules\Collections\Application\Dto\CollectionSyncRow;
use App\Modules\Collections\Application\Dto\SubscribedTermRef;
use App\Modules\Collections\Application\Port\CollectionSyncReader;
use App\Modules\Learning\Application\Dto\CollectionChange;
use App\Modules\Learning\Application\Dto\CollectionItemChange;
use App\Modules\Learning\Application\Dto\PooledTermRef;
use App\Modules\Learning\Application\Dto\ProgressSyncRow;
use App\Modules\Learning\Application\Dto\SyncCursor;
use App\Modules\Learning\Application\Dto\SyncDeltaView;
use App\Modules\Learning\Application\Dto\TermSyncView;
use App\Modules\Learning\Application\Dto\TriageSyncRow;
use App\Modules\Learning\Application\Port\EnabledModesReader;
use App\Modules\Learning\Application\Port\ModeAdmissionReader;
use App\Modules\Learning\Application\Port\ProgressSyncReader;
use App\Modules\Learning\Application\Port\TriageSyncReader;
use App\Modules\Learning\Application\Service\CardLanguageResolver;
use App\Modules\Learning\Domain\ValueObject\ExerciseMode;
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
        private TriageSyncReader $triageSync,
        private EnabledModesReader $enabledModes,
        private ModeAdmissionReader $admission,
        private Clock $clock,
        private CardLanguageResolver $cardLanguages,
    ) {}

    public function __invoke(GetSyncDelta $query): SyncDeltaView
    {
        $upper = $query->cursor !== null ? $query->cursor->upper : $this->clock->now();
        $since = $query->since;

        $collections = $this->collectionSync->changedCollections($query->userId, $since, $upper);
        $items = $this->collectionSync->changedItems($query->userId, $since, $upper);
        // Terms that changed globally, plus terms pulled in by a fresh subscription this window
        // (their own updated_at is old, so a subscribed collection would otherwise arrive contentless).
        // The id set also carries terms that just LEFT the user's scope: a retired term is gone
        // from every live item by the time we look, so without them its tombstone would have
        // nothing to ride and the word would stay in the phone's mirror forever.
        // …and THE POOL, whatever became of the folders its words came from. The pool is the queue
        // and a collection is a catalogue: deleting a folder pauses nothing, and a word can join the
        // pool with no folder at all. Scoped by collections alone, such a word's content stopped
        // being shipped while its progress kept coming — so the phone held a queued pair it could
        // not draw, and the next full snapshot (every pull-to-refresh) reaped it, while the server
        // went on dealing it in sessions.
        $scopedTermIds = array_values(array_unique([
            ...$this->collectionSync->liveTermIds($query->userId),
            ...$this->collectionSync->recentlyRemovedTermIds($query->userId, $since, $upper),
            ...$this->progressSync->pooledTermIds($query->userId),
        ]));
        $termRefs = $this->mergeTermRefs(
            $this->termChanges->changedTermIds($scopedTermIds, $since, $upper),
            array_map(
                static fn (SubscribedTermRef $ref): TermChangeRef => new TermChangeRef($ref->id, $ref->updatedAt),
                $this->collectionSync->newlySubscribedTermRefs($query->userId, $since, $upper),
            ),
            // Enrolment touches the term not at all, so a word taken into study today can carry a
            // timestamp from months ago and be missed by the window above.
            array_map(
                static fn (PooledTermRef $ref): TermChangeRef => new TermChangeRef($ref->id, $ref->updatedAt),
                $this->progressSync->newlyEnrolledTermRefs($query->userId, $since, $upper),
            ),
        );
        $progress = $this->progressSync->changedProgress($query->userId, $since, $upper);
        // Triage verdicts the delta feed carries so a signed-out client (which wiped its local
        // triage marker) restores it on the next sync — otherwise `unknown` swipes, which leave no
        // progress row, resurrect in the deck after every re-login.
        $triages = $this->triageSync->changedTriages($query->userId, $since, $upper);

        // Fixed order → deterministic offset paging across a heterogeneous stream.
        $all = [...$collections, ...$items, ...$termRefs, ...$progress, ...$triages];
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
        /** @var list<TriageSyncRow> $pTriages */
        $pTriages = [];
        foreach ($page as $row) {
            if ($row instanceof CollectionSyncRow) {
                $pCollections[] = new CollectionChange(
                    $row->id, $row->deleted, $row->updatedAt, $row->title, $row->description,
                    $row->topic, $row->sourceLang, $row->targetLang, $row->itemsCount,
                    $row->source, $row->type,
                    $row->imageUrl, $row->imageAuthor, $row->imageAuthorUrl, $row->isDefault,
                );
            } elseif ($row instanceof CollectionItemSyncRow) {
                $pItems[] = new CollectionItemChange(
                    $row->collectionId, $row->termId, $row->deleted, $row->updatedAt, $row->position, $row->note,
                );
            } elseif ($row instanceof TermChangeRef) {
                $pTermRefs[] = $row;
            } elseif ($row instanceof ProgressSyncRow) {
                $pProgress[] = $row;
            } elseif ($row instanceof TriageSyncRow) {
                $pTriages[] = $row;
            }
        }

        // Tombstones need no content — and asking for it would return nothing anyway.
        // The mirror on the phone is one translation per term, so the language it is written in has
        // to be the one that term's own COLLECTION supports — not the owner's profile, and not
        // whatever row sorted first. A shelf holding an `en→ru` deck beside an `en→uk` one mirrors
        // each in its own language, which is the whole point of the pair being on the collection.
        $liveTermIds = array_map(
            static fn (TermChangeRef $r): TermId => TermId::fromString($r->id),
            array_values(array_filter($pTermRefs, static fn (TermChangeRef $r): bool => ! $r->deleted)),
        );
        $content = $this->termContent->byIds(
            $liveTermIds,
            $this->cardLanguages->forTerms($query->userId, $liveTermIds),
        );
        $terms = array_map(
            static fn (TermChangeRef $r): TermSyncView => new TermSyncView(
                $r->id,
                $r->updatedAt,
                $r->deleted ? null : ($content[$r->id] ?? null),
                $r->deleted,
            ),
            $pTermRefs,
        );

        // The user's trainer toggles ride along with every page. They are settings, not a change
        // stream: tiny, and diffing them would buy nothing but a way for a flipped toggle to get
        // stuck on a client that happened to miss its window. The device applies them to its local
        // practice builder, so a toggle reaches the phone on the next sync — no reinstall.
        $modes = array_map(
            static fn (ExerciseMode $m): string => $m->value,
            $this->enabledModes->forUser($query->userId)->modes,
        );
        // …and the ADMISSION MATRIX with them, for the same reason and by the same rule. The device
        // assembles sessions offline, so it has to know not only which trainers are on but which
        // rung of the ladder opens each — otherwise it would deal a dictation card to a word the
        // learner met a minute ago, and only find out at the next sync.
        $admission = $this->admission->matrixFor($query->userId)->toWire();

        return new SyncDeltaView($upper, $nextCursor, $hasMore, $pCollections, $pItems, $terms, $pProgress, $pTriages, $modes, $admission);
    }

    /**
     * Union the term refs from every source, deduped by id (keeping the later timestamp), ordered by
     * (updatedAt, id) so the concatenated stream pages deterministically.
     *
     * Three sources today: terms that CHANGED in the window, terms a fresh subscription pulled in,
     * and terms that entered the POOL. The last two exist for the same reason — neither act touches
     * the term itself, so its own timestamp cannot carry it into the window.
     *
     * @param  list<TermChangeRef>  ...$lists  changed refs FIRST: only they carry tombstones
     * @return list<TermChangeRef>
     */
    private function mergeTermRefs(array ...$lists): array
    {
        $byId = [];
        foreach ($lists as $list) {
            foreach ($list as $ref) {
                $existing = $byId[$ref->id] ?? null;
                // The first list wins ties: it is the CHANGED one, and only it can carry a
                // tombstone. A ref pulled in by a subscription or an enrolment says «ship this
                // term's content», never «drop it».
                if ($existing === null || $ref->updatedAt > $existing->updatedAt) {
                    $byId[$ref->id] = $ref;
                }
            }
        }

        $merged = array_values($byId);
        usort($merged, static fn (TermChangeRef $a, TermChangeRef $b): int => [$a->updatedAt, $a->id] <=> [$b->updatedAt, $b->id]);

        return $merged;
    }
}

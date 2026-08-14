<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Controller;

use App\Modules\Learning\Application\Dto\CollectionChange;
use App\Modules\Learning\Application\Dto\CollectionItemChange;
use App\Modules\Learning\Application\Dto\ProgressSyncRow;
use App\Modules\Learning\Application\Dto\SyncCursor;
use App\Modules\Learning\Application\Dto\SyncDeltaView;
use App\Modules\Learning\Application\Dto\TermSyncView;
use App\Modules\Learning\Application\Dto\TriageSyncRow;
use App\Modules\Learning\Application\Port\SyncCursorReader;
use App\Modules\Learning\Application\Query\GetSyncDelta;
use App\Modules\Learning\Application\Query\GetSyncDeltaHandler;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Offline sync for the mobile client. `cursor` returns the client_seq high-water mark (counter
 * seeding); `sync` is the delta feed the local database mirrors — collections, items, terms and
 * progress changed since a server timestamp, tombstones for deletions, paginated by an opaque
 * cursor. The client reads its own DB per screen; this only refreshes it.
 */
final class SyncController
{
    private const DEFAULT_LIMIT = 500;
    private const MAX_LIMIT = 1000;

    public function __construct(
        private readonly SyncCursorReader $cursor,
        private readonly GetSyncDeltaHandler $sync,
    ) {}

    public function cursor(Request $request): JsonResponse
    {
        $view = $this->cursor->cursorFor(
            UserId::fromString((string) $request->user()?->getAuthIdentifier()),
        );

        return response()->json(['data' => [
            'max_triage_seq' => $view->maxTriageSeq,
            'max_review_seq' => $view->maxReviewSeq,
        ]]);
    }

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'since' => ['sometimes', 'nullable', 'date'],     // last server_time; never the device clock
            'cursor' => ['sometimes', 'nullable', 'string'],  // opaque, from a previous page
            'limit' => ['sometimes', 'integer', 'min:1', 'max:' . self::MAX_LIMIT],
        ]);

        $sinceRaw = $validated['since'] ?? null;
        $cursorRaw = $validated['cursor'] ?? null;

        $view = ($this->sync)(new GetSyncDelta(
            userId: UserId::fromString((string) $request->user()?->getAuthIdentifier()),
            since: is_string($sinceRaw) && $sinceRaw !== '' ? new DateTimeImmutable($sinceRaw) : null,
            cursor: is_string($cursorRaw) && $cursorRaw !== '' ? SyncCursor::decode($cursorRaw) : null,
            limit: (int) ($validated['limit'] ?? self::DEFAULT_LIMIT),
        ));

        return response()->json(['data' => $this->serialize($view)]);
    }

    /** @return array<string, mixed> */
    private function serialize(SyncDeltaView $view): array
    {
        return [
            'server_time' => $view->serverTime->format(DATE_ATOM),
            'next_cursor' => $view->nextCursor,
            'has_more' => $view->hasMore,
            // Settings, not a change stream — see GetSyncDeltaHandler. The client mirrors this into
            // its practice builder, which is how a flipped trainer toggle reaches the device.
            'settings' => [
                'exercise_modes' => $view->exerciseModes,
                // The acquisition-ladder matrix. `min_step` is the rung the three thresholds work
                // out to — sent as well as them, because the device filters a ladder by rung and
                // deriving it there would be a second implementation of LearningLadder.
                'mode_admission' => $view->modeAdmission,
            ],
            'changes' => [
                'collections' => array_map($this->collection(...), $view->collections),
                'collection_items' => array_map($this->item(...), $view->collectionItems),
                'terms' => array_map($this->term(...), $view->terms),
                'progress' => array_map($this->progressRow(...), $view->progress),
                'triages' => array_map($this->triageRow(...), $view->triages),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function collection(CollectionChange $r): array
    {
        if ($r->deleted) {
            return ['id' => $r->id, 'op' => 'delete', 'updated_at' => $r->updatedAt->format(DATE_ATOM)];
        }

        return [
            'id' => $r->id,
            'op' => 'upsert',
            'updated_at' => $r->updatedAt->format(DATE_ATOM),
            'title' => $r->title,
            'description' => $r->description,
            'topic' => $r->topic,
            'source_lang' => $r->sourceLang,
            'target_lang' => $r->targetLang,
            'items_count' => $r->itemsCount,
            'source' => $r->source,
            'type' => $r->type,
            'image_url' => $r->imageUrl,
            'image_author' => $r->imageAuthor,
            'image_author_url' => $r->imageAuthorUrl,
        ];
    }

    /** @return array<string, mixed> */
    private function item(CollectionItemChange $r): array
    {
        return [
            'collection_id' => $r->collectionId,
            'term_id' => $r->termId,
            'op' => $r->deleted ? 'delete' : 'upsert',
            'updated_at' => $r->updatedAt->format(DATE_ATOM),
            'position' => $r->position,
            'note' => $r->note,
        ];
    }

    /** @return array<string, mixed> */
    private function term(TermSyncView $t): array
    {
        // A retired term: the client drops it from its mirror. Same shape as a collection
        // tombstone — id, op, timestamp, no payload.
        if ($t->deleted) {
            return ['id' => $t->id, 'op' => 'delete', 'updated_at' => $t->updatedAt->format(DATE_ATOM)];
        }

        $c = $t->content;

        return [
            'id' => $t->id,
            'op' => 'upsert',
            'updated_at' => $t->updatedAt->format(DATE_ATOM),
            'text' => $c?->text,
            'type' => $c?->type,
            'transcription' => $c?->transcription,
            'translation' => $c?->translation,
            'example' => $c?->example,
            'example_translation' => $c?->exampleTranslation,
            'image_url' => $c?->imageUrl,
            'image_author' => $c?->imageAuthor,
            'image_author_url' => $c?->imageAuthorUrl,
            // Additive, and required rather than cosmetic: the client grades typed answers from its
            // own database, so it has to hold the same accepted set the server grades against.
            // Absent variants would make the device stricter than the server, which the invariant
            // «клиентская проверка никогда не строже серверной» forbids.
            'accepted_variants' => $c->acceptedVariants ?? [],
            // Shipped ahead of the trainer that reads them, so the device already has them offline
            // when find_the_mistake is switched on.
            'example_distractors' => $c->exampleDistractors ?? [],
        ];
    }

    /** @return array<string, mixed> */
    private function progressRow(ProgressSyncRow $r): array
    {
        return [
            'term_id' => $r->termId,
            'op' => 'upsert',
            'updated_at' => $r->updatedAt->format(DATE_ATOM),
            'state' => $r->state,
            'ease_factor' => $r->easeFactor,
            'interval_days' => $r->intervalDays,
            'due_at' => $r->dueAt?->format(DATE_ATOM),
            'reps' => $r->reps,
            'lapses' => $r->lapses,
            'last_reviewed_at' => $r->lastReviewedAt?->format(DATE_ATOM),
            // The acquisition ladder, orthogonal to `state` above: `state` says when the pair comes
            // back, these say what it comes back as. The device mirrors the same pure function the
            // server uses to turn them into a rung, which is how an offline session knows whether a
            // word is owed an intro or a dictation.
            'acquisition' => $r->acquisition,
            'learning_step' => $r->learningStep,
        ];
    }

    /**
     * Governing triage verdict for a term. Append-only server-side, so it is always an upsert —
     * the client upserts it into its local triage marker so a re-login can't resurrect the swipe.
     *
     * @return array<string, mixed>
     */
    private function triageRow(TriageSyncRow $r): array
    {
        return [
            'term_id' => $r->termId,
            'op' => 'upsert',
            'updated_at' => $r->updatedAt->format(DATE_ATOM),
            'verdict' => $r->verdict,
            'client_seq' => $r->clientSeq,
            'collection_id' => $r->collectionId,
        ];
    }
}

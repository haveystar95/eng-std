<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Controller;

use App\Modules\Learning\Application\Dto\CollectionChange;
use App\Modules\Learning\Application\Dto\CollectionItemChange;
use App\Modules\Learning\Application\Dto\ProgressSyncRow;
use App\Modules\Learning\Application\Dto\SyncCursor;
use App\Modules\Learning\Application\Dto\SyncDeltaView;
use App\Modules\Learning\Application\Dto\TermSyncView;
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
            'changes' => [
                'collections' => array_map($this->collection(...), $view->collections),
                'collection_items' => array_map($this->item(...), $view->collectionItems),
                'terms' => array_map($this->term(...), $view->terms),
                'progress' => array_map($this->progressRow(...), $view->progress),
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
        ];
    }
}

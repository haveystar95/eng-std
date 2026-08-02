<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Controller;

use App\Modules\Learning\Application\Command\TriageTerms;
use App\Modules\Learning\Application\Command\TriageTermsHandler;
use App\Modules\Learning\Application\Dto\TriageInput;
use App\Modules\Learning\Application\Query\GetTriageQueue;
use App\Modules\Learning\Application\Query\GetTriageQueueHandler;
use App\Modules\Learning\Domain\ValueObject\TriageId;
use App\Modules\Learning\Domain\ValueObject\TriageVerdict;
use App\Modules\Learning\Presentation\Http\Request\TriageBatchRequest;
use App\Modules\Learning\Presentation\Http\Resource\TriageCardResource;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The first-pass swipe endpoints. GET the queue (one collection's not-yet-triaged terms),
 * POST a batch of verdicts. Batch is idempotent by client id and never writes to `reviews`.
 */
final class TriageController
{
    public function __construct(
        private readonly TriageTermsHandler $triage,
        private readonly GetTriageQueueHandler $queue,
    ) {}

    public function queue(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'collection_id' => ['required', 'string', 'size:26'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:40'],
        ]);

        $cards = ($this->queue)(new GetTriageQueue(
            userId: $this->actor($request),
            collectionId: (string) $validated['collection_id'],
            limit: (int) ($validated['limit'] ?? 40),
        ));

        return TriageCardResource::collection($cards);
    }

    public function batch(TriageBatchRequest $request): JsonResponse
    {
        $triages = [];
        foreach ((array) $request->validated('triages') as $row) {
            if (! is_array($row)) {
                continue;
            }
            // isset() is already false for a null collection_id, so this covers both.
            $collectionId = isset($row['collection_id'])
                ? CollectionId::fromString((string) $row['collection_id'])
                : null;

            $triages[] = new TriageInput(
                triageId: TriageId::fromString((string) $row['id']),
                termId: TermId::fromString((string) $row['term_id']),
                verdict: TriageVerdict::from((string) $row['verdict']),
                decidedAt: new DateTimeImmutable((string) $row['decided_at']),
                clientSeq: (int) $row['client_seq'],
                collectionId: $collectionId,
                latencyMs: isset($row['latency_ms']) ? (int) $row['latency_ms'] : null,
            );
        }

        $result = ($this->triage)(new TriageTerms($this->actor($request), $triages));

        return response()->json(['data' => [
            'accepted' => $result->accepted,
            'duplicates' => $result->duplicates,
            'unknown' => $result->unknown,
        ]]);
    }

    private function actor(Request $request): UserId
    {
        return UserId::fromString((string) $request->user()?->getAuthIdentifier());
    }
}

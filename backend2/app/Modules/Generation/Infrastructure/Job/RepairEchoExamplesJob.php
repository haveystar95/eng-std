<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Job;

use App\Modules\Generation\Application\Command\RepairEchoExamples;
use App\Modules\Generation\Application\Command\RepairEchoExamplesHandler;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Gives a real example to the terms a fresh generation left without one — the echoes
 * {@see DraftValidator} refused (QA-7).
 *
 * A follow-up job like the image attachment and the enrichment chain, and for the same reason: it
 * costs a model call per bad example, and a collection whose examples are still being repaired is
 * already a complete, playable collection. If this never runs, a few terms simply have no example.
 *
 * Idempotent by what it looks for: a term repaired once no longer echoes, so a retry finds nothing
 * to do and spends nothing.
 */
final class RepairEchoExamplesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 300;

    /** @var list<int> */
    public array $backoff = [30, 180];

    public function __construct(
        private readonly string $collectionId,
        private readonly string $ownerId,
    ) {}

    public function handle(RepairEchoExamplesHandler $repair): void
    {
        try {
            $report = $repair(new RepairEchoExamples(
                actorId: UserId::fromString($this->ownerId),
                collectionId: CollectionId::fromString($this->collectionId),
            ));
        } catch (Throwable $e) {
            // Never poison the queue over a follow-up: the collection is complete without this.
            Log::warning('echo-example repair failed', [
                'collection_id' => $this->collectionId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if ($report->needingRepair === 0) {
            return;
        }

        Log::info('echo-example repair', [
            'collection_id' => $this->collectionId,
            'examined' => $report->examined,
            'needing_repair' => $report->needingRepair,
            'repaired' => $report->repaired(),
            'cost_usd' => $report->costUsd,
            'failures' => count($report->failures),
        ]);
    }
}

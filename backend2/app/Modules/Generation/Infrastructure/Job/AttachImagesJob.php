<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Job;

use App\Modules\Generation\Application\Command\AttachCollectionImages;
use App\Modules\Generation\Application\Command\AttachCollectionImagesHandler;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Attaches stock photos to a generated collection AFTER it has been created — separate from the
 * generation job so it never blocks or fails the generation itself. Images are best-effort: if this
 * job exhausts its retries, the collection simply keeps null images (the client shows placeholders).
 *
 * Transient image-search failures (rate limit / 5xx / network) surface as exceptions and are retried
 * with backoff. An empty search result is NOT an exception — it's a null image, no retry. The attach
 * is idempotent (readers skip already-imaged items; aggregates never overwrite), so retries are safe.
 */
final class AttachImagesJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    /** @var list<int> */
    public array $backoff = [10, 30, 60];

    public function __construct(private readonly string $collectionId) {}

    public function handle(AttachCollectionImagesHandler $handler, OutboundCallContext $context): void
    {
        // Every image search inside this scope is stamped with the collection it is buying art for,
        // so "what did this deck cost me" is one filter in the log rather than a guess.
        $context->run(null, $this->collectionId, fn () => $handler(
            new AttachCollectionImages(CollectionId::fromString($this->collectionId)),
        ));
    }

    public function failed(Throwable $e): void
    {
        // Best-effort feature: a terminal failure just means no images. Record it and move on —
        // there is no generation state to unwind (the collection already succeeded).
        Log::warning('AttachImagesJob failed; collection keeps null images', [
            'collection_id' => $this->collectionId,
            'error' => $e->getMessage(),
        ]);
    }
}

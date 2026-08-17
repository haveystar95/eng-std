<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Command;

use App\Modules\Collections\Application\Command\AttachCollectionImage;
use App\Modules\Collections\Application\Command\AttachCollectionImageHandler;
use App\Modules\Collections\Application\Query\GetCollectionTermSet;
use App\Modules\Collections\Application\Query\GetCollectionTermSetHandler;
use App\Modules\Collections\Application\Query\PendingCollectionImageReader;
use App\Modules\Generation\Application\Port\ImageSearchPort;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Vocabulary\Application\Command\AttachTermImage;
use App\Modules\Vocabulary\Application\Command\AttachTermImageHandler;
use App\Modules\Vocabulary\Application\Query\PendingTermImageReader;

/**
 * Orchestrates image attachment for a generated collection, talking to Vocabulary and Collections
 * only through their Application. Runs off the request thread (see AttachImagesJob) so generation
 * never waits on it.
 *
 * Idempotency and cost-safety come from the readers + aggregates, not from here:
 * - the readers return ONLY terms/collection lacking an image but carrying a query, so an already
 *   imaged (or globally-shared, already imaged) term is never re-searched — that also covers the
 *   prompt-cache path, where reused terms already have both prompt and photo;
 * - the attach commands never overwrite an existing image.
 *
 * A genuine empty result is a null return and simply skips that item (no retry). A transient
 * ImageSearchPort failure throws {@see \App\Modules\Generation\Application\Port\TransientImageSearchError}
 * — it propagates so the job retries with backoff; terms already attached this pass are persisted and
 * skipped on the retry.
 */
final readonly class AttachCollectionImagesHandler
{
    public function __construct(
        private GetCollectionTermSetHandler $termSet,
        private PendingTermImageReader $pendingTerms,
        private AttachTermImageHandler $attachTerm,
        private PendingCollectionImageReader $pendingCover,
        private AttachCollectionImageHandler $attachCover,
        private ImageSearchPort $images,
    ) {}

    public function __invoke(AttachCollectionImages $command): void
    {
        $collectionId = $command->collectionId;

        $termSet = ($this->termSet)(new GetCollectionTermSet($collectionId));
        if ($termSet !== null) {
            foreach ($this->pendingTerms->pendingFor($termSet->termIds) as $pending) {
                $result = $this->images->search($pending->query);
                if ($result !== null) {
                    ($this->attachTerm)(new AttachTermImage(
                        TermId::fromString($pending->termId),
                        $result->url,
                        $result->author,
                        $result->authorUrl,
                    ));
                }
            }
        }

        $coverQuery = $this->pendingCover->pendingFor($collectionId);
        if ($coverQuery !== null) {
            $result = $this->images->search($coverQuery);
            if ($result !== null) {
                ($this->attachCover)(new AttachCollectionImage(
                    $collectionId,
                    $result->url,
                    $result->author,
                    $result->authorUrl,
                ));
            }
        }
    }
}

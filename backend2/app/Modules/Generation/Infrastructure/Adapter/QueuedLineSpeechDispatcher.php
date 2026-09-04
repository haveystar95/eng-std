<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\DispatchesLineSpeech;
use App\Modules\Generation\Infrastructure\Job\SpeakLinesJob;
use App\Modules\Shared\Domain\ValueObject\CollectionId;

final class QueuedLineSpeechDispatcher implements DispatchesLineSpeech
{
    public function __construct(private readonly bool $enabled) {}

    public function dispatch(CollectionId $collectionId): void
    {
        if (! $this->enabled) {
            return;
        }

        SpeakLinesJob::dispatch($collectionId->value);
    }
}

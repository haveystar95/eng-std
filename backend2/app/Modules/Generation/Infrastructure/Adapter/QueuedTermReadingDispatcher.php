<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Port\DispatchesTermReading;
use App\Modules\Generation\Infrastructure\Job\WriteTermReadingJob;
use App\Modules\Shared\Domain\ValueObject\TermId;

/** Fulfils the reading-dispatch port with the Generation queue job. */
final class QueuedTermReadingDispatcher implements DispatchesTermReading
{
    public function writeReading(TermId $termId, string $supportLang): void
    {
        WriteTermReadingJob::dispatch($termId->value, $supportLang);
    }
}

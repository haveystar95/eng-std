<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\PlaygroundRunRequest;
use App\Modules\Generation\Application\Port\PlaygroundRunDispatcher;
use App\Modules\Generation\Infrastructure\Job\RunPlaygroundCallJob;

/** Fulfils the sandbox dispatch port with the Generation queue job. */
final class QueuedPlaygroundRunDispatcher implements PlaygroundRunDispatcher
{
    public function dispatch(PlaygroundRunRequest $request): void
    {
        RunPlaygroundCallJob::dispatch($request->runId, $request->provider, $request->model, $request->prompt, $request->temperature);
    }
}

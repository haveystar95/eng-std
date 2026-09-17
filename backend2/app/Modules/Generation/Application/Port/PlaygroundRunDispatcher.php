<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\PlaygroundRunRequest;

/** Queues the call of a sandbox run (наряд GEN-3) — the same queued way a plan is written, never inside the web request. */
interface PlaygroundRunDispatcher
{
    public function dispatch(PlaygroundRunRequest $request): void;
}

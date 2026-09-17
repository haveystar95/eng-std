<?php

declare(strict_types=1);

namespace App\Modules\Generation\Application\Port;

use App\Modules\Generation\Application\Dto\PlaygroundRun;

/**
 * Where a sandbox run waits to be read (наряд GEN-3). Short-lived by nature: a run is read by the screen that started it,
 * minutes later at most; what it spent is not kept here but in the journal of model calls.
 */
interface PlaygroundRunStore
{
    public function save(PlaygroundRun $run): void;

    public function find(string $id): ?PlaygroundRun;
}

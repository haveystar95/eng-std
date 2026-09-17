<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

/** One sandbox run by its id — what the screen polls until the run is done. */
final readonly class GetPlaygroundRun
{
    public function __construct(public string $runId) {}
}

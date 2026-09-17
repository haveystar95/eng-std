<?php

declare(strict_types=1);

namespace App\Modules\Observability\Infrastructure\Console;

use App\Modules\Observability\Application\Port\ModelCallJournal;
use DateTimeImmutable;
use Illuminate\Console\Command;

/**
 * `model-calls:sweep-lost` — the calls of processes that ended mid-call (наряд GEN-3). A worker killed at its job's timeout
 * runs no `finally`: its journal row stays `started`. Once the caller's own wait for the answer is over by more than the
 * grace, nothing will ever finish that row — it is `lost`: the vendor may have answered and billed, and nobody read it.
 * Scheduled in `routes/console.php`; idempotent.
 */
final class SweepLostModelCallsCommand extends Command
{
    /** Past the caller's timeout, how long a `started` row still waits for its process to write the answer down. */
    public const GRACE_SECONDS = 60;

    protected $signature = 'model-calls:sweep-lost';

    protected $description = 'Mark lost the model calls whose process ended before their answer was recorded';

    public function handle(ModelCallJournal $journal): int
    {
        $marked = $journal->sweepLost(new DateTimeImmutable, self::GRACE_SECONDS);
        $this->line("marked lost: {$marked}");

        return self::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Generation\Presentation\Console;

use App\Modules\Generation\Application\Service\ExpirePracticeDialog;
use App\Modules\Generation\Domain\Repository\PracticeDialogRepository;
use App\Modules\Shared\Domain\Service\Clock;
use Illuminate\Console\Command;

/**
 * Background sweep: retire practice dialogs whose realtime token TTL lapsed while still active,
 * recording their estimated spend. On-access expiry (a transcript/finish call) covers dialogs the
 * client returns to; this catches the ones it never does. Safe to run on a schedule.
 */
final class ExpireStaleDialogsCommand extends Command
{
    protected $signature = 'practice:expire-dialogs {--limit=200 : max dialogs to sweep per run}';

    protected $description = 'Expire practice dialogs whose realtime token TTL has lapsed';

    public function handle(PracticeDialogRepository $dialogs, ExpirePracticeDialog $expire, Clock $clock): int
    {
        $now = $clock->now();
        $limitOption = $this->option('limit');
        $limit = is_numeric($limitOption) ? (int) $limitOption : 200;

        $stale = $dialogs->staleActive($now, $limit);
        foreach ($stale as $dialog) {
            $expire->ifStale($dialog, $now);
        }

        $this->info('Expired ' . count($stale) . ' stale dialog(s).');

        return self::SUCCESS;
    }
}

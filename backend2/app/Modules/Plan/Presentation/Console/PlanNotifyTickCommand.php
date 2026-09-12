<?php

declare(strict_types=1);

namespace App\Modules\Plan\Presentation\Console;

use App\Modules\Plan\Application\Command\RunNotificationTick;
use App\Modules\Plan\Application\Command\RunNotificationTickHandler;
use Illuminate\Console\Command;

/**
 * THE 15-MINUTE TICK — scheduled in `routes/console.php`, run by the `scheduler` compose service
 * (`schedule:work`). Idempotent: running it twice in a quarter hour writes nothing the second time.
 */
final class PlanNotifyTickCommand extends Command
{
    protected $signature = 'plan:notify-tick';

    protected $description = 'Plan notifications: event-date facts and the daily reminder for every active plan';

    public function handle(RunNotificationTickHandler $handler): int
    {
        $handler(new RunNotificationTick);
        $this->info('plan:notify-tick done');

        return self::SUCCESS;
    }
}

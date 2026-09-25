<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Console;

use App\Modules\Identity\Application\Command\RevokeAccess;
use App\Modules\Identity\Application\Command\RevokeAccessHandler;
use App\Modules\Identity\Application\Query\GetAccess;
use App\Modules\Identity\Application\Query\GetAccessHandler;
use Illuminate\Console\Command;

/**
 * THE OWNER TAKES THE PAID PLAN BACK (наряд ACC-1 §2): `php artisan access:revoke {user}` — every right of the learner in
 * force becomes `expired`, ending now; the rows stay.
 */
final class AccessRevokeCommand extends Command
{
    use NamesALearner;

    protected $signature = 'access:revoke {user : the learner — id or email}';

    protected $description = 'Take a learner\'s paid plan back: every right in force expires now';

    public function handle(RevokeAccessHandler $revoke, GetAccessHandler $access): int
    {
        $userId = $this->learner();
        if ($userId === null) {
            return self::FAILURE;
        }
        $taken = $revoke(new RevokeAccess($userId));
        $this->info(sprintf('Revoked %d right(s) of %s — access: %s.', $taken, $userId->value, $access(new GetAccess($userId))->plan));

        return self::SUCCESS;
    }
}

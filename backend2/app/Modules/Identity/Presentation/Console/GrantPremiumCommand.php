<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Console;

use App\Modules\Identity\Application\Port\UserTierWriter;
use App\Modules\Shared\Domain\ValueObject\SubscriptionTier;
use Illuminate\Console\Command;

/**
 * DEV utility: flip a user to the premium tier so premium-only features (realtime practice
 * dialogs) can be self-tested without a StoreKit purchase. Not wired into any user-facing flow.
 */
final class GrantPremiumCommand extends Command
{
    protected $signature = 'practice:grant-premium {email : email of the user to make premium}';

    protected $description = 'DEV: set a user tier to premium (for self-testing premium features)';

    public function handle(UserTierWriter $tiers): int
    {
        $emailArg = $this->argument('email');
        $email = is_string($emailArg) ? $emailArg : '';

        if ($tiers->setTierByEmail($email, SubscriptionTier::Premium)) {
            $this->info("Granted premium to {$email}.");

            return self::SUCCESS;
        }

        $this->error("No user with a profile found for {$email}.");

        return self::FAILURE;
    }
}

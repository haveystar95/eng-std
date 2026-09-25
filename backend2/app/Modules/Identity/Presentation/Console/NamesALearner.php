<?php

declare(strict_types=1);

namespace App\Modules\Identity\Presentation\Console;

use App\Modules\Identity\Application\Port\UserReader;
use App\Modules\Shared\Domain\ValueObject\UserId;
use InvalidArgumentException;

/**
 * The learner a command of the owner's names — by id or by email (`access:grant`, `access:revoke`): the account found, or
 * an error printed and null.
 *
 * @phpstan-require-extends \Illuminate\Console\Command
 */
trait NamesALearner
{
    private function learner(): ?UserId
    {
        $users = app(UserReader::class);
        $argument = $this->argument('user');
        $named = trim(is_string($argument) ? $argument : '');
        $view = null;
        if (str_contains($named, '@')) {
            $view = $users->byEmail($named);
        } else {
            try {
                $view = $users->byId(UserId::fromString($named));
            } catch (InvalidArgumentException) {
                $view = null;
            }
        }
        if ($view === null) {
            $this->error("No learner {$named}.");

            return null;
        }

        return UserId::fromString($view->id);
    }
}

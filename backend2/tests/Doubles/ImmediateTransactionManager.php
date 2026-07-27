<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Shared\Domain\Service\TransactionManager;

/** Runs the unit of work inline — no database, no real transaction. */
final class ImmediateTransactionManager implements TransactionManager
{
    public function run(callable $work): mixed
    {
        return $work();
    }
}

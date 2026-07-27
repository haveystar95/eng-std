<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure;

use App\Modules\Shared\Domain\Service\TransactionManager;
use Illuminate\Support\Facades\DB;

final class IlluminateTransactionManager implements TransactionManager
{
    public function run(callable $work): mixed
    {
        return DB::transaction(static fn (): mixed => $work());
    }
}

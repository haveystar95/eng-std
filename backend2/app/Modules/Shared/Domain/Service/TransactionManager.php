<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Service;

/**
 * Runs a unit of work atomically. Lets Application handlers wrap a multi-step write
 * (insert reviews + fold progress + project stats) in one transaction without importing
 * the framework's DB facade into the Application layer.
 */
interface TransactionManager
{
    /**
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public function run(callable $work): mixed;
}

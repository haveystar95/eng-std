<?php

declare(strict_types=1);

namespace App\Modules\Admin\Application\Query;

use RuntimeException;

/** A plan code shared by two plans — the panel answers 409 with their ids instead of guessing (наряд ADM-1). */
final class PlanCodeConflict extends RuntimeException
{
    /** @param list<string> $ids */
    public function __construct(string $message, public readonly array $ids)
    {
        parent::__construct($message);
    }
}

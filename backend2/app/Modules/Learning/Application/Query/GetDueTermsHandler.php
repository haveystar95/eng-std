<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Query;

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Port\DueTermsReader;

/**
 * Selection rules: due before new (a backlog of due cards means no new words that day),
 * new terms only fill the leftover session slots and never exceed the day's remaining
 * quota. Session size is capped so one call can't drown the user.
 */
final readonly class GetDueTermsHandler
{
    private const MAX_SESSION_SIZE = 100;

    public function __construct(private DueTermsReader $reader) {}

    /** @return list<DueTermView> */
    public function __invoke(GetDueTerms $query): array
    {
        $size = max(1, min(self::MAX_SESSION_SIZE, $query->sessionSize));

        $due = $this->reader->due($query->userId, $query->now, $size);

        $remaining = $size - count($due);
        $newLimit = min($remaining, max(0, $query->newTermsRemaining));
        if ($newLimit <= 0) {
            return $due;
        }

        return array_merge($due, $this->reader->newTerms($query->userId, $newLimit));
    }
}

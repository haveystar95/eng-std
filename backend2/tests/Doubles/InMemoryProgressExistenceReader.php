<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Application\Port\ProgressExistenceReader;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class InMemoryProgressExistenceReader implements ProgressExistenceReader
{
    /** @param list<string> $started term ids that already have progress */
    public function __construct(private readonly array $started = []) {}

    public function existingTermIds(UserId $userId, array $termIds): array
    {
        $set = [];
        foreach ($termIds as $termId) {
            if (in_array($termId, $this->started, true)) {
                $set[$termId] = true;
            }
        }

        return $set;
    }
}

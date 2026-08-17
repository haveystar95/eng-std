<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Port\ProgressSnapshotReader;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class InMemoryProgressSnapshotReader implements ProgressSnapshotReader
{
    /** @param array<string, DueTermView> $snapshots keyed by term id */
    public function __construct(private readonly array $snapshots = []) {}

    public function forTerms(UserId $userId, array $termIds): array
    {
        $out = [];
        foreach ($termIds as $termId) {
            if (isset($this->snapshots[$termId])) {
                $out[$termId] = $this->snapshots[$termId];
            }
        }

        return $out;
    }
}

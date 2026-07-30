<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Application\Port\SessionCompositionReader;

final class FakeSessionCompositionReader implements SessionCompositionReader
{
    /** @param array<string, array<string, true>> $compositions session id → set of term ids */
    public function __construct(private readonly array $compositions = []) {}

    public function compositionsByIds(array $sessionIds): array
    {
        return array_intersect_key($this->compositions, array_flip($sessionIds));
    }
}

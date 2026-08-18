<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Generation\Application\Port\LoggedResponseReader;

final class InMemoryLoggedResponseReader implements LoggedResponseReader
{
    /** @var array<string, array<string, mixed>> */
    private array $byId = [];

    /** @param array<string, mixed> $body */
    public function put(string $logId, array $body): void
    {
        $this->byId[$logId] = $body;
    }

    public function findResponseBody(string $logId): ?array
    {
        return $this->byId[$logId] ?? null;
    }
}

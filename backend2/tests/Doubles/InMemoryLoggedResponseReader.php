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

    /** @var array<string, array{calls: int, prompt_tokens: int, cached_tokens: int}> */
    private array $cacheByModel = [];

    /** @param array<string, array{calls: int, prompt_tokens: int, cached_tokens: int}> $byModel */
    public function putPromptCache(array $byModel): void
    {
        $this->cacheByModel = $byModel;
    }

    public function promptCacheByModel(string $purpose, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        // Window-independent on purpose: the double exists so a caller can be tested against a
        // known answer, and a fake that also re-implemented the time filtering would be a second
        // implementation of the thing under test.
        return $this->cacheByModel;
    }
}

<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Vocabulary\Application\Query\StaleCoreReader;

/** Terms are stale iff their id was handed to the constructor. */
final class FakeStaleCoreReader implements StaleCoreReader
{
    /** @param  list<string>  $staleIds */
    public function __construct(private array $staleIds = []) {}

    /** @param  list<string>  $staleIds */
    public function setStale(array $staleIds): void
    {
        $this->staleIds = $staleIds;
    }

    public function idsByPromptVersion(array $promptVersions, ?string $afterId = null, int $limit = 500): array
    {
        return array_slice($this->staleIds, 0, max(1, $limit));
    }

    public function countByPromptVersion(array $promptVersions): int
    {
        return count($this->staleIds);
    }

    public function idsNotWrittenBy(array $termIds, string $promptVersion): array
    {
        return array_values(array_intersect($termIds, $this->staleIds));
    }
}

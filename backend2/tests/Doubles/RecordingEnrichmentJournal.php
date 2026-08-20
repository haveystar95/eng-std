<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Generation\Application\Port\EnrichmentJournal;

/** An in-memory journal: everything is pending until marked, and marks can be cleared. */
final class RecordingEnrichmentJournal implements EnrichmentJournal
{
    /** @var array<string, true>  "termId|version" */
    private array $done = [];

    /** @var list<string>  term ids whose mark was cleared, in order */
    public array $cleared = [];

    public function pending(array $termIds, string $generatorVersion): array
    {
        return array_values(array_filter(
            $termIds,
            fn (string $id): bool => ! isset($this->done[$id . '|' . $generatorVersion]),
        ));
    }

    public function markDone(string $termId, string $generatorVersion): void
    {
        $this->done[$termId . '|' . $generatorVersion] = true;
    }

    public function clearMark(string $termId, string $generatorVersion): void
    {
        unset($this->done[$termId . '|' . $generatorVersion]);
        $this->cleared[] = $termId;
    }

    public function recordFindings(array $findings, string $generatorVersion): void {}

    public function findingsFor(array $termIds, string $generatorVersion): array
    {
        return [];
    }

    public function acknowledgeOpenFindings(string $generatorVersion, ?string $note = null): int
    {
        return 0;
    }
}

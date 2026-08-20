<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Vocabulary\Application\Dto\DistractorAuditRow;
use App\Modules\Vocabulary\Application\Query\DistractorAuditReader;

/** Serves whatever rows the test put in, keyed by term id. */
final class FakeDistractorAuditReader implements DistractorAuditReader
{
    /** @param  array<string, list<DistractorAuditRow>>  $rows */
    public function __construct(private array $rows = []) {}

    public function all(): array
    {
        $out = [];
        foreach ($this->rows as $rows) {
            foreach ($rows as $row) {
                $out[] = $row;
            }
        }

        return $out;
    }

    public function forTerm(string $termId): array
    {
        return $this->rows[$termId] ?? [];
    }
}

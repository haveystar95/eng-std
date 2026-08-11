<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Eloquent;

use App\Modules\Generation\Application\Port\EnrichmentJournal;
use App\Modules\Generation\Domain\ValueObject\EnrichmentFinding;
use App\Modules\Generation\Domain\ValueObject\FindingKind;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Support\Facades\DB;

final class EloquentEnrichmentJournal implements EnrichmentJournal
{
    public function pending(array $termIds, string $generatorVersion): array
    {
        if ($termIds === []) {
            return [];
        }

        $done = DB::table('term_enrichment_versions')
            ->where('generator_version', $generatorVersion)
            ->whereIn('term_id', $termIds)
            ->pluck('term_id')
            ->all();

        $doneSet = array_fill_keys(array_map(static fn (mixed $id): string => (string) $id, $done), true);

        return array_values(array_filter($termIds, static fn (string $id): bool => ! isset($doneSet[$id])));
    }

    public function markDone(string $termId, string $generatorVersion): void
    {
        // insertOrIgnore, not insert: two overlapping runs of the same version are allowed to race
        // here (the primary key settles it) rather than blowing up a whole chunk.
        DB::table('term_enrichment_versions')->insertOrIgnore([
            'term_id' => $termId,
            'generator_version' => $generatorVersion,
            'created_at' => now(),
        ]);
    }

    public function recordFindings(array $findings, string $generatorVersion): void
    {
        if ($findings === []) {
            return;
        }

        DB::table('enrichment_findings')->insert(array_map(
            static fn ($f): array => [
                'id' => Ulid::generate(),
                'term_id' => $f->termId,
                'kind' => $f->kind->value,
                'field' => $f->field,
                'detail' => $f->detail,
                'generator_version' => $generatorVersion,
                'created_at' => now(),
            ],
            $findings,
        ));
    }

    public function findingsFor(array $termIds, string $generatorVersion): array
    {
        if ($termIds === []) {
            return [];
        }

        $out = [];
        foreach (DB::table('enrichment_findings')
            ->where('generator_version', $generatorVersion)
            ->whereIn('term_id', $termIds)
            ->orderBy('id')
            ->get(['term_id', 'kind', 'field', 'detail']) as $row) {
            $kind = FindingKind::tryFrom((string) $row->kind);
            if ($kind === null) {
                continue;
            }
            $out[] = new EnrichmentFinding(
                termId: (string) $row->term_id,
                kind: $kind,
                field: $row->field !== null ? (string) $row->field : null,
                detail: (string) $row->detail,
            );
        }

        return $out;
    }
}

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

    public function clearMark(string $termId, string $generatorVersion): void
    {
        DB::table('term_enrichment_versions')
            ->where('term_id', $termId)
            ->where('generator_version', $generatorVersion)
            ->delete();
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
            // Acknowledged findings stay in the log and leave the worklist.
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('enrichment_finding_acks')
                ->whereColumn('enrichment_finding_acks.finding_id', 'enrichment_findings.id'))
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

    public function acknowledgeOpenFindings(string $generatorVersion, ?string $note = null): int
    {
        $open = DB::table('enrichment_findings')
            ->where('generator_version', $generatorVersion)
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                ->from('enrichment_finding_acks')
                ->whereColumn('enrichment_finding_acks.finding_id', 'enrichment_findings.id'))
            ->pluck('id')
            ->all();

        if ($open === []) {
            return 0;
        }

        $now = now();
        DB::table('enrichment_finding_acks')->insertOrIgnore(array_map(
            static fn (mixed $id): array => [
                'finding_id' => (string) $id,
                'note' => $note,
                'created_at' => $now,
            ],
            $open,
        ));

        return count($open);
    }
}

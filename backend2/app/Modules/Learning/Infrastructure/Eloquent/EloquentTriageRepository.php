<?php

declare(strict_types=1);

namespace App\Modules\Learning\Infrastructure\Eloquent;

use App\Modules\Learning\Domain\Entity\Triage;
use App\Modules\Learning\Domain\Repository\TriageRepository;
use App\Modules\Learning\Domain\ValueObject\TriageId;
use App\Modules\Learning\Domain\ValueObject\TriageVerdict;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class EloquentTriageRepository implements TriageRepository
{
    public function insertIgnore(Triage $triage): bool
    {
        $inserted = DB::table('term_triages')->insertOrIgnore([
            'id' => $triage->id->value,
            'user_id' => $triage->userId->value,
            'term_id' => $triage->termId->value,
            'verdict' => $triage->verdict->value,
            'collection_id' => $triage->collectionId?->value,
            'latency_ms' => $triage->latencyMs,
            'revealed' => $triage->revealed,
            'client_seq' => $triage->clientSeq,
            // Device-stamped, same class as a review's answered_at (see UtcInstant).
            'decided_at' => UtcInstant::bind($triage->decidedAt),
            'created_at' => now(),
        ]);

        return $inserted === 1;
    }

    public function currentByTerm(UserId $userId, array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $ids = array_map(static fn (TermId $id): string => $id->value, $termIds);

        // DISTINCT ON keeps the first row per term_id under the ORDER BY, i.e. the greatest
        // client_seq — the governing verdict. Backed by term_triages_user_term_seq_idx.
        $rows = DB::table('term_triages')
            ->select(['id', 'term_id', 'verdict', 'collection_id', 'latency_ms', 'revealed', 'client_seq', 'decided_at'])
            ->where('user_id', $userId->value)
            ->whereIn('term_id', $ids)
            ->orderBy('term_id')
            ->orderByDesc('client_seq')
            ->distinct('term_id')
            ->get();

        $current = [];
        foreach ($rows as $row) {
            $current[(string) $row->term_id] = new Triage(
                id: TriageId::fromString((string) $row->id),
                userId: $userId,
                termId: TermId::fromString((string) $row->term_id),
                verdict: TriageVerdict::from((string) $row->verdict),
                decidedAt: new DateTimeImmutable((string) $row->decided_at),
                clientSeq: (int) $row->client_seq,
                collectionId: $row->collection_id !== null ? CollectionId::fromString((string) $row->collection_id) : null,
                latencyMs: $row->latency_ms !== null ? (int) $row->latency_ms : null,
                revealed: $row->revealed !== null ? (bool) $row->revealed : null,
            );
        }

        return $current;
    }
}

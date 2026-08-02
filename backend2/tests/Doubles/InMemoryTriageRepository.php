<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Domain\Entity\Triage;
use App\Modules\Learning\Domain\Repository\TriageRepository;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class InMemoryTriageRepository implements TriageRepository
{
    /** @var array<string, Triage> keyed by client id */
    private array $byId = [];

    public function insertIgnore(Triage $triage): bool
    {
        if (isset($this->byId[$triage->id->value])) {
            return false;
        }
        $this->byId[$triage->id->value] = $triage;

        return true;
    }

    public function currentByTerm(UserId $userId, array $termIds): array
    {
        $wanted = array_map(static fn (TermId $id): string => $id->value, $termIds);

        $current = [];
        foreach ($this->byId as $triage) {
            if ($triage->userId->value !== $userId->value || ! in_array($triage->termId->value, $wanted, true)) {
                continue;
            }
            $key = $triage->termId->value;
            // Governing verdict = greatest client_seq, matching the SQL DISTINCT ON.
            if (! isset($current[$key]) || $triage->clientSeq > $current[$key]->clientSeq) {
                $current[$key] = $triage;
            }
        }

        return $current;
    }

    public function count(): int
    {
        return count($this->byId);
    }
}

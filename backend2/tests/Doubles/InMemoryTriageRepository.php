<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Domain\Entity\Triage;
use App\Modules\Learning\Domain\Repository\TriageRepository;

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

    public function count(): int
    {
        return count($this->byId);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Application\Dto\DueTermView;
use App\Modules\Learning\Application\Port\ProgressSnapshotReader;
use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Repository\TermProgressRepository;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class InMemoryTermProgressRepository implements TermProgressRepository, ProgressSnapshotReader
{
    /** @var array<string, TermProgress> */
    private array $byKey = [];

    public function findForUpdate(UserId $userId, TermId $termId): ?TermProgress
    {
        return $this->byKey[$this->key($userId->value, $termId->value)] ?? null;
    }

    public function forTerms(UserId $userId, array $termIds): array
    {
        $out = [];
        foreach ($termIds as $termId) {
            $progress = $this->byKey[$this->key($userId->value, $termId)] ?? null;
            if ($progress !== null) {
                $out[$termId] = new DueTermView(
                    $progress->termId(), $progress->state(), $progress->intervalDays(), $progress->dueAt(), $progress->reps(),
                );
            }
        }

        return $out;
    }

    public function save(TermProgress $progress): void
    {
        $this->byKey[$this->key($progress->userId()->value, $progress->termId()->value)] = $progress;
    }

    public function get(UserId $userId, TermId $termId): ?TermProgress
    {
        return $this->findForUpdate($userId, $termId);
    }

    public function count(): int
    {
        return count($this->byKey);
    }

    private function key(string $userId, string $termId): string
    {
        return $userId . '|' . $termId;
    }
}

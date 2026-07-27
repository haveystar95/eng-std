<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Domain\Entity\TermProgress;
use App\Modules\Learning\Domain\Repository\TermProgressRepository;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;

final class InMemoryTermProgressRepository implements TermProgressRepository
{
    /** @var array<string, TermProgress> */
    private array $byKey = [];

    public function findForUpdate(UserId $userId, TermId $termId): ?TermProgress
    {
        return $this->byKey[$this->key($userId->value, $termId->value)] ?? null;
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

<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Domain\Entity\StudySession;
use App\Modules\Learning\Domain\Repository\StudySessionRepository;

final class InMemoryStudySessionRepository implements StudySessionRepository
{
    /** @var array<string, StudySession> */
    private array $byId = [];

    public function save(StudySession $session): void
    {
        $this->byId[$session->id()->value] ??= $session;
    }

    public function count(): int
    {
        return count($this->byId);
    }
}

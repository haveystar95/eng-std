<?php

declare(strict_types=1);

namespace Tests\Doubles;

use App\Modules\Learning\Application\Dto\SessionContext;
use App\Modules\Learning\Application\Port\SessionContextReader;
use App\Modules\Learning\Domain\Entity\StudySession;
use App\Modules\Learning\Domain\Repository\StudySessionRepository;
use App\Modules\Learning\Domain\ValueObject\SessionOutcome;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * Store and reader in one double, so a test can assert what an answer batch ADOPTED, not just what
 * it accepted. `save` mirrors the Eloquent repository's `insertOrIgnore`: a session that already
 * exists is left exactly as it was — which is what makes a second chunk of the same offline
 * practice run safe.
 */
final class InMemoryStudySessions implements SessionContextReader, StudySessionRepository
{
    /** @var array<string, SessionContext> */
    private array $contexts = [];

    /** @var array<string, DateTimeImmutable> */
    private array $startedAt = [];

    /** @param list<string> $termIds */
    public function seed(string $sessionId, string $userId, bool $isPractice, array $termIds = []): void
    {
        $composition = [];
        foreach ($termIds as $termId) {
            $composition[$termId] = true;
        }
        $this->contexts[$sessionId] = new SessionContext($userId, $isPractice, $composition);
    }

    public function save(StudySession $session): void
    {
        $id = $session->id()->value;
        if (isset($this->contexts[$id])) {
            return; // insertOrIgnore
        }
        $composition = [];
        foreach ($session->composition() as $termId) {
            $composition[$termId->value] = true;
        }
        $this->contexts[$id] = new SessionContext(
            $session->userId()->value,
            $session->isPractice(),
            $composition,
        );
        $this->startedAt[$id] = $session->startedAt();
    }

    /**
     * Mirrors the Eloquent repository's conditional update: this user's session, still open, closed
     * exactly once.
     *
     * @var array<string, array{endedAt: DateTimeImmutable, outcome: SessionOutcome}>
     */
    private array $completions = [];

    public function complete(
        StudySessionId $id,
        UserId $userId,
        DateTimeImmutable $endedAt,
        SessionOutcome $outcome,
    ): bool {
        $context = $this->contexts[$id->value] ?? null;
        if ($context === null || $context->userId !== $userId->value || isset($this->completions[$id->value])) {
            return false;
        }
        $this->completions[$id->value] = ['endedAt' => $endedAt, 'outcome' => $outcome];

        return true;
    }

    /** @return array{endedAt: DateTimeImmutable, outcome: SessionOutcome}|null */
    public function completion(string $sessionId): ?array
    {
        return $this->completions[$sessionId] ?? null;
    }

    public function byIds(array $sessionIds): array
    {
        return array_intersect_key($this->contexts, array_flip($sessionIds));
    }

    public function context(string $sessionId): ?SessionContext
    {
        return $this->contexts[$sessionId] ?? null;
    }

    public function startedAt(string $sessionId): ?DateTimeImmutable
    {
        return $this->startedAt[$sessionId] ?? null;
    }

    /** @return list<string> term ids the session was recorded with */
    public function composition(string $sessionId): array
    {
        return array_keys($this->contexts[$sessionId]?->composition ?? []);
    }

    public function count(): int
    {
        return count($this->contexts);
    }

    public function has(string $sessionId): bool
    {
        return isset($this->contexts[$sessionId]);
    }

    /** Convenience for building a composition set from term value objects. */
    public static function ids(TermId ...$terms): array
    {
        return array_map(static fn (TermId $t): string => $t->value, $terms);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Entity;

use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\TermId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * A study run. Reviews reference it for latency/analytics; it never drives scheduling.
 * `collectionId` is optional — a global session mixes collections. Its `composition` is the
 * fixed set of term ids it was built with, so an answer for a term outside it can be rejected.
 */
final class StudySession
{
    /** @param list<TermId> $composition */
    private function __construct(
        private readonly StudySessionId $id,
        private readonly UserId $userId,
        private readonly ?CollectionId $collectionId,
        private readonly bool $isPractice,
        private readonly array $composition,
        private readonly DateTimeImmutable $startedAt,
    ) {}

    /** @param list<TermId> $composition */
    public static function start(
        StudySessionId $id,
        UserId $userId,
        bool $isPractice,
        array $composition,
        DateTimeImmutable $startedAt,
        ?CollectionId $collectionId = null,
    ): self {
        return new self($id, $userId, $collectionId, $isPractice, $composition, $startedAt);
    }

    public function id(): StudySessionId
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function collectionId(): ?CollectionId
    {
        return $this->collectionId;
    }

    public function isPractice(): bool
    {
        return $this->isPractice;
    }

    /** @return list<TermId> */
    public function composition(): array
    {
        return $this->composition;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }
}

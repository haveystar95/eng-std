<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Entity;

use App\Modules\Learning\Domain\ValueObject\StudyMode;
use App\Modules\Learning\Domain\ValueObject\StudySessionId;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * A study run. Reviews reference it for latency/analytics; it never drives scheduling.
 * `collectionId` is optional — a session may mix collections.
 */
final class StudySession
{
    private function __construct(
        private readonly StudySessionId $id,
        private readonly UserId $userId,
        private readonly ?CollectionId $collectionId,
        private readonly StudyMode $mode,
        private readonly DateTimeImmutable $startedAt,
    ) {}

    public static function start(
        StudySessionId $id,
        UserId $userId,
        StudyMode $mode,
        DateTimeImmutable $startedAt,
        ?CollectionId $collectionId = null,
    ): self {
        return new self($id, $userId, $collectionId, $mode, $startedAt);
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

    public function mode(): StudyMode
    {
        return $this->mode;
    }

    public function startedAt(): DateTimeImmutable
    {
        return $this->startedAt;
    }
}

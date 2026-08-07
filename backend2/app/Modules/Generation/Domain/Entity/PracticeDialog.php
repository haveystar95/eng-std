<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Entity;

use App\Modules\Generation\Domain\Exception\InvalidPracticeDialogTransition;
use App\Modules\Generation\Domain\ValueObject\PracticeDialogStatus;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\PracticeDialogId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * One realtime conversation practice over a collection. The aggregate owns the lesson the model
 * was briefed with and the lifecycle (active → finished|expired); the transcript lines and the
 * coverage of target words are append-only facts kept alongside it, not on the aggregate.
 *
 * This is PRACTICE: nothing here ever writes a review or touches (user, term) progress. Its only
 * spend record is the estimated realtime cost, written when the dialog leaves `active`.
 */
final class PracticeDialog
{
    /** @param array<string, mixed> $lesson */
    private function __construct(
        private readonly PracticeDialogId $id,
        private readonly UserId $userId,
        private readonly CollectionId $collectionId,
        private PracticeDialogStatus $status,
        private readonly array $lesson,
        private DateTimeImmutable $expiresAt,
        private ?int $tokensIn,
        private ?int $tokensOut,
        private ?string $costUsd,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $finishedAt,
        private ?string $summary,
    ) {}

    /** @param array<string, mixed> $lesson */
    public static function open(
        PracticeDialogId $id,
        UserId $userId,
        CollectionId $collectionId,
        array $lesson,
        DateTimeImmutable $expiresAt,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            $id, $userId, $collectionId, PracticeDialogStatus::Active, $lesson, $expiresAt,
            null, null, null, $createdAt, null, null,
        );
    }

    /** @param array<string, mixed> $lesson */
    public static function reconstitute(
        PracticeDialogId $id,
        UserId $userId,
        CollectionId $collectionId,
        PracticeDialogStatus $status,
        array $lesson,
        DateTimeImmutable $expiresAt,
        ?int $tokensIn,
        ?int $tokensOut,
        ?string $costUsd,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $finishedAt,
        ?string $summary,
    ): self {
        return new self(
            $id, $userId, $collectionId, $status, $lesson, $expiresAt,
            $tokensIn, $tokensOut, $costUsd, $createdAt, $finishedAt, $summary,
        );
    }

    /** The token TTL has lapsed at $now while the dialog was still active. */
    public function isExpiredAt(DateTimeImmutable $now): bool
    {
        return $this->status === PracticeDialogStatus::Active && $now >= $this->expiresAt;
    }

    /** Seconds of realtime audio to bill up to $upTo, never past the token's expiry. */
    public function billableSeconds(DateTimeImmutable $upTo): int
    {
        $end = $upTo < $this->expiresAt ? $upTo : $this->expiresAt;

        return max(0, $end->getTimestamp() - $this->createdAt->getTimestamp());
    }

    /** A client-id retry re-minted a fresh token for a still-live dialog; adopt its new expiry. */
    public function renew(DateTimeImmutable $expiresAt): void
    {
        if ($this->status !== PracticeDialogStatus::Active) {
            throw InvalidPracticeDialogTransition::from($this->status, PracticeDialogStatus::Active);
        }
        $this->expiresAt = $expiresAt;
    }

    /**
     * Close the dialog with a summary. Reachable from active or expired (the client can ask for a
     * summary after the audio TTL lapsed); a second finish is a no-op that keeps the recorded spend
     * and result.
     */
    public function finish(?int $tokensIn, ?int $tokensOut, ?string $costUsd, DateTimeImmutable $at, ?string $summary): void
    {
        if ($this->status === PracticeDialogStatus::Finished) {
            return;
        }
        $this->status = PracticeDialogStatus::Finished;
        $this->recordUsage($tokensIn, $tokensOut, $costUsd);
        $this->finishedAt = $at;
        $this->summary = $summary;
    }

    /** Retire an active dialog whose token TTL lapsed without an explicit finish (no summary). */
    public function expire(?int $tokensIn, ?int $tokensOut, ?string $costUsd, DateTimeImmutable $at): void
    {
        if ($this->status !== PracticeDialogStatus::Active) {
            throw InvalidPracticeDialogTransition::from($this->status, PracticeDialogStatus::Expired);
        }
        $this->status = PracticeDialogStatus::Expired;
        $this->recordUsage($tokensIn, $tokensOut, $costUsd);
        $this->finishedAt = $at;
    }

    private function recordUsage(?int $tokensIn, ?int $tokensOut, ?string $costUsd): void
    {
        $this->tokensIn = $tokensIn;
        $this->tokensOut = $tokensOut;
        $this->costUsd = $costUsd;
    }

    public function id(): PracticeDialogId
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function collectionId(): CollectionId
    {
        return $this->collectionId;
    }

    public function status(): PracticeDialogStatus
    {
        return $this->status;
    }

    /** @return array<string, mixed> */
    public function lesson(): array
    {
        return $this->lesson;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function tokensIn(): ?int
    {
        return $this->tokensIn;
    }

    public function tokensOut(): ?int
    {
        return $this->tokensOut;
    }

    public function costUsd(): ?string
    {
        return $this->costUsd;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function finishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function summary(): ?string
    {
        return $this->summary;
    }
}

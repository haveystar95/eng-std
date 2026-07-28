<?php

declare(strict_types=1);

namespace App\Modules\Generation\Domain\Entity;

use App\Modules\Generation\Domain\Exception\InvalidGenerationTransition;
use App\Modules\Generation\Domain\ValueObject\GenerationStatus;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\GenerationRequestId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * One AI generation, tracked from request to result. The aggregate guards the lifecycle
 * (pending → running → succeeded|failed) so a job can't, say, mark a failed request as
 * succeeded. Cost/usage are recorded here for the per-user spend read model.
 */
final class GenerationRequest
{
    /** @param list<string> $levels */
    private function __construct(
        private readonly GenerationRequestId $id,
        private readonly UserId $userId,
        private readonly string $prompt,
        private readonly string $normalizedPrompt,
        private readonly LanguageCode $sourceLang,
        private readonly LanguageCode $targetLang,
        private readonly array $levels,
        private readonly int $size,
        private readonly string $promptVersion,
        private GenerationStatus $status,
        private ?string $model,
        private ?int $tokensIn,
        private ?int $tokensOut,
        private ?string $costUsd,
        private ?CollectionId $collectionId,
        private ?string $error,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $finishedAt,
    ) {}

    /** @param list<string> $levels */
    public static function open(
        GenerationRequestId $id,
        UserId $userId,
        string $prompt,
        string $normalizedPrompt,
        LanguageCode $sourceLang,
        LanguageCode $targetLang,
        array $levels,
        int $size,
        string $promptVersion,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            $id, $userId, $prompt, $normalizedPrompt, $sourceLang, $targetLang, $levels, $size,
            $promptVersion, GenerationStatus::Pending, null, null, null, null, null, null, $createdAt, null,
        );
    }

    /**
     * Rebuild from persistence.
     *
     * @param list<string> $levels
     */
    public static function reconstitute(
        GenerationRequestId $id,
        UserId $userId,
        string $prompt,
        string $normalizedPrompt,
        LanguageCode $sourceLang,
        LanguageCode $targetLang,
        array $levels,
        int $size,
        string $promptVersion,
        GenerationStatus $status,
        ?string $model,
        ?int $tokensIn,
        ?int $tokensOut,
        ?string $costUsd,
        ?CollectionId $collectionId,
        ?string $error,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $finishedAt,
    ): self {
        return new self(
            $id, $userId, $prompt, $normalizedPrompt, $sourceLang, $targetLang, $levels, $size,
            $promptVersion, $status, $model, $tokensIn, $tokensOut, $costUsd, $collectionId, $error,
            $createdAt, $finishedAt,
        );
    }

    public function markRunning(): void
    {
        if ($this->status !== GenerationStatus::Pending) {
            throw InvalidGenerationTransition::from($this->status, GenerationStatus::Running);
        }
        $this->status = GenerationStatus::Running;
    }

    public function markSucceeded(
        CollectionId $collectionId,
        string $model,
        ?int $tokensIn,
        ?int $tokensOut,
        ?string $costUsd,
        DateTimeImmutable $finishedAt,
    ): void {
        if ($this->status->isTerminal()) {
            throw InvalidGenerationTransition::from($this->status, GenerationStatus::Succeeded);
        }
        $this->status = GenerationStatus::Succeeded;
        $this->collectionId = $collectionId;
        $this->model = $model;
        $this->tokensIn = $tokensIn;
        $this->tokensOut = $tokensOut;
        $this->costUsd = $costUsd;
        $this->error = null;
        $this->finishedAt = $finishedAt;
    }

    public function markFailed(string $reason, DateTimeImmutable $finishedAt): void
    {
        if ($this->status === GenerationStatus::Succeeded) {
            throw InvalidGenerationTransition::from($this->status, GenerationStatus::Failed);
        }
        $this->status = GenerationStatus::Failed;
        $this->error = $reason;
        $this->finishedAt = $finishedAt;
    }

    public function id(): GenerationRequestId
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function prompt(): string
    {
        return $this->prompt;
    }

    public function normalizedPrompt(): string
    {
        return $this->normalizedPrompt;
    }

    public function sourceLang(): LanguageCode
    {
        return $this->sourceLang;
    }

    public function targetLang(): LanguageCode
    {
        return $this->targetLang;
    }

    /** @return list<string> */
    public function levels(): array
    {
        return $this->levels;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function promptVersion(): string
    {
        return $this->promptVersion;
    }

    public function status(): GenerationStatus
    {
        return $this->status;
    }

    public function model(): ?string
    {
        return $this->model;
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

    public function collectionId(): ?CollectionId
    {
        return $this->collectionId;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function finishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }
}

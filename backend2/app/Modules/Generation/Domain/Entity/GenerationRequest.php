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
        private ?int $deliveredCount,
        private readonly string $promptVersion,
        private GenerationStatus $status,
        private ?string $model,
        private ?int $tokensIn,
        private ?int $tokensOut,
        private ?string $costUsd,
        private ?CollectionId $collectionId,
        private ?string $error,
        private ?string $rawResponse,
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
            null, $promptVersion, GenerationStatus::Pending, null, null, null, null, null, null, null, $createdAt, null,
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
        ?int $deliveredCount,
        string $promptVersion,
        GenerationStatus $status,
        ?string $model,
        ?int $tokensIn,
        ?int $tokensOut,
        ?string $costUsd,
        ?CollectionId $collectionId,
        ?string $error,
        ?string $rawResponse,
        DateTimeImmutable $createdAt,
        ?DateTimeImmutable $finishedAt,
    ): self {
        return new self(
            $id, $userId, $prompt, $normalizedPrompt, $sourceLang, $targetLang, $levels, $size,
            $deliveredCount, $promptVersion, $status, $model, $tokensIn, $tokensOut, $costUsd, $collectionId, $error,
            $rawResponse, $createdAt, $finishedAt,
        );
    }

    /**
     * Start (or RE-start) the run. Idempotent from `running`, refused from a terminal state.
     *
     * The re-entry is the point, and it was found by paying for it. `GenerateCollectionJob` has
     * `tries = 3`: attempt 1 marks the request running and then dies of something else, attempt 2
     * calls this method on a request that is already running, and — while this threw — the domain
     * exception REPLACED the real cause. All three attempts then ended the same way, and what
     * `failed()` recorded was «Cannot move a generation from running to running», an artefact of the
     * state machine describing nothing about why the generation actually failed. Three of the
     * owner's own generations died that way on 25.08, at $0.043 each, and the reason for all three
     * is not recoverable from anything we stored.
     *
     * A retry re-entering its own run is not an illegal transition — it is the same job saying «I am
     * running» a second time, which is true. So it is a no-op, and whatever really goes wrong on the
     * retry is what reaches `markFailed()`.
     *
     * A TERMINAL state is still refused, and that is a different question: succeeded and failed are
     * final (see {@see GenerationStatus}), the quota has been settled and a collection may already
     * exist, so re-running one would be a second charge for work that is over. The handler returns
     * early on terminal anyway; this stays as the domain's own guarantee rather than a courtesy the
     * caller extends.
     */
    public function markRunning(): void
    {
        if ($this->status === GenerationStatus::Running) {
            return;
        }
        if ($this->status !== GenerationStatus::Pending) {
            throw InvalidGenerationTransition::from($this->status, GenerationStatus::Running);
        }
        $this->status = GenerationStatus::Running;
    }

    /**
     * Persist model usage + the raw response the moment the model answers, before validation runs.
     * Kept separate from the status transitions so a request that later fails validation still
     * records what the model cost and returned — the spend must not vanish because the draft was
     * rejected. Only meaningful while running.
     */
    public function recordAttempt(
        string $model,
        ?int $tokensIn,
        ?int $tokensOut,
        ?string $costUsd,
        ?string $rawResponse,
    ): void {
        $this->model = $model;
        $this->tokensIn = $tokensIn;
        $this->tokensOut = $tokensOut;
        $this->costUsd = $costUsd;
        $this->rawResponse = $rawResponse;
    }

    public function markSucceeded(
        CollectionId $collectionId,
        string $model,
        ?int $tokensIn,
        ?int $tokensOut,
        ?string $costUsd,
        int $deliveredCount,
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
        $this->deliveredCount = $deliveredCount;
        $this->error = null;
        $this->finishedAt = $finishedAt;
    }

    /**
     * Why THIS attempt died, recorded while the request is still running and the queue still has
     * retries left. The status does not move: the run is not over until the tries are.
     *
     * The other half of the retry story above. Making `markRunning()` idempotent stops the state
     * machine from inventing a reason, but it does not preserve the real one: `failed()` fires only
     * after the LAST attempt, so what it records is whatever the last attempt tripped over — a
     * timeout on a call whose first attempt died of a rejected draft, say. The cause worth keeping is
     * the FIRST one, because it is the one that has not yet been contaminated by a half-finished run.
     *
     * So the first attempt writes its cause here and every later one is a no-op, and
     * {@see markFailed()} then keeps what is already recorded instead of overwriting it. A retry that
     * SUCCEEDS clears the note ({@see markSucceeded()} nulls `error`) — a request that finished well
     * carries no error, whatever happened on the way.
     */
    public function noteAttemptFailure(string $reason): void
    {
        if ($this->status !== GenerationStatus::Running || $this->error !== null || trim($reason) === '') {
            return;
        }
        $this->error = trim($reason);
    }

    /**
     * @param  string  $reason  used only when no attempt has already recorded one — see
     *         {@see noteAttemptFailure()}. The first cause is the one worth reading.
     */
    public function markFailed(string $reason, DateTimeImmutable $finishedAt): void
    {
        if ($this->status === GenerationStatus::Succeeded) {
            throw InvalidGenerationTransition::from($this->status, GenerationStatus::Failed);
        }
        $this->status = GenerationStatus::Failed;
        $this->error ??= $reason;
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

    /** How many items actually landed in the collection; null until the request succeeds. */
    public function deliveredCount(): ?int
    {
        return $this->deliveredCount;
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

    public function rawResponse(): ?string
    {
        return $this->rawResponse;
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

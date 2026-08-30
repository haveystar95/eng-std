<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Entity;

use App\Modules\Learning\Domain\Exception\InvalidPlanTransition;
use App\Modules\Learning\Domain\ValueObject\PlanDayId;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use DateTimeImmutable;

/**
 * One day of a plan, and the small state machine that keeps a paid model call from being made
 * twice.
 *
 * ## Two attempts, then stop
 *
 * `generationAttempts` counts CLAIMS, not failures, and the cap is two. The reason it is a stored
 * counter and not a queue `tries` setting: the queue retries a job that CRASHED, which is a
 * different event from a day whose material came back and did not pass the validator. The second
 * costs the same money as the first and will keep costing it, so it stops after one re-run and says
 * why in `failReason`. A day that failed twice is a thing a person looks at.
 *
 * ## Idempotent by (plan, day)
 *
 * {@see claim()} refuses a day that is already `generating` or already finished, and the repository
 * claims it inside a locked read. Two workers handed the same day — a replayed dispatch, a restart
 * mid-job — means one of them pays and the other returns.
 */
final class PlanDay
{
    public const MAX_ATTEMPTS = 2;

    private function __construct(
        private readonly PlanDayId $id,
        private readonly PlanId $planId,
        private readonly int $dayIndex,
        private readonly PlanDayKind $kind,
        private ?CollectionId $collectionId,
        private readonly string $title,
        private readonly ?string $outcomeText,
        /** @var list<array<string, mixed>> */
        private readonly array $skills,
        /** @var array<string, mixed>|null */
        private readonly ?array $roleBrief,
        private readonly ?DateTimeImmutable $scheduledOn,
        private PlanDayStatus $status,
        private int $generationAttempts,
        private ?string $failReason,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $skills
     * @param  array<string, mixed>|null  $roleBrief
     */
    public static function plan(
        PlanDayId $id,
        PlanId $planId,
        int $dayIndex,
        PlanDayKind $kind,
        string $title,
        ?string $outcomeText,
        array $skills,
        ?array $roleBrief,
        ?DateTimeImmutable $scheduledOn,
    ): self {
        return new self(
            $id, $planId, $dayIndex, $kind, null, $title, $outcomeText, $skills, $roleBrief,
            $scheduledOn, PlanDayStatus::Pending, 0, null,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $skills
     * @param  array<string, mixed>|null  $roleBrief
     */
    public static function reconstitute(
        PlanDayId $id,
        PlanId $planId,
        int $dayIndex,
        PlanDayKind $kind,
        ?CollectionId $collectionId,
        string $title,
        ?string $outcomeText,
        array $skills,
        ?array $roleBrief,
        ?DateTimeImmutable $scheduledOn,
        PlanDayStatus $status,
        int $generationAttempts,
        ?string $failReason,
    ): self {
        return new self(
            $id, $planId, $dayIndex, $kind, $collectionId, $title, $outcomeText, $skills,
            $roleBrief, $scheduledOn, $status, $generationAttempts, $failReason,
        );
    }

    /**
     * Take this day for generation. Returns false when somebody else already has it or it is done —
     * the caller returns rather than paying for a second copy.
     */
    public function claim(): bool
    {
        if ($this->kind === PlanDayKind::Final) {
            // The final day introduces nothing. There is no call to make and no collection to fill.
            return false;
        }
        if ($this->status !== PlanDayStatus::Pending && $this->status !== PlanDayStatus::Failed) {
            return false;
        }
        if ($this->generationAttempts >= self::MAX_ATTEMPTS) {
            return false;
        }

        $this->status = PlanDayStatus::Generating;
        $this->generationAttempts++;
        $this->failReason = null;

        return true;
    }

    public function markReady(CollectionId $collectionId): void
    {
        if ($this->status !== PlanDayStatus::Generating) {
            throw InvalidPlanTransition::forDay($this->status, 'объявить день готовым');
        }

        $this->collectionId = $collectionId;
        $this->status = PlanDayStatus::Ready;
        $this->failReason = null;
    }

    /**
     * The attempt did not work.
     *
     * Back to `pending` while there is an attempt left, so the next dispatch picks it up; `failed`
     * once both are spent, with the reason kept. The reason is trimmed to what a column and a human
     * can use — a stack trace in this field is a field nobody reads.
     */
    public function markFailed(string $reason): void
    {
        $this->failReason = mb_substr(trim($reason), 0, 500);
        $this->status = $this->generationAttempts >= self::MAX_ATTEMPTS
            ? PlanDayStatus::Failed
            : PlanDayStatus::Pending;
    }

    public function markDone(): void
    {
        $this->status = PlanDayStatus::Done;
    }

    public function isReady(): bool
    {
        return $this->status === PlanDayStatus::Ready || $this->status === PlanDayStatus::Done;
    }

    public function id(): PlanDayId
    {
        return $this->id;
    }

    public function planId(): PlanId
    {
        return $this->planId;
    }

    public function dayIndex(): int
    {
        return $this->dayIndex;
    }

    public function kind(): PlanDayKind
    {
        return $this->kind;
    }

    public function collectionId(): ?CollectionId
    {
        return $this->collectionId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function outcomeText(): ?string
    {
        return $this->outcomeText;
    }

    /** @return list<array<string, mixed>> */
    public function skills(): array
    {
        return $this->skills;
    }

    /** @return array<string, mixed>|null */
    public function roleBrief(): ?array
    {
        return $this->roleBrief;
    }

    public function scheduledOn(): ?DateTimeImmutable
    {
        return $this->scheduledOn;
    }

    public function status(): PlanDayStatus
    {
        return $this->status;
    }

    public function generationAttempts(): int
    {
        return $this->generationAttempts;
    }

    public function failReason(): ?string
    {
        return $this->failReason;
    }
}

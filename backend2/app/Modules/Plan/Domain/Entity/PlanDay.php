<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Entity;

use App\Modules\Plan\Domain\ValueObject\DayMetrics;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use DateTimeImmutable;

/** One calendar day of the plan: what it is, whether it may be walked, and how it went. */
final class PlanDay
{
    private function __construct(
        private readonly PlanDayId $id,
        private readonly PlanId $planId,
        private readonly int $number,
        private DayType $type,
        private ?PlanSceneId $sceneId,
        private DayStatus $status,
        private ?DateTimeImmutable $opensOn,
        private ?DateTimeImmutable $openedAt,
        private ?DateTimeImmutable $closedAt,
        private DayMetrics $metrics,
    ) {}

    public static function planned(PlanDayId $id, PlanId $planId, int $number, DayType $type): self
    {
        return new self($id, $planId, $number, $type, null, DayStatus::Locked, null, null, null, DayMetrics::empty());
    }

    public static function reconstitute(
        PlanDayId $id,
        PlanId $planId,
        int $number,
        DayType $type,
        ?PlanSceneId $sceneId,
        DayStatus $status,
        ?DateTimeImmutable $opensOn,
        ?DateTimeImmutable $openedAt,
        ?DateTimeImmutable $closedAt,
        DayMetrics $metrics,
    ): self {
        return new self($id, $planId, $number, $type, $sceneId, $status, $opensOn, $openedAt, $closedAt, $metrics);
    }

    public function assignScene(PlanSceneId $sceneId): void
    {
        $this->type = DayType::Scene;
        $this->sceneId = $sceneId;
    }

    /** A scene removed in the preview leaves a review day behind, so the calendar keeps its length. */
    public function becomeReview(): void
    {
        $this->type = DayType::Review;
        $this->sceneId = null;
    }

    public function retype(DayType $type): void
    {
        $this->type = $type;
        if ($type !== DayType::Scene) {
            $this->sceneId = null;
        }
    }

    /** A scene day with no scene yet — waiting to be dealt one, or for an extension to write one. */
    public function clearScene(): void
    {
        $this->sceneId = null;
    }

    /** The day may be started from this calendar date on (the learner's own calendar). */
    public function unlockOn(DateTimeImmutable $date): void
    {
        $this->opensOn = $date->setTime(0, 0);
        $this->status = DayStatus::Locked;
    }

    public function open(): void
    {
        $this->status = DayStatus::Open;
    }

    /** Is the day available today — open already, or locked only by a date that has come? */
    public function isAvailableOn(DateTimeImmutable $today): bool
    {
        if ($this->status === DayStatus::Open || $this->status === DayStatus::InProgress) {
            return true;
        }

        // Calendar dates, compared as dates: `opensOn` was written in one zone and `today` comes
        // in the learner's, and an instant comparison between them would move midnight.
        return $this->status === DayStatus::Locked
            && $this->opensOn !== null
            && $this->opensOn->format('Y-m-d') <= $today->format('Y-m-d');
    }

    public function start(DateTimeImmutable $now): void
    {
        $this->status = DayStatus::InProgress;
        $this->openedAt ??= $now;
    }

    public function close(DateTimeImmutable $now, DayMetrics $metrics): void
    {
        $this->status = DayStatus::Closed;
        $this->closedAt = $now;
        $this->metrics = $metrics;
    }

    public function updateMetrics(DayMetrics $metrics): void
    {
        $this->metrics = $metrics;
    }

    public function id(): PlanDayId
    {
        return $this->id;
    }

    public function planId(): PlanId
    {
        return $this->planId;
    }

    public function number(): int
    {
        return $this->number;
    }

    public function type(): DayType
    {
        return $this->type;
    }

    public function sceneId(): ?PlanSceneId
    {
        return $this->sceneId;
    }

    public function status(): DayStatus
    {
        return $this->status;
    }

    public function isClosed(): bool
    {
        return $this->status === DayStatus::Closed;
    }

    public function isTouched(): bool
    {
        return $this->status !== DayStatus::Locked;
    }

    public function opensOn(): ?DateTimeImmutable
    {
        return $this->opensOn;
    }

    public function openedAt(): ?DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function closedAt(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function metrics(): DayMetrics
    {
        return $this->metrics;
    }
}

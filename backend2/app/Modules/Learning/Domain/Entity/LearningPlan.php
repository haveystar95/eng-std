<?php

declare(strict_types=1);

namespace App\Modules\Learning\Domain\Entity;

use App\Modules\Learning\Domain\Exception\InvalidPlanTransition;
use App\Modules\Learning\Domain\ValueObject\PlanId;
use App\Modules\Learning\Domain\ValueObject\PlanLevel;
use App\Modules\Learning\Domain\ValueObject\PlanOutline;
use App\Modules\Learning\Domain\ValueObject\PlanStatus;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * ONE learner's plan for ONE event.
 *
 * Mutable where {@see \App\Modules\Learning\Domain\Entity\TermProgress} is immutable, and the
 * difference is what each one is: progress is a projection folded from an append-only log, so
 * every step has to yield a fresh value; a plan is a small, long-lived aggregate a person edits
 * three or four times in its life. Copying it on every touch would buy nothing.
 *
 * The two JSON columns hold two different kinds of truth and are never merged:
 *
 *   outline   the MODEL's answer, verbatim. Replaced whole by a re-run, never edited in place, so
 *             what is stored is always exactly one model answer and «what did it actually say» has
 *             an answer.
 *   computed  the SERVER's arithmetic over that answer. Recomputed whenever the calendar or the
 *             minutes change, with no model call.
 */
final class LearningPlan
{
    private function __construct(
        private readonly PlanId $id,
        private readonly UserId $userId,
        private PlanStatus $status,
        private string $title,
        private readonly string $goalText,
        private ?string $goalRestated,
        private readonly LanguageCode $targetLang,
        private PlanLevel $level,
        private DateTimeImmutable $eventDate,
        private int $minutesPerDay,
        /** @var array<string, mixed>|null */
        private ?array $outline,
        /** @var array<string, mixed>|null */
        private ?array $computed,
        private ?DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $completedAt,
    ) {}

    public static function draft(
        PlanId $id,
        UserId $userId,
        string $title,
        string $goalText,
        LanguageCode $targetLang,
        PlanLevel $level,
        DateTimeImmutable $eventDate,
        int $minutesPerDay,
    ): self {
        return new self(
            $id, $userId, PlanStatus::Draft, $title, $goalText, null, $targetLang, $level,
            $eventDate, $minutesPerDay, null, null, null, null,
        );
    }

    /**
     * @param  array<string, mixed>  $outline   P1's answer
     * @param  array<string, mixed>  $computed  A1's arithmetic
     */
    public static function reconstitute(
        PlanId $id,
        UserId $userId,
        PlanStatus $status,
        string $title,
        string $goalText,
        ?string $goalRestated,
        LanguageCode $targetLang,
        PlanLevel $level,
        DateTimeImmutable $eventDate,
        int $minutesPerDay,
        ?array $outline,
        ?array $computed,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $completedAt,
    ): self {
        return new self(
            $id, $userId, $status, $title, $goalText, $goalRestated, $targetLang, $level,
            $eventDate, $minutesPerDay, $outline, $computed, $startedAt, $completedAt,
        );
    }

    /**
     * A fresh outline lands, and the plan takes its title and its restatement from it.
     *
     * Allowed on a DRAFT only. Re-outlining a running plan would swap the material under a learner
     * who is halfway through it — the days would be rebuilt, the collections orphaned and the
     * enrolled words held by a plan that no longer promises what they were for. The way to change
     * a running plan is to abandon it and build another; that is a decision, and it is theirs.
     *
     * @param  array<string, mixed>  $outline
     * @param  array<string, mixed>  $computed
     */
    public function applyOutline(array $outline, array $computed, PlanOutline $parsed): void
    {
        if ($this->status !== PlanStatus::Draft) {
            throw InvalidPlanTransition::make($this->status, 'пересобрать каркас');
        }

        $this->outline = $outline;
        $this->computed = $computed;
        if ($parsed->title !== '') {
            $this->title = $parsed->title;
        }
        if ($parsed->goalRestated !== '') {
            $this->goalRestated = $parsed->goalRestated;
        }
    }

    /**
     * The calendar or the minutes changed: new arithmetic, same model answer.
     *
     * This is the whole point of keeping `outline` and `computed` apart — the learner moving
     * «40 минут» to «20 минут» must not cost a model call, and it does not.
     *
     * @param  array<string, mixed>  $computed
     */
    public function reschedule(array $computed, int $minutesPerDay, DateTimeImmutable $eventDate): void
    {
        if ($this->status !== PlanStatus::Draft) {
            throw InvalidPlanTransition::make($this->status, 'пересчитать расписание');
        }

        $this->computed = $computed;
        $this->minutesPerDay = $minutesPerDay;
        $this->eventDate = $eventDate;
    }

    public function start(DateTimeImmutable $now): void
    {
        if ($this->status !== PlanStatus::Draft && $this->status !== PlanStatus::Paused) {
            throw InvalidPlanTransition::make($this->status, 'начать');
        }
        if ($this->outline === null || $this->computed === null) {
            throw InvalidPlanTransition::make($this->status, 'начать план без каркаса');
        }

        // A plan resumed from a pause keeps the moment it FIRST started: «с какого дня я это учу»
        // is not rewritten by putting the plan down for a week.
        $this->startedAt ??= $now;
        $this->status = PlanStatus::Active;
    }

    public function pause(): void
    {
        if ($this->status !== PlanStatus::Active) {
            throw InvalidPlanTransition::make($this->status, 'поставить на паузу');
        }

        $this->status = PlanStatus::Paused;
    }

    /**
     * Give up on it. Terminal, and it RELEASES the plan's hold on the pool — see
     * {@see \App\Modules\Learning\Domain\Service\EnrollmentPolicy::release()}. The words stay;
     * only the plan's claim on them goes.
     */
    public function abandon(): void
    {
        if ($this->status->isTerminal()) {
            throw InvalidPlanTransition::make($this->status, 'отказаться');
        }

        $this->status = PlanStatus::Abandoned;
    }

    public function complete(DateTimeImmutable $now): void
    {
        if ($this->status !== PlanStatus::Active) {
            throw InvalidPlanTransition::make($this->status, 'завершить');
        }

        $this->status = PlanStatus::Completed;
        $this->completedAt = $now;
    }

    /**
     * The stored outline, parsed.
     *
     * @throws \App\Modules\Learning\Domain\Exception\InvalidPlanOutline
     */
    public function parsedOutline(): ?PlanOutline
    {
        return $this->outline === null ? null : PlanOutline::fromArray($this->outline);
    }

    public function id(): PlanId
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function status(): PlanStatus
    {
        return $this->status;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function goalText(): string
    {
        return $this->goalText;
    }

    public function goalRestated(): ?string
    {
        return $this->goalRestated;
    }

    public function targetLang(): LanguageCode
    {
        return $this->targetLang;
    }

    public function level(): PlanLevel
    {
        return $this->level;
    }

    public function eventDate(): DateTimeImmutable
    {
        return $this->eventDate;
    }

    public function minutesPerDay(): int
    {
        return $this->minutesPerDay;
    }

    /** @return array<string, mixed>|null */
    public function outline(): ?array
    {
        return $this->outline;
    }

    /** @return array<string, mixed>|null */
    public function computed(): ?array
    {
        return $this->computed;
    }

    public function startedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }
}

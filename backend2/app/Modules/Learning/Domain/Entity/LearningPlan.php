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
        /**
         * The learner's own language, AS THIS PLAN WAS WRITTEN IN IT. Not read from the account at
         * use time: a plan runs for days, its skeleton, its keys and its grading are all in this
         * language, and the account is a setting a person can change on Tuesday. Fluid while the
         * plan is a draft, frozen by {@see start()}.
         */
        private LanguageCode $supportLang,
        private PlanLevel $level,
        private DateTimeImmutable $eventDate,
        private int $minutesPerDay,
        /** @var array<string, mixed>|null */
        private ?array $outline,
        /** @var array<string, mixed>|null */
        private ?array $computed,
        private ?DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $completedAt,
        /**
         * WHICH ABILITIES THE LEARNER ACTUALLY USED AT THE EVENT — their own report, ticked by hand
         * (кадр 1c · 14), and the one fact about a plan that cannot be derived from anything.
         *
         * Everything else the plan knows comes from the review log or from the conversations. This
         * happened in a room the app was not in, so the only honest source is the person who was
         * there. It is what turns «готовность 50%» into «на приёме сказал 5 из 6».
         *
         * A list of INDEXES into the plan's checkpoint list, not a list of texts: the checkpoints
         * live in the outline and cannot move (re-outlining a running plan is refused), so an index
         * cannot come to mean a different sentence — while a stored copy of the sentence could
         * drift from the one on screen.
         *
         * NULL means «never asked»; an empty list means «asked, and none were used». Two different
         * facts, and collapsing them would make «0 из 6» unprintable.
         *
         * @var list<int>|null
         */
        private ?array $eventFeedback = null,
        /**
         * WHY the plan was abandoned, when it was not the learner who abandoned it.
         *
         * Null on «я передумал» — a person tapping «отказаться» has their reason and it is not the
         * app's business. It carries a value only when something ELSE ends the plan on the owner's
         * behalf: a prompt version whose skeletons can no longer be read, a run that left a plan
         * broken. A plan that says «abandoned» and nothing beside it is a plan the owner reopens a
         * month later and cannot explain.
         *
         * Set only by {@see abandon()}, and only forward: the two other endings never touch it.
         */
        private ?string $abandonReason = null,
    ) {}

    public static function draft(
        PlanId $id,
        UserId $userId,
        string $title,
        string $goalText,
        LanguageCode $targetLang,
        LanguageCode $supportLang,
        PlanLevel $level,
        DateTimeImmutable $eventDate,
        int $minutesPerDay,
    ): self {
        return new self(
            $id, $userId, PlanStatus::Draft, $title, $goalText, null, $targetLang, $supportLang,
            $level, $eventDate, $minutesPerDay, null, null, null, null,
        );
    }

    /**
     * @param  array<string, mixed>  $outline   P1's answer
     * @param  array<string, mixed>  $computed  A1's arithmetic
     * @param  list<int>|null  $eventFeedback   what the learner said they used at the event
     */
    public static function reconstitute(
        PlanId $id,
        UserId $userId,
        PlanStatus $status,
        string $title,
        string $goalText,
        ?string $goalRestated,
        LanguageCode $targetLang,
        LanguageCode $supportLang,
        PlanLevel $level,
        DateTimeImmutable $eventDate,
        int $minutesPerDay,
        ?array $outline,
        ?array $computed,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $completedAt,
        ?array $eventFeedback = null,
        ?string $abandonReason = null,
    ): self {
        return new self(
            $id, $userId, $status, $title, $goalText, $goalRestated, $targetLang, $supportLang,
            $level, $eventDate, $minutesPerDay, $outline, $computed, $startedAt, $completedAt,
            $eventFeedback, $abandonReason,
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

        // From here the support language is FROZEN. Nothing rewrites it: the skeleton is already
        // written in it, day 1 is about to be, and the keys the learner will be graded on are its
        // sentences. There is deliberately no method that can change it afterwards — the freeze is
        // the absence of a setter, not a flag somebody has to remember to check.
    }

    /**
     * Re-read the learner's language onto a DRAFT.
     *
     * Only reachable while nothing has been generated. The outline is what gets written in this
     * language, so the last word before commitment is the right one; after `start` there is no
     * path here at all.
     */
    public function refreshSupportLang(LanguageCode $supportLang): void
    {
        if ($this->status !== PlanStatus::Draft) {
            throw InvalidPlanTransition::make($this->status, 'сменить язык поддержки');
        }

        $this->supportLang = $supportLang;
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
     *
     * `$reason` is for the endings the learner did not choose ({@see $abandonReason}); their own
     * «отказаться» passes nothing. It is a short machine tag («prompt_v0_2_1_run»), not a sentence
     * to show — the column is 64 characters and what it answers is «which decision killed this»,
     * not «what happened».
     */
    public function abandon(?string $reason = null): void
    {
        if ($this->status->isTerminal()) {
            throw InvalidPlanTransition::make($this->status, 'отказаться');
        }

        $this->status = PlanStatus::Abandoned;
        $this->abandonReason = $reason;
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
     * «Как прошло?» — the learner's own report, and the act that CLOSES the plan.
     *
     * The two happen together on purpose: answering the question is the last thing the plan asks,
     * and a plan that recorded the answer and stayed active would keep holding words for an event
     * that has already happened. A plan already completed (a second tap on the notification, an
     * offline retry) simply has its report replaced — the answer is a fact about the event, not a
     * log of attempts to state it.
     *
     * @param  list<int>  $checkpointIndexes  positions in the plan's checkpoint list
     */
    public function recordEventFeedback(array $checkpointIndexes, DateTimeImmutable $now): void
    {
        // Deduplicated and ordered here rather than at the edge: two different indexes are two
        // different abilities, and the same one twice is one ability said twice.
        $unique = array_values(array_unique(array_map(intval(...), $checkpointIndexes)));
        sort($unique);
        $this->eventFeedback = $unique;

        if ($this->status === PlanStatus::Active) {
            $this->complete($now);
        }
    }

    /** @return list<int>|null */
    public function eventFeedback(): ?array
    {
        return $this->eventFeedback;
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

    /** {@see $abandonReason} — null unless something other than the learner ended this plan. */
    public function abandonReason(): ?string
    {
        return $this->abandonReason;
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

    public function supportLang(): LanguageCode
    {
        return $this->supportLang;
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

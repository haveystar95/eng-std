<?php

declare(strict_types=1);

namespace App\Modules\Plan\Domain\Entity;

use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Blueprint\PlanTitles;
use App\Modules\Plan\Domain\Blueprint\SceneBrief;
use App\Modules\Plan\Domain\Exception\CoreSceneNotRemovable;
use App\Modules\Plan\Domain\Exception\PlanDayBuilding;
use App\Modules\Plan\Domain\Exception\PlanDayLocked;
use App\Modules\Plan\Domain\Exception\PlanDayNotFound;
use App\Modules\Plan\Domain\Exception\PlanDayNotOpen;
use App\Modules\Plan\Domain\Exception\PlanNotInState;
use App\Modules\Plan\Domain\Exception\PlanTooShort;
use App\Modules\Plan\Domain\Exception\SceneNotFound;
use App\Modules\Plan\Domain\Lesson\EarlierDay;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonRoles;
use App\Modules\Plan\Domain\Service\PlanCalendar;
use App\Modules\Plan\Domain\ValueObject\DayMetrics;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\LockReason;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use App\Modules\Plan\Domain\ValueObject\Paywall;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use App\Modules\Plan\Domain\ValueObject\SceneKind;
use App\Modules\Shared\Domain\ValueObject\CollectionId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use DateTimeImmutable;

/**
 * THE PLAN — a preparation for one event over 1–10 days (`docs/plan-v2.md`).
 *
 * The aggregate holds the calendar (days), the scenes the model wrote, and the status. Every rule
 * about what may happen to a plan lives here: when a day opens, what removing a scene does, how a
 * reschedule shortens or extends, which scene is never dropped. Cards and terms are their own
 * aggregates (one day's cards are written in one go and answered one at a time).
 */
final class Plan
{
    /**
     * @param  list<PlanScene>  $scenes
     * @param  list<PlanDay>  $days
     * @param  list<array{check: string, mode: string, action: string, detail: string}>  $findings
     * @param  array<string, int>|null  $pace  the price list of the plan's days — seconds per card by kind, a snapshot of
     *                                         `plan.pace` taken when the plan was made (наряд FIX-3 §2); null — none taken yet
     */
    private function __construct(
        private readonly PlanId $id,
        private readonly UserId $userId,
        private readonly string $goalText,
        private readonly LanguageCode $targetLang,
        private readonly LanguageCode $nativeLang,
        private readonly PlanLevel $level,
        private int $daysTotal,
        private int $daysRequested,
        private ?DateTimeImmutable $eventDate,
        private PlanStatus $status,
        private ?PlanTitles $titles,
        private ?Image $coverImage,
        private ?ModelCall $planCall,
        private array $findings,
        private ?string $unclearReason,
        private ?string $failReason,
        private ?DateTimeImmutable $buildStartedAt,
        private ?CollectionId $collectionId,
        private ?DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $finishedAt,
        private readonly DateTimeImmutable $createdAt,
        private array $scenes,
        private array $days,
        private ?array $pace = null,
    ) {}

    /**
     * A new plan: the days laid out by the calendar (shortened when the event is nearer than the
     * learner asked for), no scenes yet, and the model about to be asked — with the price list of its days as it
     * stands today (`$pace`, наряд FIX-3 §2).
     *
     * @param  callable(): PlanDayId  $dayIds
     * @param  array<string, int>|null  $pace
     */
    public static function create(
        PlanId $id,
        UserId $userId,
        string $goalText,
        LanguageCode $targetLang,
        LanguageCode $nativeLang,
        PlanLevel $level,
        int $daysRequested,
        ?DateTimeImmutable $eventDate,
        DateTimeImmutable $today,
        DateTimeImmutable $now,
        callable $dayIds,
        ?array $pace = null,
    ): self {
        PlanCalendar::assertDays($daysRequested);
        $daysTotal = self::fitDays($daysRequested, $eventDate, $today);

        $days = [];
        foreach (PlanCalendar::layout($daysTotal) as $index => $type) {
            $days[] = PlanDay::planned($dayIds(), $id, $index + 1, $type);
        }

        return new self(
            $id, $userId, trim($goalText), $targetLang, $nativeLang, $level, $daysTotal, $daysRequested,
            $eventDate?->setTime(0, 0), PlanStatus::Building, null, null, null, [], null, null, $now, null, null, null,
            $now, [], $days, $pace,
        );
    }

    /**
     * @param  list<PlanScene>  $scenes
     * @param  list<PlanDay>  $days
     * @param  list<array{check: string, mode: string, action: string, detail: string}>  $findings
     * @param  array<string, int>|null  $pace
     */
    public static function reconstitute(
        PlanId $id,
        UserId $userId,
        string $goalText,
        LanguageCode $targetLang,
        LanguageCode $nativeLang,
        PlanLevel $level,
        int $daysTotal,
        int $daysRequested,
        ?DateTimeImmutable $eventDate,
        PlanStatus $status,
        ?PlanTitles $titles,
        ?Image $coverImage,
        ?ModelCall $planCall,
        array $findings,
        ?string $unclearReason,
        ?string $failReason,
        ?DateTimeImmutable $buildStartedAt,
        ?CollectionId $collectionId,
        ?DateTimeImmutable $startedAt,
        ?DateTimeImmutable $finishedAt,
        DateTimeImmutable $createdAt,
        array $scenes,
        array $days,
        ?array $pace = null,
    ): self {
        return new self(
            $id, $userId, $goalText, $targetLang, $nativeLang, $level, $daysTotal, $daysRequested, $eventDate, $status,
            $titles, $coverImage, $planCall, $findings, $unclearReason, $failReason, $buildStartedAt, $collectionId,
            $startedAt, $finishedAt, $createdAt, $scenes, $days, $pace,
        );
    }

    /**
     * THE PRICE LIST OF THE PLAN'S DAYS, TAKEN AGAIN (наряд FIX-3 §2, `plan:repace`): the plan reads its minutes by the
     * list given. False when it already had exactly that list — the command is run again and changes nothing.
     *
     * @param  array<string, int>  $pace
     */
    public function repace(array $pace): bool
    {
        ksort($pace);
        $had = $this->pace;
        if ($had !== null) {
            ksort($had);
        }
        if ($had === $pace) {
            return false;
        }
        $this->pace = $pace;

        return true;
    }

    /** @return array<string, int>|null the plan's own price list — null until one is taken ({@see repace()}) */
    public function pace(): ?array
    {
        return $this->pace;
    }

    // ---- building --------------------------------------------------------------------------

    public function beginBuild(DateTimeImmutable $now): void
    {
        $this->status = PlanStatus::Building;
        $this->buildStartedAt = $now;
        $this->unclearReason = null;
        $this->failReason = null;
    }

    /**
     * The model's plan, accepted: titles, scenes, and the scene days filled in order.
     *
     * @param  callable(): PlanSceneId  $sceneIds
     * @param  list<array{check: string, mode: string, action: string, detail: string}>  $findings
     */
    public function acceptBlueprint(Blueprint $blueprint, ModelCall $call, array $findings, callable $sceneIds): void
    {
        $this->assertStatus('accept', [PlanStatus::Building]);
        if ($blueprint->titles === null) {
            throw new \InvalidArgumentException('An ok blueprint carries titles.');
        }

        $this->titles = $blueprint->titles;
        $this->planCall = $call;
        $this->findings = $findings;
        $this->scenes = array_map(
            fn (SceneBrief $brief): PlanScene => PlanScene::fromBrief($sceneIds(), $this->id, $brief),
            $blueprint->scenes,
        );
        $this->assignScenesToDays();
        $this->status = PlanStatus::Ready;
        $this->buildStartedAt = null;
    }

    /**
     * More scenes after an extension — appended after the existing ones and laid onto the scene
     * days that have none yet.
     *
     * The model's own numbering is NOT trusted here: asked for two more scenes it may answer
     * `order` 1, 2 and a fresh `priority` 1 (the live run of 10.09 did), and the plan already has
     * both. The new scenes take the orders after the last existing one, in the order they came,
     * and the priorities after the highest existing one — an extension never installs a second
     * core. The `plan_shape` check has counted the model's numbering by then.
     *
     * @param  list<SceneBrief>  $briefs
     * @param  callable(): PlanSceneId  $sceneIds
     */
    public function appendScenes(array $briefs, ModelCall $call, callable $sceneIds): void
    {
        $order = 0;
        $priority = 0;
        foreach ($this->scenes as $scene) {
            $order = max($order, $scene->order());
            $priority = max($priority, $scene->priority());
        }
        $sorted = $briefs;
        usort($sorted, static fn (SceneBrief $a, SceneBrief $b): int => [$a->order, $a->priority] <=> [$b->order, $b->priority]);
        foreach ($sorted as $brief) {
            $this->scenes[] = PlanScene::fromBrief($sceneIds(), $this->id, $brief->withOrder(++$order)->withPriority(++$priority));
        }
        $this->planCall = $this->planCall === null ? $call : new ModelCall(
            $call->promptVersion, $call->buildVersion, $call->model,
            ModelCall::addCosts($this->planCall->costUsd, $call->costUsd),
            $call->latencyMs, $this->planCall->attempts + $call->attempts,
        );
        $this->assignScenesToDays();
    }

    public function markUnclear(string $reason, ModelCall $call): void
    {
        $this->status = PlanStatus::Unclear;
        $this->unclearReason = $reason;
        $this->planCall = $call;
        $this->buildStartedAt = null;
    }

    /** @param list<array{check: string, mode: string, action: string, detail: string}> $findings */
    public function markFailed(string $reason, ?ModelCall $call, array $findings): void
    {
        $this->status = PlanStatus::Failed;
        $this->failReason = $reason;
        $this->planCall = $call;
        $this->findings = $findings;
        $this->buildStartedAt = null;
    }

    public function isBuildStale(DateTimeImmutable $now, int $staleAfterSeconds): bool
    {
        return $this->status === PlanStatus::Building
            && $this->buildStartedAt !== null
            && $now->getTimestamp() - $this->buildStartedAt->getTimestamp() > $staleAfterSeconds;
    }

    // ---- preview ---------------------------------------------------------------------------

    /** A scene taken out in the preview: its day becomes a review, the plan keeps its length. */
    public function removeScene(PlanSceneId $sceneId): void
    {
        $this->assertStatus('remove scene', [PlanStatus::Ready]);
        $scene = $this->scene($sceneId);
        if ($scene->isCore()) {
            throw CoreSceneNotRemovable::withId($sceneId);
        }

        $this->scenes = array_values(array_filter(
            $this->scenes,
            static fn (PlanScene $s): bool => ! $s->id()->equals($sceneId),
        ));
        foreach ($this->days as $day) {
            if ($day->sceneId()?->equals($sceneId) === true) {
                $day->becomeReview();
            }
        }
    }

    public function start(DateTimeImmutable $now, DateTimeImmutable $today): void
    {
        $this->assertStatus('start', [PlanStatus::Ready]);
        $this->status = PlanStatus::Active;
        $this->startedAt = $now;
        $first = $this->day(1);
        $first->unlockOn($today);
        $first->open();
    }

    // ---- life ------------------------------------------------------------------------------

    /** `overdue` is derived: an active plan whose event date has passed. */
    public function effectiveStatus(DateTimeImmutable $today): PlanStatus
    {
        if ($this->status === PlanStatus::Active && $this->eventDate !== null && $this->eventDate->format('Y-m-d') < $today->format('Y-m-d')) {
            return PlanStatus::Overdue;
        }

        return $this->status;
    }

    public function finish(DateTimeImmutable $now): void
    {
        $this->assertStatus('finish', [PlanStatus::Active, PlanStatus::Overdue]);
        $this->status = PlanStatus::Finished;
        $this->finishedAt = $now;
    }

    public function delete(): void
    {
        $this->status = PlanStatus::Deleted;
    }

    public function attachCollection(CollectionId $collectionId): void
    {
        $this->collectionId ??= $collectionId;
    }

    public function attachCoverImage(Image $image): void
    {
        $this->coverImage ??= $image;
    }

    /**
     * A new date and/or a new number of days. Days already touched are kept as they are; the
     * rest is laid out again. Scenes that no longer fit are dropped by the rule — variants first,
     * then the highest priority number, never the core — and scene days without a scene are
     * reported as the number of scenes to ask the model for.
     *
     * @param  callable(): PlanDayId  $dayIds
     * @return array{scenes_to_add: int, dropped_scene_ids: list<string>}
     */
    public function reschedule(?DateTimeImmutable $eventDate, ?int $daysRequested, DateTimeImmutable $today, callable $dayIds): array
    {
        $this->assertStatus('reschedule', [PlanStatus::Ready, PlanStatus::Active, PlanStatus::Overdue]);
        $requested = $daysRequested ?? $this->daysRequested;
        PlanCalendar::assertDays($requested);
        $this->eventDate = $eventDate?->setTime(0, 0);
        $this->daysRequested = $requested;

        $touched = count(array_filter($this->days, static fn (PlanDay $d): bool => $d->isTouched()));
        $daysTotal = self::fitDays($requested, $this->eventDate, $today);
        if ($daysTotal < $touched) {
            throw PlanTooShort::minimum($touched);
        }
        $this->daysTotal = $daysTotal;

        $layout = PlanCalendar::layout($daysTotal);
        $kept = array_slice($this->days, 0, $touched);
        $days = $kept;
        for ($number = $touched + 1; $number <= $daysTotal; $number++) {
            $existing = $this->days[$number - 1] ?? null;
            $type = $layout[$number - 1];
            if ($existing !== null) {
                $existing->retype($type);
                $days[] = $existing;
            } else {
                $days[] = PlanDay::planned($dayIds(), $this->id, $number, $type);
            }
        }
        $this->days = $days;

        // Scenes standing on kept days stay; the rest are laid out again, shortened by the rule.
        $keptSceneIds = [];
        foreach ($kept as $day) {
            if ($day->sceneId() !== null) {
                $keptSceneIds[$day->sceneId()->value] = true;
            }
        }
        $free = array_values(array_filter(
            $this->scenes,
            static fn (PlanScene $s): bool => ! isset($keptSceneIds[$s->id()->value]),
        ));
        $slots = 0;
        foreach (array_slice($this->days, $touched) as $day) {
            if ($day->type() === DayType::Scene) {
                $slots++;
            }
        }

        $dropped = [];
        while (count($free) > $slots) {
            $victim = self::sceneToDrop($free);
            if ($victim === null) {
                break;
            }
            $dropped[] = $victim->id()->value;
            $free = array_values(array_filter($free, static fn (PlanScene $s): bool => ! $s->id()->equals($victim->id())));
        }
        $this->scenes = array_values(array_filter(
            $this->scenes,
            static fn (PlanScene $s): bool => ! in_array($s->id()->value, $dropped, true),
        ));
        // The untouched scene days are dealt their scenes afresh, in scene order — a day that
        // pointed at a dropped scene, or at a scene now standing earlier, would otherwise keep it.
        foreach (array_slice($this->days, $touched) as $day) {
            if ($day->type() === DayType::Scene) {
                $day->clearScene();
            }
        }
        $this->assignScenesToDays();

        return ['scenes_to_add' => max(0, $slots - count($free)), 'dropped_scene_ids' => $dropped];
    }

    // ---- days ------------------------------------------------------------------------------

    /**
     * Start (or continue) a day. Returns the day; the caller deals its cards if it has none yet.
     *
     * A day opens when the day before it is closed, its lesson is written, and its calendar day has come — the day after
     * the day before it was OPENED, in the learner's calendar ({@see closeDay()}) — or at once while the plan is catching
     * up with its event ({@see isCatchingUp()}; `docs/plan-v2.md` §5, наряд GEN-3 §11). And, while the paywall is on, when
     * it does not hold the day (наряд ACC-1 §2; {@see Paywall}) — asked first: a day it holds stays shut whatever the date.
     */
    public function openDay(int $number, DateTimeImmutable $today, DateTimeImmutable $now, ?Paywall $paywall = null): PlanDay
    {
        $this->assertStatus('open day', [PlanStatus::Active, PlanStatus::Overdue]);
        $day = $this->day($number);

        if ($day->status() === DayStatus::InProgress) {
            return $day;
        }
        if ($day->status() === DayStatus::Closed) {
            throw PlanDayNotOpen::day($number, $day->status());
        }
        if ($paywall?->locks($day) === true) {
            throw PlanDayLocked::bySubscription($number);
        }

        $previous = $number > 1 ? $this->day($number - 1) : null;
        if ($previous !== null && ! $previous->isClosed()) {
            throw PlanDayLocked::behindDay($number, $number - 1);
        }
        if ($this->isDayBuilding($day)) {
            throw PlanDayBuilding::day($number, $this->sceneOf($day)?->lessonStatus()->wire() ?? 'building');
        }
        if (! $day->isAvailableOn($today) && ! $this->isCatchingUp($today)) {
            throw PlanDayLocked::untilDate($number, $day->opensOn()?->format('Y-m-d') ?? $today->format('Y-m-d'));
        }

        $day->start($now);

        return $day;
    }

    /**
     * Close a day: metrics written, and the next day dated — the calendar day after THIS day was opened, in the learner's
     * calendar (наряд GEN-3 §11: opened at 23:00, closed at 01:00 — the next day is there at 01:00). Returns the next day,
     * whose lesson the caller asks for.
     */
    public function closeDay(int $number, DayMetrics $metrics, DateTimeImmutable $today, DateTimeImmutable $now): ?PlanDay
    {
        $this->assertStatus('close day', [PlanStatus::Active, PlanStatus::Overdue]);
        $day = $this->day($number);
        if ($day->status() !== DayStatus::InProgress) {
            throw PlanDayNotOpen::day($number, $day->status());
        }
        $opened = ($day->openedAt() ?? $now)->setTimezone($today->getTimezone());
        $day->close($now, $metrics);

        $next = $this->days[$number] ?? null;
        $next?->unlockOn($opened->setTime(0, 0)->modify('+1 day'));

        return $next;
    }

    /**
     * «ДОГОНЯЕМ» (наряд GEN-3 §11): the calendar days left until the event, the event's own day included, are no more than the
     * days of the plan not passed yet — no day waits for its calendar day, the next one opens as soon as the one before it
     * closes. The event's day is a study day: on it the days left open one after another. A plan with no event date never
     * catches up; after the event date the calendar rules again.
     */
    public function isCatchingUp(DateTimeImmutable $today): bool
    {
        if ($this->eventDate === null || $this->eventDate->format('Y-m-d') < $today->format('Y-m-d')) {
            return false;
        }
        $left = count(array_filter($this->days, static fn (PlanDay $d): bool => ! $d->isClosed()));

        return PlanCalendar::calendarDaysBetween($today, $this->eventDate) + 1 <= $left;
    }

    /**
     * A day waiting for its lesson (наряд GEN-3 §11): the next day of a live plan — the one after the last closed day, or
     * day 1 — is a scene day whose lesson is not written yet (asked for, being written, waiting for its photos). It is not
     * `locked` and not `failed`: the client shows «собираем урок» and asks again; opening it is 409 `plan_day_building`.
     */
    public function isDayBuilding(PlanDay $day): bool
    {
        if (! $this->status->isLive() || ! in_array($day->status(), [DayStatus::Locked, DayStatus::Open], true)) {
            return false;
        }
        $previous = $day->number() > 1 ? $this->day($day->number() - 1) : null;
        $scene = $day->type() === DayType::Scene ? $this->sceneOf($day) : null;

        return ($previous === null || $previous->isClosed()) && $scene !== null && $scene->isAwaitingLesson();
    }

    /**
     * The day's status for today, not as stored: a locked day whose day before it is closed and whose calendar day has
     * come — or whose plan is catching up — reads `open`; a day the paywall holds reads `locked` (наряд ACC-1 §2).
     */
    public function effectiveDayStatus(PlanDay $day, DateTimeImmutable $today, ?Paywall $paywall = null): DayStatus
    {
        if ($paywall?->locks($day) === true) {
            return DayStatus::Locked;
        }
        if ($day->status() !== DayStatus::Locked) {
            return $day->status();
        }
        $previous = $day->number() > 1 ? $this->day($day->number() - 1) : null;
        $previousClosed = $previous === null || $previous->isClosed();

        return $previousClosed && $this->status->isLive() && ($day->isAvailableOn($today) || $this->isCatchingUp($today))
            ? DayStatus::Open
            : DayStatus::Locked;
    }

    /**
     * WHY THE DAY IS LOCKED (наряд ACC-1 §2, `days[].lock_reason`): `subscription` while the paywall holds it; `date` for
     * the lock there always was — its calendar day has not come, the day before it is not closed, the plan is not
     * started; null for a day not locked, and for a day waiting for its lesson (`building` — not a lock).
     */
    public function lockReason(PlanDay $day, DateTimeImmutable $today, ?Paywall $paywall = null): ?LockReason
    {
        if ($paywall?->locks($day) === true) {
            return LockReason::Subscription;
        }
        if ($this->isDayBuilding($day)) {
            return null;
        }

        return $this->effectiveDayStatus($day, $today) === DayStatus::Locked ? LockReason::Date : null;
    }

    /**
     * The scene whose lesson the learner's current day waits for, when nobody has asked for it — a plan's day 1 before its
     * start, or a scene an extension laid on the day the learner is on. The days after it get theirs when the day before
     * them closes (наряд GEN-3 §11), never here.
     */
    public function currentSceneWithoutLesson(): ?PlanScene
    {
        $current = $this->currentDay();
        $scene = $current !== null && $current->type() === DayType::Scene ? $this->sceneOf($current) : null;

        return $scene !== null && $scene->needsLesson() ? $scene : null;
    }

    public function day(int $number): PlanDay
    {
        return $this->days[$number - 1] ?? throw PlanDayNotFound::number($number);
    }

    public function scene(PlanSceneId $id): PlanScene
    {
        foreach ($this->scenes as $scene) {
            if ($scene->id()->equals($id)) {
                return $scene;
            }
        }

        throw SceneNotFound::withId($id);
    }

    public function sceneOf(PlanDay $day): ?PlanScene
    {
        $id = $day->sceneId();

        return $id === null ? null : $this->scene($id);
    }

    /** The first day that is not closed — what the tab shows; null when every day is closed. */
    public function currentDay(): ?PlanDay
    {
        foreach ($this->days as $day) {
            if (! $day->isClosed()) {
                return $day;
            }
        }

        return null;
    }

    /** The next scene day after `$number` — whose lesson is written when day `$number` opens. */
    public function nextSceneDayAfter(int $number): ?PlanDay
    {
        foreach ($this->days as $day) {
            if ($day->number() > $number && $day->type() === DayType::Scene && $day->sceneId() !== null) {
                return $day;
            }
        }

        return null;
    }

    /**
     * The scene days before `$number`, closest first.
     *
     * @return list<PlanDay>
     */
    public function sceneDaysBefore(int $number, int $limit): array
    {
        $out = [];
        for ($n = $number - 1; $n >= 1 && count($out) < $limit; $n--) {
            $day = $this->day($n);
            if ($day->type() === DayType::Scene && $day->sceneId() !== null) {
                $out[] = $day;
            }
        }

        return $out;
    }

    /**
     * THE STORY SO FAR of a scene (`lesson_day.v4.7`, EARLIER_DAYS; наряд GEN-3): the scene days of this plan before the
     * scene's day whose lesson is written (ready, or waiting for its photos), in the order of the calendar — none for the
     * first scene day, and none for a scene no day holds. Each is read off its served lesson, the partner's gender as the
     * day was spoken ({@see PlanScene::partnerVoiceGender()}, the default cast when it has none).
     */
    public function earlierDaysOf(PlanSceneId $sceneId): EarlierDays
    {
        $days = [];
        foreach ($this->days as $day) {
            if ($day->sceneId()?->equals($sceneId) === true) {
                return new EarlierDays($days);
            }
            $scene = $day->type() === DayType::Scene ? $this->sceneOf($day) : null;
            $lesson = $scene?->hasLesson() === true ? $scene->lesson() : null;
            if ($scene !== null && $lesson !== null) {
                $days[] = EarlierDay::of(
                    $day->number(),
                    $scene->titleTarget(),
                    $scene->partnerRoleTarget(),
                    $scene->partnerVoiceGender() ?? PlanScene::DEFAULT_PARTNER_VOICE,
                    $lesson,
                );
            }
        }

        return new EarlierDays;
    }

    /**
     * Who speaks in a scene's lesson (наряд GEN-3): the learner's role is the PLAN's — the same on every day of the story —
     * and the partner's is the scene's. A plan has its titles from the moment it has scenes.
     */
    public function lessonRoles(PlanScene $scene): LessonRoles
    {
        if ($this->titles === null) {
            throw PlanNotInState::for('write a lesson', $this->status, [PlanStatus::Ready, PlanStatus::Active, PlanStatus::Overdue, PlanStatus::Finished]);
        }

        return new LessonRoles(
            $this->titles->learnerRoleTarget,
            $this->titles->learnerRoleNative,
            $scene->partnerRoleTarget(),
            $scene->partnerRoleNative(),
        );
    }

    /** The previous scene day (any distance back), or null. */
    public function previousSceneDay(int $number): ?PlanDay
    {
        return $this->sceneDaysBefore($number, 1)[0] ?? null;
    }

    public function daysShortenedFrom(): ?int
    {
        return $this->daysRequested > $this->daysTotal ? $this->daysRequested : null;
    }

    public function daysLeftUntilEvent(DateTimeImmutable $today): ?int
    {
        if ($this->eventDate === null) {
            return null;
        }

        return PlanCalendar::calendarDaysBetween($today, $this->eventDate);
    }

    // ---- internals -------------------------------------------------------------------------

    private static function fitDays(int $requested, ?DateTimeImmutable $eventDate, DateTimeImmutable $today): int
    {
        if ($eventDate === null) {
            return $requested;
        }

        return min($requested, PlanCalendar::daysUntil($today, $eventDate));
    }

    /** Scene days in order get the scenes in order; extra scene days stay empty until an extension answers. */
    private function assignScenesToDays(): void
    {
        $assigned = [];
        foreach ($this->days as $day) {
            if ($day->sceneId() !== null) {
                $assigned[$day->sceneId()->value] = true;
            }
        }
        $queue = array_values(array_filter(
            $this->scenes,
            static fn (PlanScene $s): bool => ! isset($assigned[$s->id()->value]),
        ));
        usort($queue, static fn (PlanScene $a, PlanScene $b): int => $a->order() <=> $b->order());

        foreach ($this->days as $day) {
            if ($day->type() !== DayType::Scene || $day->sceneId() !== null) {
                continue;
            }
            $scene = array_shift($queue);
            if ($scene === null) {
                break;
            }
            $day->assignScene($scene->id());
        }
    }

    /**
     * Which scene goes when the plan shrinks: a variant first (the latest one), then the situation
     * with the highest priority number. The core (priority 1) is never chosen.
     *
     * @param  list<PlanScene>  $candidates
     */
    private static function sceneToDrop(array $candidates): ?PlanScene
    {
        $variants = array_values(array_filter($candidates, static fn (PlanScene $s): bool => $s->kind() === SceneKind::Variant && ! $s->isCore()));
        if ($variants !== []) {
            usort($variants, static fn (PlanScene $a, PlanScene $b): int => $b->order() <=> $a->order());

            return $variants[0];
        }
        $situations = array_values(array_filter($candidates, static fn (PlanScene $s): bool => ! $s->isCore()));
        if ($situations === []) {
            return null;
        }
        usort($situations, static fn (PlanScene $a, PlanScene $b): int => $b->priority() <=> $a->priority());

        return $situations[0];
    }

    /** @param list<PlanStatus> $wanted */
    private function assertStatus(string $action, array $wanted): void
    {
        if (! in_array($this->status, $wanted, true)) {
            throw PlanNotInState::for($action, $this->status, $wanted);
        }
    }

    // ---- accessors -------------------------------------------------------------------------

    public function id(): PlanId
    {
        return $this->id;
    }

    public function userId(): UserId
    {
        return $this->userId;
    }

    public function goalText(): string
    {
        return $this->goalText;
    }

    public function targetLang(): LanguageCode
    {
        return $this->targetLang;
    }

    public function nativeLang(): LanguageCode
    {
        return $this->nativeLang;
    }

    public function level(): PlanLevel
    {
        return $this->level;
    }

    public function daysTotal(): int
    {
        return $this->daysTotal;
    }

    public function daysRequested(): int
    {
        return $this->daysRequested;
    }

    public function eventDate(): ?DateTimeImmutable
    {
        return $this->eventDate;
    }

    public function status(): PlanStatus
    {
        return $this->status;
    }

    public function titles(): ?PlanTitles
    {
        return $this->titles;
    }

    public function coverImage(): ?Image
    {
        return $this->coverImage;
    }

    public function planCall(): ?ModelCall
    {
        return $this->planCall;
    }

    /** @return list<array{check: string, mode: string, action: string, detail: string}> */
    public function findings(): array
    {
        return $this->findings;
    }

    public function unclearReason(): ?string
    {
        return $this->unclearReason;
    }

    public function failReason(): ?string
    {
        return $this->failReason;
    }

    public function buildStartedAt(): ?DateTimeImmutable
    {
        return $this->buildStartedAt;
    }

    public function collectionId(): ?CollectionId
    {
        return $this->collectionId;
    }

    public function startedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function finishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return list<PlanScene> */
    public function scenes(): array
    {
        return $this->scenes;
    }

    /** @return list<PlanDay> */
    public function days(): array
    {
        return $this->days;
    }
}

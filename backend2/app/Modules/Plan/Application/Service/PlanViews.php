<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\DayRouteView;
use App\Modules\Plan\Application\Dto\DaySlotView;
use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Dto\PlanSummaryView;
use App\Modules\Plan\Application\Dto\PlanView;
use App\Modules\Plan\Application\Dto\SceneView;
use App\Modules\Plan\Application\Dto\VersionsView;
use App\Modules\Plan\Application\Port\BuildVersion;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\Service\PlanCalendar;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use App\Modules\Plan\Domain\ValueObject\PlanStatus;
use DateTimeImmutable;

/**
 * The read models of the plan, built from the aggregate — one factory so the tab, the preview
 * and the day room print the same strings for the same facts.
 */
final readonly class PlanViews
{
    public function __construct(
        private PlanModelPort $model,
        private BuildVersion $build,
        private PlanConfig $config,
    ) {}

    public function versions(): VersionsView
    {
        return new VersionsView($this->build->current(), $this->model->planPromptVersion(), $this->model->lessonPromptVersion());
    }

    public function plan(Plan $plan, DateTimeImmutable $today): PlanView
    {
        $strings = new NativeStrings($plan->nativeLang()->value);
        $titles = $plan->titles();
        $daysLeft = $plan->daysLeftUntilEvent($today);
        $status = $plan->effectiveStatus($today);
        $current = $plan->currentDay();

        $cost = $plan->planCall()->costUsd ?? '0.000000';
        foreach ($plan->scenes() as $scene) {
            $cost = ModelCall::addCosts($cost, $scene->lessonCall()->costUsd ?? '0.000000');
        }

        return new PlanView(
            id: $plan->id()->value,
            status: $status->value,
            goalText: $plan->goalText(),
            targetLang: $plan->targetLang()->value,
            nativeLang: $plan->nativeLang()->value,
            level: $plan->level()->value,
            daysTotal: $plan->daysTotal(),
            daysRequested: $plan->daysRequested(),
            daysShortenedFrom: $plan->daysShortenedFrom(),
            eventDate: $plan->eventDate()?->format('Y-m-d'),
            daysLeft: $daysLeft,
            titleNative: $titles?->titleNative,
            titleTarget: $titles?->titleTarget,
            eventNative: $titles?->eventNative,
            untilPhrase: $titles !== null && $daysLeft !== null && $daysLeft >= 0
                ? $strings->untilPhrase($titles->untilPhraseNative, $daysLeft)
                : null,
            overdueNative: $status === PlanStatus::Overdue ? $titles?->overdueNative : null,
            routeSummary: $strings->routeSummary(PlanCalendar::layout($plan->daysTotal())),
            learnerRoleTarget: $titles?->learnerRoleTarget,
            learnerRoleNative: $titles?->learnerRoleNative,
            coverImage: $plan->coverImage()?->toArray(),
            collectionId: $plan->collectionId()?->value,
            unclearReason: $plan->unclearReason(),
            failReason: $plan->failReason(),
            currentDay: $current === null ? null : $this->day($plan, $current, $today, $strings),
            days: array_map(fn (PlanDay $d): DayRouteView => $this->day($plan, $d, $today, $strings), $plan->days()),
            scenes: array_map(fn (PlanScene $s): SceneView => $this->scene($plan, $s), $plan->scenes()),
            rescueKit: $this->config->rescueKit,
            costUsd: $cost,
            versions: $this->versions(),
            startedAt: $plan->startedAt()?->format(DATE_ATOM),
            finishedAt: $plan->finishedAt()?->format(DATE_ATOM),
            createdAt: $plan->createdAt()->format(DATE_ATOM),
        );
    }

    public function summary(Plan $plan, DateTimeImmutable $today): PlanSummaryView
    {
        return new PlanSummaryView(
            id: $plan->id()->value,
            status: $plan->effectiveStatus($today)->value,
            titleNative: $plan->titles()?->titleNative,
            goalText: $plan->goalText(),
            eventDate: $plan->eventDate()?->format('Y-m-d'),
            daysTotal: $plan->daysTotal(),
            collectionId: $plan->collectionId()?->value,
            startedAt: $plan->startedAt()?->format(DATE_ATOM),
            finishedAt: $plan->finishedAt()?->format(DATE_ATOM),
            createdAt: $plan->createdAt()->format(DATE_ATOM),
        );
    }

    public function scene(Plan $plan, PlanScene $scene): SceneView
    {
        $dayNumber = null;
        foreach ($plan->days() as $day) {
            if ($day->sceneId()?->equals($scene->id()) === true) {
                $dayNumber = $day->number();
                break;
            }
        }

        return new SceneView(
            id: $scene->id()->value,
            order: $scene->order(),
            kind: $scene->kind()->value,
            priority: $scene->priority(),
            titleNative: $scene->titleNative(),
            titleTarget: $scene->titleTarget(),
            teachesNative: $scene->teachesNative(),
            goalsNative: $scene->goalsNative(),
            learnerRoleTarget: $scene->learnerRoleTarget(),
            learnerRoleNative: $scene->learnerRoleNative(),
            partnerRoleTarget: $scene->partnerRoleTarget(),
            partnerRoleNative: $scene->partnerRoleNative(),
            image: $scene->image()?->toArray(),
            lessonStatus: $scene->lessonStatus()->value,
            lessonFailReason: $scene->failReason(),
            dayNumber: $dayNumber,
            costUsd: $scene->lessonCall()?->costUsd,
            latencyMs: $scene->lessonCall()?->latencyMs,
            promptVersion: $scene->lessonCall()?->promptVersion,
        );
    }

    public function day(Plan $plan, PlanDay $day, DateTimeImmutable $today, ?NativeStrings $strings = null): DayRouteView
    {
        $strings ??= new NativeStrings($plan->nativeLang()->value);
        $scene = $plan->sceneOf($day);
        $metrics = $day->metrics();

        return new DayRouteView(
            id: $day->id()->value,
            number: $day->number(),
            type: $day->type()->value,
            status: $this->effectiveDayStatus($plan, $day, $today)->value,
            sceneId: $scene?->id()->value,
            titleNative: $scene?->titleNative(),
            titleTarget: $scene?->titleTarget(),
            teachesNative: $scene?->teachesNative(),
            lessonStatus: $scene?->lessonStatus()->value,
            opensOn: $day->opensOn()?->format('Y-m-d'),
            slot: $this->slot($plan, $day, $today, $strings),
            cardsTotal: $metrics->cardsTotal,
            cardsDone: $metrics->cardsDone,
            minutesSpent: $metrics->minutesSpent,
            openedAt: $day->openedAt()?->format(DATE_ATOM),
            closedAt: $day->closedAt()?->format(DATE_ATOM),
        );
    }

    /** A locked day whose date has come and whose predecessor is closed reads as `open`. */
    private function effectiveDayStatus(Plan $plan, PlanDay $day, DateTimeImmutable $today): DayStatus
    {
        if ($day->status() !== DayStatus::Locked) {
            return $day->status();
        }
        $previous = $day->number() > 1 ? $plan->day($day->number() - 1) : null;
        $previousClosed = $previous === null || $previous->isClosed();

        return $previousClosed && $day->isAvailableOn($today) && $plan->status()->isLive()
            ? DayStatus::Open
            : DayStatus::Locked;
    }

    /**
     * The day's date: a closed day has the day it was closed; an available day is today; a locked
     * day behind an open one is «tomorrow» or later, counted one per day FROM THE CURRENT DAY'S
     * OWN DATE.
     *
     * That last part is the whole rule. The days after the current one are not counted from today
     * — they are counted from where the current day itself stands. Closing day N puts day N+1 on
     * tomorrow, and counting day N+2 from today put it on tomorrow as well: two days wearing the
     * same «завтра» on one route.
     */
    private function slot(Plan $plan, PlanDay $day, DateTimeImmutable $today, NativeStrings $strings): DaySlotView
    {
        $midnight = $today->setTime(0, 0);
        if ($day->isClosed()) {
            // The instant it was closed, read in the learner's own calendar.
            $date = $day->closedAt()?->setTimezone($today->getTimezone()) ?? $midnight;

            return new DaySlotView(DaySlotView::PAST, $date->format('Y-m-d'), null);
        }
        $current = $plan->status()->isLive() ? $plan->currentDay() : null;
        if ($current === null) {
            $offset = $day->number() - 1;
        } else {
            $opens = $current->opensOn();
            $base = $opens === null ? 0 : max(0, PlanCalendar::calendarDaysBetween($midnight, $opens));
            $offset = max(0, $base + $day->number() - $current->number());
        }
        $date = $midnight->modify("+{$offset} day");

        return match ($offset) {
            0 => new DaySlotView(DaySlotView::TODAY, $date->format('Y-m-d'), $strings->today()),
            1 => new DaySlotView(DaySlotView::TOMORROW, $date->format('Y-m-d'), $strings->tomorrow()),
            default => new DaySlotView(DaySlotView::DATE, $date->format('Y-m-d'), null),
        };
    }
}

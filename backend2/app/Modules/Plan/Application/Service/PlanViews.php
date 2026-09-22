<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\DayRouteView;
use App\Modules\Plan\Application\Dto\DaySlotView;
use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Dto\PlanSummaryView;
use App\Modules\Plan\Application\Dto\PlanView;
use App\Modules\Plan\Application\Dto\RouteStageView;
use App\Modules\Plan\Application\Dto\SceneView;
use App\Modules\Plan\Application\Dto\VersionsView;
use App\Modules\Plan\Application\Port\BuildVersion;
use App\Modules\Plan\Application\Port\LearnerGender;
use App\Modules\Plan\Application\Port\LearnerHabits;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Repository\ConversationRepository;
use App\Modules\Plan\Domain\Repository\StagePassageRepository;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Service\ConversationRules;
use App\Modules\Plan\Domain\Service\DayStages;
use App\Modules\Plan\Domain\ValueObject\TalkStage;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\Service\NativeStrings;
use App\Modules\Plan\Domain\Service\PlanCalendar;
use App\Modules\Plan\Domain\Service\RouteStages;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\RouteStage;
use App\Modules\Plan\Domain\ValueObject\Stage;
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
        private DayCardRepository $cards,
        private DayDealer $dealer,
        private LearnerHabits $habits,
        private ConversationRepository $conversations,
        private StagePassageRepository $passages,
        private ConversationRules $rules,
        private LearnerGender $learners,
    ) {}

    public function versions(): VersionsView
    {
        return new VersionsView($this->build->current(), $this->model->planPromptVersion(), $this->model->lessonPromptVersion());
    }

    public function plan(Plan $plan, DateTimeImmutable $today): PlanView
    {
        // «…скажешь всё это сам» ends as the learner's profile says (наряд FIX-3 §1).
        $strings = new NativeStrings($plan->nativeLang()->value, $this->learners->of($plan->userId()));
        $titles = $plan->titles();
        $daysLeft = $plan->daysLeftUntilEvent($today);
        $status = $plan->effectiveStatus($today);
        $current = $plan->currentDay();

        // The route's stages: every dealt day counted in one grouped query, and the one day that
        // is not dealt yet but is the learner's next — drawn from what the dealer will deal.
        $tallies = $this->cards->stageTallies($plan->id());
        $outline = $current === null || isset($tallies[$current->id()->value]) ? [] : $this->outline($plan, $current);
        // The sixth node of every day, in two queries (наряд CONV-1; walked — наряд CONV-2): the talk has no
        // cards, so where it stands is read off the journal of stages first and off the day's talks second.
        $dayIds = array_map(static fn (PlanDay $d): PlanDayId => $d->id(), $plan->days());
        $passed = $this->passages->ofDays($dayIds, Stage::Conversation);
        $started = $this->conversations->latestForDays($dayIds);
        $talks = [];
        foreach ($dayIds as $dayId) {
            $talks[$dayId->value] = TalkStage::of(isset($passed[$dayId->value]), isset($started[$dayId->value]));
        }

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
            coverImage: self::imageArray($plan->coverImage()),
            collectionId: $plan->collectionId()?->value,
            unclearReason: $plan->unclearReason(),
            failReason: $plan->failReason(),
            currentDay: $current === null ? null : $this->routeDay($plan, $current, $today, $strings, $tallies[$current->id()->value] ?? [], $outline, $talks[$current->id()->value] ?? null),
            days: array_map(fn (PlanDay $d): DayRouteView => $this->routeDay(
                $plan, $d, $today, $strings, $tallies[$d->id()->value] ?? [], $current !== null && $d->id()->equals($current->id()) ? $outline : [],
                $talks[$d->id()->value] ?? null,
            ), $plan->days()),
            scenes: array_map(fn (PlanScene $s): SceneView => $this->scene($plan, $s), $plan->scenes()),
            rescueKit: $this->config->rescueKit,
            costUsd: $cost,
            versions: $this->versions(),
            startedAt: $plan->startedAt()?->format(DATE_ATOM),
            finishedAt: $plan->finishedAt()?->format(DATE_ATOM),
            createdAt: $plan->createdAt()->format(DATE_ATOM),
            summary: $strings->planSummary($this->sceneTitles($plan), $plan->eventDate()),
            // One reminder hour for the server's tick and the phone's local reminders — the rule is
            // Identity's `UsualVisitTime` (usual visit hour, 19:00 without visits, never before 08:00).
            reminderHour: intdiv($this->habits->usualVisitMinutes($plan->userId()), 60),
            catchUp: $plan->isCatchingUp($today),
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
            image: self::imageArray($scene->image()),
            // `illustrating` is `building` on the wire: the day is still being put together (DAY-UI-3).
            lessonStatus: $scene->lessonStatus()->wire(),
            lessonFailReason: $scene->failReason(),
            dayNumber: $dayNumber,
            costUsd: $scene->lessonCall()?->costUsd,
            latencyMs: $scene->lessonCall()?->latencyMs,
            promptVersion: $scene->lessonCall()?->promptVersion,
            imageVersion: $scene->image()?->version(),
        );
    }

    /**
     * One day of the route on its own. `$cards` are the day's cards when the caller already holds
     * them — dealt ones for an opened day, the dealer's outline for one not opened yet (the day
     * room has exactly that list) — so the stages cost nothing more; without them the day's stages
     * are read with the plan's one grouped query.
     *
     * @param  list<DayCard>|null  $cards
     */
    public function day(Plan $plan, PlanDay $day, DateTimeImmutable $today, ?NativeStrings $strings = null, ?array $cards = null): DayRouteView
    {
        $strings ??= new NativeStrings($plan->nativeLang()->value);
        if ($cards === null) {
            $tallies = $this->cards->stageTallies($plan->id())[$day->id()->value] ?? [];
            $current = $plan->currentDay();
            $outline = $tallies === [] && $current !== null && $current->id()->equals($day->id()) ? $this->outline($plan, $day) : [];
        } elseif ($day->openedAt() !== null) {
            $tallies = RouteStages::tally($cards);
            $outline = [];
        } else {
            $tallies = [];
            $outline = RouteStages::stagesOf($cards);
        }

        $talk = TalkStage::of(
            $this->passages->of($day->id(), Stage::Conversation) !== null,
            $this->conversations->latestForDay($day->id()) !== null,
        );

        return $this->routeDay($plan, $day, $today, $strings, $tallies, $outline, $talk);
    }

    /**
     * @param  array<string, array{total: int, answered: int}>  $tallies
     * @param  list<Stage>  $outline
     * @param  TalkStage|null  $talk  where the day's sixth stage stands (наряды CONV-1, CONV-2); null — nothing of it yet
     */
    private function routeDay(Plan $plan, PlanDay $day, DateTimeImmutable $today, NativeStrings $strings, array $tallies, array $outline, ?TalkStage $talk = null): DayRouteView
    {
        $scene = $plan->sceneOf($day);
        $status = $plan->effectiveDayStatus($day, $today);
        $current = $plan->currentDay();
        $availableToday = $current !== null && $current->id()->equals($day->id())
            && ($status === DayStatus::Open || $status === DayStatus::InProgress);
        $metrics = $day->metrics();

        return new DayRouteView(
            id: $day->id()->value,
            number: $day->number(),
            type: $day->type()->value,
            // A day next in line whose lesson is still being written is `building` (наряд GEN-3 §11) — not locked, not failed.
            status: $plan->isDayBuilding($day) ? DayRouteView::BUILDING : $status->value,
            sceneId: $scene?->id()->value,
            titleNative: $scene?->titleNative(),
            titleTarget: $scene?->titleTarget(),
            teachesNative: $scene?->teachesNative(),
            lessonStatus: $scene?->lessonStatus()->wire(),
            opensOn: $day->opensOn()?->format('Y-m-d'),
            slot: $this->slot($plan, $day, $today, $strings),
            cardsTotal: $metrics->cardsTotal,
            cardsDone: $metrics->cardsDone,
            minutesSpent: $metrics->minutesSpent,
            openedAt: $day->openedAt()?->format(DATE_ATOM),
            closedAt: $day->closedAt()?->format(DATE_ATOM),
            stages: array_map(
                static fn (RouteStage $s): RouteStageView => new RouteStageView($s->stage->value, $s->state->value),
                RouteStages::of(
                    $day->type(), $tallies, $day->isClosed(), $availableToday, $outline,
                    DayStages::walksConversation($day, $this->rules->enabled), $talk,
                ),
            ),
        );
    }

    /**
     * The stages the dealer would deal the day now — nothing written. Empty when the day has no
     * material yet (its lesson is not written), which leaves the day to its type's stages.
     *
     * @return list<Stage>
     */
    private function outline(Plan $plan, PlanDay $day): array
    {
        return RouteStages::stagesOf($this->dealer->outline($plan, $day));
    }

    /**
     * The titles of the scene days in route order — reviews and the rehearsal have no scene.
     *
     * @return list<string>
     */
    private function sceneTitles(Plan $plan): array
    {
        $titles = [];
        foreach ($plan->days() as $day) {
            $scene = $day->type() === DayType::Scene ? $plan->sceneOf($day) : null;
            if ($scene !== null) {
                $titles[] = $scene->titleNative();
            }
        }

        return $titles;
    }

    /** @return array{url: string, author: string|null, author_url: string|null, tone: string|null}|null */
    private static function imageArray(?Image $image): ?array
    {
        return $image === null ? null : [...$image->toArray(), 'tone' => $image->tone];
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

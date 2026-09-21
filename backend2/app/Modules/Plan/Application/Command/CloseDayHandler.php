<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Command;

use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Port\NextDayAccess;
use App\Modules\Plan\Application\Port\PlanCollectionWriter;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Service\DayMetricsOf;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Application\Service\PlanEventJournal;
use App\Modules\Plan\Application\Service\PlanNotifier;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\PlanEvent;
use App\Modules\Plan\Domain\Exception\PlanDayNotOpen;
use App\Modules\Plan\Domain\Exception\StageIncomplete;
use App\Modules\Plan\Domain\Repository\StagePassageRepository;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\PlanEventKind;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;

/**
 * «День пройден»: metrics, the next day dated (the calendar day after this one was OPENED), the day's words into the plan's
 * collection — and a `day_passed` line in the plan's journal, in the same transaction (PLAN-UI-3; the order's
 * «PlanDayPassing» is this handler). `day_passed` is journal-only: no letter.
 *
 * THE ONE PLACE A DAY'S LESSON IS ASKED FOR AFTER THE PLAN IS BUILT (наряд GEN-3 §11): closing day N asks for the lesson of day
 * N+1 — when N+1 is a scene day, its lesson was never asked for, and the learner may have that day ({@see NextDayAccess},
 * always yes before PAY-1). Once: the day is closed under the plan's row lock, and closing it again is 409
 * `plan_day_not_open` before anything is queued. A review or the rehearsal is not built, but closing one asks, by the same
 * rule, for the scene day after it.
 */
final readonly class CloseDayHandler
{
    public function __construct(
        private PlanAccess $access,
        private PlanRepository $plans,
        private DayCardRepository $cards,
        private StagePassageRepository $passages,
        private PlanTermRepository $terms,
        private PlanCollectionWriter $collection,
        private DayMetricsOf $metrics,
        private LearnerCalendar $calendar,
        private PlanDispatcher $dispatcher,
        private NextDayAccess $nextDay,
        private Clock $clock,
        private TransactionManager $tx,
        private PlanEventJournal $journal,
        private PlanNotifier $notifier,
    ) {}

    public function __invoke(CloseDay $command): void
    {
        $now = $this->clock->now();
        $today = $this->calendar->todayFor($command->actorId, $now);

        /** @var PlanEvent|null $passed */
        $passed = null;
        $nextSceneId = $this->tx->run(function () use ($command, $today, $now, &$passed): ?\App\Modules\Plan\Domain\ValueObject\PlanSceneId {
            $plan = $this->access->ownedForUpdate($command->planId, $command->actorId);
            $day = $plan->day($command->number);
            if ($day->status() !== DayStatus::InProgress) {
                throw PlanDayNotOpen::day($command->number, $day->status());
            }

            $cards = $this->cards->forDay($day->id());
            foreach ($cards as $card) {
                if (! $card->isAnswered()) {
                    throw StageIncomplete::stage($card->stage(), count(array_filter(
                        $cards,
                        static fn (DayCard $c): bool => $c->stage() === $card->stage() && ! $c->isAnswered(),
                    )));
                }
            }
            // «День пройден» = every stage walked, and since наряд CONV-1 a day dealt with the talk
            // has six. The talk has no cards to count: the journal of stages says whether it was
            // walked (наряд CONV-2, п. 2) — by the FIRST talk that came to an end of its own, so a
            // «Ещё раз» still going on does not hold a walked day shut.
            if ($day->hasConversation() && $this->passages->of($day->id(), Stage::Conversation) === null) {
                throw StageIncomplete::stage(Stage::Conversation, 1);
            }

            // The day's minutes are its cards' and the talk's that walked the stage, by the time it was talked (наряд
            // BACK-TAILS-2 §8): «19 минут» on the summary is how long the day took — and a replay after it is an
            // exercise on top of the day, not a part of it.
            $metrics = $this->metrics->of($day->id(), $cards);
            $next = $plan->closeDay($command->number, $metrics, $today, $now);

            // The day's words and phrases go to the plan's collection — the ordinary mechanism.
            $scene = $day->type() === DayType::Scene ? $plan->sceneOf($day) : null;
            if ($scene !== null) {
                $collectionId = $this->collection->ensureCollection($plan);
                $plan->attachCollection($collectionId);
                $this->collection->addTerms(
                    $plan, $collectionId, $this->terms->forScene($scene->id()),
                    $scene->lessonCall()->promptVersion ?? '',
                );
            }
            $this->plans->save($plan);
            $passed = $this->journal->record($plan->id(), $plan->userId(), PlanEventKind::DayPassed, $day->id(), $day->number());

            $nextScene = $next !== null && $next->type() === DayType::Scene ? $plan->sceneOf($next) : null;

            return $nextScene !== null && $nextScene->needsLesson() && $this->nextDay->nextDayAllowed($plan, $next) ? $nextScene->id() : null;
        });

        if ($nextSceneId !== null) {
            $this->dispatcher->buildLesson($nextSceneId);
        }
        $this->notifier->notify($passed);
    }
}

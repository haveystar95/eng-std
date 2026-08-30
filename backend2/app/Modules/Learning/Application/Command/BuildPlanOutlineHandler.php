<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Application\Dto\PlanOutlineBrief;
use App\Modules\Learning\Application\Port\PlanOutlinePort;
use App\Modules\Learning\Application\Service\PlanDaysFromComputed;
use App\Modules\Learning\Domain\Exception\PlanNotFound;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Service\PlanScheduler;
use App\Modules\Learning\Domain\ValueObject\PlanOutline;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;

/**
 * P1, then A1. The one place a plan's skeleton is decided.
 *
 * ## The model call happens OUTSIDE the transaction
 *
 * Same rule the collection generator follows and for the same reason: a ten-second vendor call
 * inside an open transaction holds a row lock for ten seconds. Only the writes are transactional.
 *
 * ## The number of days is the SERVER's, and it is computed before the call
 *
 * P1 is TOLD how many days it has. It is never asked to count them — see
 * {@see PlanScheduler}. So the arithmetic runs twice around the model: once to decide what to ask
 * for, and once over the answer to decide what the days actually are. The second run can disagree
 * with the first (the model may write a day at the top of its budget band), and that disagreement
 * is the «не влезает» the learner is shown before they commit.
 *
 * ## The support language comes from the ACCOUNT, always
 *
 * It is not stored on the plan and it is not asked for. One learner has one native language, and a
 * copy of it frozen on a plan is a second answer to a question that already has one.
 */
final readonly class BuildPlanOutlineHandler
{
    public function __construct(
        private PlanRepository $plans,
        private PlanDayRepository $days,
        private PlanOutlinePort $outlines,
        private LearnerProfileReader $profiles,
        private PlanScheduler $scheduler,
        private PlanDaysFromComputed $daysFromComputed,
        private TransactionManager $tx,
        private Clock $clock,
    ) {}

    public function __invoke(BuildPlanOutline $command): void
    {
        $plan = $this->plans->findById($command->planId);
        if ($plan === null || ! $plan->userId()->equals($command->actorId)) {
            throw PlanNotFound::withId($command->planId->value);
        }

        $today = $this->clock->now()->setTime(0, 0, 0);

        // The plan is still a draft, so the learner's CURRENT language is the right one — the
        // skeleton about to be written is what it will be written in. This is the last moment the
        // account has any say; `start` freezes it.
        $plan->refreshSupportLang(new LanguageCode($this->profiles->nativeLangFor($plan->userId())));

        // How many days there ARE, before anyone asks the model to fill them.
        $days = $this->daysUntil($today, $plan->eventDate());

        $answer = $this->outlines->outlineFor(new PlanOutlineBrief(
            planId: $plan->id()->value,
            userId: $plan->userId()->value,
            goalText: $plan->goalText(),
            supportLang: $plan->supportLang()->value,
            targetLang: $plan->targetLang()->value,
            level: $plan->level()->value,
            days: $days,
            minutesPerDay: $plan->minutesPerDay(),
        ));

        $outline = PlanOutline::fromArray($answer->payload);
        $computed = $this->scheduler->compute(
            $outline,
            $plan->minutesPerDay(),
            $plan->eventDate(),
            $today,
        );

        $this->tx->run(function () use ($plan, $answer, $outline, $computed): void {
            $plan->applyOutline($answer->payload, $computed->toArray(), $outline);
            $this->plans->save($plan);
            $this->days->replaceAll($plan->id(), $this->daysFromComputed->build($plan->id(), $computed));
        });
    }

    private function daysUntil(\DateTimeImmutable $today, \DateTimeImmutable $eventDate): int
    {
        $diff = $today->diff($eventDate->setTime(0, 0, 0));

        return max(1, (int) $diff->days + 1);
    }
}

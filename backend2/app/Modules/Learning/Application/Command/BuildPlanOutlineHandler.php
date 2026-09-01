<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Command;

use App\Modules\Learning\Application\Port\LearnerProfileReader;
use App\Modules\Learning\Application\Dto\PlanOutlineBrief;
use App\Modules\Learning\Application\Port\PlanOutlinePort;
use App\Modules\Learning\Application\Service\PlanDaysFromComputed;
use App\Modules\Learning\Application\Service\PlanSkillsFromComputed;
use App\Modules\Learning\Domain\Exception\PlanNotFound;
use App\Modules\Learning\Domain\Repository\PlanDayRepository;
use App\Modules\Learning\Domain\Repository\PlanSkillRepository;
use App\Modules\Learning\Domain\Repository\PlanRepository;
use App\Modules\Learning\Domain\Service\PlanScheduler;
use App\Modules\Learning\Domain\ValueObject\PlanOutline;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\Service\TransactionManager;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use DateTimeImmutable;

/**
 * P1, then A1. The one place a plan's skeleton is decided.
 *
 * ## The model call happens OUTSIDE the transaction
 *
 * Same rule the collection generator follows and for the same reason: a ten-second vendor call
 * inside an open transaction holds a row lock for ten seconds. Only the writes are transactional.
 *
 * ## The days are the SERVER's, and the model is not told about them at all
 *
 * P1 v0.2 answers in scenes and priced abilities and never sees the calendar. The arithmetic runs
 * ONCE, over the answer ({@see PlanScheduler}), and what it produces — how many days, what does
 * not fit before the deadline — is the «срок мал» the learner is shown before they commit. Until
 * v0.2 it ran twice, and the first run was what the second one measured.
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
        private PlanSkillRepository $skills,
        private PlanOutlinePort $outlines,
        private LearnerProfileReader $profiles,
        private PlanScheduler $scheduler,
        private PlanDaysFromComputed $daysFromComputed,
        private PlanSkillsFromComputed $skillsFromComputed,
        private TransactionManager $tx,
        private Clock $clock,
    ) {}

    public function __invoke(BuildPlanOutline $command): void
    {
        $plan = $this->plans->findById($command->planId);
        if ($plan === null || ! $plan->userId()->equals($command->actorId)) {
            throw PlanNotFound::withId($command->planId->value);
        }

        // «СЕГОДНЯ» — ЭТО ДАТА В КАЛЕНДАРЕ УЧЕНИКА, А НЕ В UTC.
        //
        // Это была `$this->clock->now()`, и живой прогон показал, чего она стоит: план, созданный
        // 01.09 в 00:5x по Europe/Bucharest, лёг днями 31.08–03.09 при событии 05.09 — первый день
        // подготовки в уже прошедшем дне, 04.09 пустой. Плану из четырёх дней это стоило одного
        // (`docs/research/e2e-sim-1.md`, Д-4). Час ночи по местному времени — это ещё вчера по UTC,
        // и календарь плана — единственное место, где эта разница видна пользователю.
        //
        // Формат тот же, каким читается `event_date` ({@see PlanMapper}): полночь без зоны, то есть
        // «дата как дата». Обе стороны сравнения — {@see PlanScheduler::compute()} — так обязаны
        // быть одной природы, иначе смещение зоны превращается в лишние или недостающие сутки.
        $today = new DateTimeImmutable(
            $this->clock->now()->setTimezone($this->profiles->timezoneFor($plan->userId()))->format('Y-m-d')
            . ' 00:00:00',
        );

        // The plan is still a draft, so the learner's CURRENT language is the right one — the
        // skeleton about to be written is what it will be written in. This is the last moment the
        // account has any say; `start` freezes it.
        $plan->refreshSupportLang(new LanguageCode($this->profiles->nativeLangFor($plan->userId())));

        $answer = $this->outlines->outlineFor(new PlanOutlineBrief(
            planId: $plan->id()->value,
            userId: $plan->userId()->value,
            goalText: $plan->goalText(),
            supportLang: $plan->supportLang()->value,
            targetLang: $plan->targetLang()->value,
            level: $plan->level()->value,
        ));

        $outline = PlanOutline::fromArray($answer->payload);
        $computed = $this->scheduler->compute(
            $outline,
            $plan->minutesPerDay(),
            $plan->eventDate(),
            $today,
            $plan->supportLang()->value,
        );

        $this->tx->run(function () use ($plan, $answer, $outline, $computed): void {
            $plan->applyOutline($answer->payload, $computed->toArray(), $outline);
            $this->plans->save($plan);
            // The abilities first, then the days: the rows are the source and the days are what
            // the scheduler made of them today. Both are rewritten together, inside one
            // transaction, so no reader ever sees an ability on a day that no longer exists.
            $this->skills->replaceAll($plan->id(), $this->skillsFromComputed->build($outline, $computed));
            $this->days->replaceAll($plan->id(), $this->daysFromComputed->build($plan->id(), $computed));
        });
    }
}

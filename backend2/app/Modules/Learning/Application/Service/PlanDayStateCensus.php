<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Application\Dto\PlanDayStateView;
use App\Modules\Learning\Application\Dto\PlanProgressView;
use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\Service\PlanStageLadder;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanDayState;
use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;
use App\Modules\Learning\Domain\ValueObject\PlanKnobs;
use App\Modules\Learning\Domain\ValueObject\PlanStage;

/**
 * ОДНО СОСТОЯНИЕ ДНЯ — «не начат» / «идёт · около N минут» / «пройден» (наряд DAY-FIX-2, Ч.3).
 *
 * Считает только сервер, и считает ОДНИМ кодом для трёх экранов: вкладка «План» (пейлоад плана),
 * экран дня (тот же пейлоад дня) и шапка присеста (пейлоад посадки). Живой прогон 05.09 показал
 * три ответа на один вопрос — «Начать день», «Продолжить · осталось 1», «Сцена 11/57» — потому что
 * каждый экран считал сам. Локальных счётчиков «осталось N» на клиенте больше нет.
 *
 * ## Слово
 *
 *   `done`         день `done` в базе ИЛИ его стойки говорят «пройден» ({@see PlanDayProgressView::$passed})
 *                  — второе раньше первого на те секунды, пока `POST /complete` едет с телефона;
 *   `not_started`  ни одна карточка сцены не тронута: все стоят на A с пустым чек-листом;
 *   `in_progress`  всё остальное.
 *
 * ## Минуты
 *
 * Карточек × `card_seconds`, до целой минуты вверх. Для дня, который изучают (фокус), карточки —
 * это ТОТ ЖЕ план посадки, который раздаст сессия ({@see PlanSittingPlanner}): разогрев, день, шов,
 * прогон. Для дня впереди фокуса — его собственные карточки на ступени A: шва у него нет. Для
 * пройденного — ноль.
 */
final readonly class PlanDayStateCensus
{
    public function __construct(
        private PlanSittingPlanner $planner,
        /** @var array{sitting_max_cards: int, words_section_cards: int, rescue_warmup_cards: int, card_seconds: int} */
        private array $budget,
    ) {}

    /**
     * The state of ONE day, given the progress the caller already computed.
     *
     * @param  list<PlanDay>  $days
     * @param  array<string, \App\Modules\Learning\Domain\ValueObject\PlanTermStage>  $stages
     * @param  PlanSittingLayout|null  $layout  the sitting already planned for this day, if the
     *                                          caller has one — so the session and the census cannot
     *                                          count two different sittings
     */
    public function of(
        LearningPlan $plan,
        array $days,
        PlanProgressView $progress,
        int $dayIndex,
        PlanKnobs $knobs,
        array $stages,
        ?PlanSittingLayout $layout = null,
    ): PlanDayStateView {
        $day = null;
        foreach ($days as $candidate) {
            if ($candidate->dayIndex() === $dayIndex) {
                $day = $candidate;
            }
        }
        $view = $progress->days[$dayIndex] ?? null;

        // THE FINAL DAY has no material of its own: it is «идёт» while the plan runs and the прогон
        // of every scene is what it costs.
        if ($day !== null && $day->kind() === PlanDayKind::Final) {
            $layout ??= $this->planner->rehearsal($plan, $progress);

            return new PlanDayStateView(
                $layout->cards() === 0 ? PlanDayState::NotStarted : PlanDayState::InProgress,
                $layout->cards(),
                $layout->minutes(),
            );
        }

        if (($day !== null && $day->status() === PlanDayStatus::Done) || ($view !== null && $view->passed)) {
            return new PlanDayStateView(PlanDayState::Done, 0, 0);
        }

        if ($view === null || $view->termIds === []) {
            return new PlanDayStateView(PlanDayState::NotStarted, 0, 0);
        }

        $touched = false;
        foreach ($view->termIds as $termId) {
            $content = $view->content[$termId] ?? null;
            $standing = $view->standings[$termId] ?? null;
            if ($standing === null || ($content !== null && $content->shelf === PlanSittingPlanner::SHELF_RESCUE)) {
                continue;
            }
            // Past stage A, or ANSWERED today at all — a reply chosen in the conversation before its
            // intro was acknowledged is still the learner's first move on this day.
            if ($standing->stage !== PlanStage::A || $standing->answeredToday) {
                $touched = true;

                break;
            }
            foreach ($standing->checklist as $step) {
                if ($step['done']) {
                    $touched = true;

                    break 2;
                }
            }
        }

        $cards = $dayIndex === $progress->focusDayIndex
            ? ($layout ?? $this->planner->plan($plan, $progress, $dayIndex, $knobs, $stages))->cards()
            : $this->ownCardsOf($view);

        return new PlanDayStateView(
            $touched ? PlanDayState::InProgress : PlanDayState::NotStarted,
            $cards,
            PlanSittingLayout::minutesFor($cards, $this->budget['card_seconds']),
        );
    }

    /**
     * A day AHEAD of the focus: what its own cards still owe on their stage — no seam, no warm-up.
     */
    private function ownCardsOf(\App\Modules\Learning\Application\Dto\PlanDayProgressView $view): int
    {
        $cards = 0;
        foreach ($view->termIds as $termId) {
            $content = $view->content[$termId] ?? null;
            $standing = $view->standings[$termId] ?? null;
            if ($standing === null || ($content !== null && $content->shelf === PlanSittingPlanner::SHELF_RESCUE)) {
                continue;
            }
            foreach ($standing->checklist as $step) {
                if (! $step['done']) {
                    $cards++;
                }
            }
            // …plus the conversation the day opens behind its intros (DAY-FIX-2, DECISIONS п. 266):
            // a scene line is met AND spoken in the same sitting, and the minutes say so.
            $kind = $content === null || $content->kind === null
                ? PlanStageLadder::KIND_WORD
                : PlanStageLadder::ladderKindFor($content->kind, $content->tier, $content->shelf);
            if ($standing->stage === PlanStage::A && ! $standing->stageComplete && PlanStageLadder::opensBSameDay($kind)) {
                $cards++;
            }
        }

        return $cards;
    }
}

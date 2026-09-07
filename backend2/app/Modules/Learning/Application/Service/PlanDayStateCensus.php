<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Service;

use App\Modules\Learning\Application\Dto\PlanDayProgressView;
use App\Modules\Learning\Application\Dto\PlanDayStateView;
use App\Modules\Learning\Application\Dto\PlanProgressView;
use App\Modules\Learning\Domain\Entity\LearningPlan;
use App\Modules\Learning\Domain\Entity\PlanDay;
use App\Modules\Learning\Domain\Service\PlanStageLadder;
use App\Modules\Learning\Domain\ValueObject\PlanDayKind;
use App\Modules\Learning\Domain\ValueObject\PlanDayStage;
use App\Modules\Learning\Domain\ValueObject\PlanDayStageState;
use App\Modules\Learning\Domain\ValueObject\PlanDayState;
use App\Modules\Learning\Domain\ValueObject\PlanDayStatus;
use App\Modules\Learning\Domain\ValueObject\PlanKnobs;
use App\Modules\Learning\Domain\ValueObject\PlanStage;

/**
 * ОДНО СОСТОЯНИЕ ДНЯ — «не начат» / «идёт» / «материал пройден · разговор около N минут» /
 * «пройден» (наряд DAY-FIX-2, Ч.3; наряд DAY-FIX-3, Ч.4.3).
 *
 * Считает только сервер, и считает ОДНИМ кодом для трёх экранов: вкладка «План» (пейлоад плана),
 * экран дня (тот же пейлоад дня) и шапка присеста (пейлоад посадки). Живой прогон 05.09 показал
 * три ответа на один вопрос — «Начать день», «Продолжить · осталось 1», «Сцена 11/57» — потому что
 * каждый экран считал сам. Локальных счётчиков «осталось N» на клиенте больше нет.
 *
 * ## Слово
 *
 *   `done`           день `done` в базе ИЛИ его стойки говорят «пройден» ({@see PlanDayProgressView::$passed})
 *                    — второе раньше первого на те секунды, пока `POST /complete` едет с телефона;
 *   `not_started`    ни одна карточка сцены не тронута: все стоят на A с пустым чек-листом;
 *   `material_done`  присест «Материал» отдал всё, что должен был, и впереди только «Разговор»
 *                    ({@see \App\Modules\Learning\Domain\Service\PlanSittings}) — день, который
 *                    изучают, и только он: у дня впереди фокуса материал ещё не начат;
 *   `in_progress`    всё остальное.
 *
 * ## Минуты
 *
 * Карточек × `card_seconds`, до целой минуты вверх — по каждому присесту отдельно и вместе. Для
 * дня, который изучают (фокус), карточки — это ТОТ ЖЕ план посадки, который раздаст сессия
 * ({@see PlanSittingPlanner}): разогрев, день, шов, прогон. Для дня впереди фокуса — его
 * собственные карточки на ступени A и разговор, который они открывают: шва у него нет. Для
 * пройденного — ноль.
 */
final readonly class PlanDayStateCensus
{
    public function __construct(
        private PlanSittingPlanner $planner,
        /** @var array{material_max_cards: int, conversation_max_cards: int, words_section_cards: int, rescue_warmup_cards: int, card_seconds: int, word_choice_options: int} */
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
        // of every scene is what it costs — a conversation, whole.
        if ($day !== null && $day->kind() === PlanDayKind::Final) {
            $layout ??= $this->planner->rehearsal($plan, $progress);

            return $this->priced(
                $layout->cards() === 0 ? PlanDayState::NotStarted : PlanDayState::InProgress,
                0,
                $layout->cards(),
            );
        }

        if (($day !== null && $day->status() === PlanDayStatus::Done) || ($view !== null && $view->passed)) {
            // ДЕНЬ ПРОЙДЕН — И ЭТАПЫ ГОВОРЯТ ТО ЖЕ. Вердикт, записанный в строку, сильнее пересчёта:
            // так же его читают фокус ({@see PlanProgress::focusOf()}) и замок
            // ({@see \App\Modules\Learning\Application\Command\BuildPlanSessionHandler::lockedBy()}).
            // Отдать при этом список, где «Разговор» стоит текущим, значит выдать экрану пейлоад,
            // который спорит сам с собой: день «пройден», а этап в нём «сейчас».
            //
            // Разойтись они могут только на дне, закрытом СТАРЫМ правилом (до наряда DAY-GATE-1):
            // у нового дня строка становится `done` ровно тогда, когда пройдены все три этапа.
            return new PlanDayStateView(PlanDayState::Done, 0, 0, dayStages: self::allDone($view));
        }

        if ($view === null || $view->termIds === []) {
            return new PlanDayStateView(PlanDayState::NotStarted, 0, 0);
        }

        // ЭТАПЫ ЭКРАНА = три обязательных, посчитанных прогрессом, плюс «Повторить ошибки», которое
        // видит только перепись: сегодняшние промахи и то, брали ли их уже этой дверью.
        $dayStages = $view->stages;
        $retrain = $this->retrainCards($view, $stages, $progress->today);
        if ($retrain > 0) {
            $dayStages[] = [
                'stage' => PlanDayStage::Retrain,
                'state' => PlanDayStageState::Current,
                'cards' => $retrain,
            ];
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

        if ($dayIndex === $progress->focusDayIndex) {
            $layout ??= $this->planner->plan($plan, $progress, $dayIndex, $knobs, $stages);
            $material = $layout->materialCards();
            $conversation = $layout->conversationCards();
        } else {
            [$material, $conversation] = $this->ownCardsOf($view);
        }

        // «МАТЕРИАЛ ПРОЙДЕН» — nothing left to meet, the conversation still ahead, and the day
        // actually walked to that point: an untouched day whose material happens to be empty is
        // not a day the learner finished half of.
        $state = match (true) {
            ! $touched => PlanDayState::NotStarted,
            $material === 0 && $conversation > 0 && $dayIndex === $progress->focusDayIndex => PlanDayState::MaterialDone,
            default => PlanDayState::InProgress,
        };

        return $this->priced($state, $material, $conversation, $dayStages);
    }

    /**
     * Этапы пройденного дня — все три `done`, в каноническом порядке.
     *
     * @return list<array{stage: PlanDayStage, state: PlanDayStageState, cards: int}>
     */
    private static function allDone(?PlanDayProgressView $view): array
    {
        if ($view === null) {
            return [];
        }

        $out = [];
        foreach (PlanDayStage::REQUIRED as $stage) {
            $out[] = ['stage' => $stage, 'state' => PlanDayStageState::Done, 'cards' => 0];
        }

        return $out;
    }

    /**
     * СКОЛЬКО РЕПЛИК МОЖНО ВЗЯТЬ «ПОВТОРИТЬ ОШИБКИ» — промахнулись сегодня, этой дверью сегодня не
     * брали, и шаг у них ещё открыт (наряд DAY-GATE-1; решение владельца 07.09, п. 4).
     *
     * Спрашивается у Domain с поднятым флагом «мимо правила одного показа»
     * ({@see PlanStageLadder::owedStepsToday()}) — иначе ответ был бы всегда «ноль», ведь именно
     * это правило сегодня их и закрыло.
     *
     * @param  array<string, \App\Modules\Learning\Domain\ValueObject\PlanTermStage>  $stages
     */
    private function retrainCards(PlanDayProgressView $view, array $stages, string $today): int
    {
        $count = 0;
        foreach ($view->termIds as $termId) {
            $standing = $view->standings[$termId] ?? null;
            $content = $view->content[$termId] ?? null;
            if ($standing === null || ! $standing->missedToday) {
                continue;
            }
            if ($content !== null && $content->shelf === PlanSittingPlanner::SHELF_RESCUE) {
                continue;
            }
            if (($stages[$termId] ?? null)?->retrainedOn($today) === true) {
                continue;
            }
            $kind = $content === null || $content->kind === null
                ? PlanStageLadder::KIND_WORD
                : PlanStageLadder::ladderKindFor($content->kind, $content->tier, $content->shelf);
            if (PlanStageLadder::owedStepsToday($standing, $kind, ignoreOneShow: true) !== []) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  list<array{stage: PlanDayStage, state: PlanDayStageState, cards: int}>  $dayStages
     */
    private function priced(PlanDayState $state, int $material, int $conversation, array $dayStages = []): PlanDayStateView
    {
        $seconds = $this->budget['card_seconds'];

        return new PlanDayStateView(
            $state,
            $material + $conversation,
            PlanSittingLayout::minutesFor($material + $conversation, $seconds),
            materialCards: $material,
            materialMinutes: PlanSittingLayout::minutesFor($material, $seconds),
            conversationCards: $conversation,
            conversationMinutes: PlanSittingLayout::minutesFor($conversation, $seconds),
            dayStages: $dayStages,
        );
    }

    /**
     * A day AHEAD of the focus: what its own cards still owe on their stage — no seam, no warm-up —
     * split into the material (the checklist of stage A) and the conversation it opens.
     *
     * @return array{0: int, 1: int}  material, conversation
     */
    private function ownCardsOf(PlanDayProgressView $view): array
    {
        $material = 0;
        $conversation = 0;
        foreach ($view->termIds as $termId) {
            $content = $view->content[$termId] ?? null;
            $standing = $view->standings[$termId] ?? null;
            if ($standing === null || ($content !== null && $content->shelf === PlanSittingPlanner::SHELF_RESCUE)) {
                continue;
            }
            $kind = $content === null || $content->kind === null
                ? PlanStageLadder::KIND_WORD
                : PlanStageLadder::ladderKindFor($content->kind, $content->tier, $content->shelf);
            foreach ($standing->checklist as $step) {
                if ($step['done']) {
                    continue;
                }
                // A step of stage A is material; a step of a line's stage B is the conversation.
                if ($standing->stage === PlanStage::A) {
                    $material++;
                } else {
                    $conversation++;
                }
            }
            // …plus the conversation the day opens behind its intros (DAY-FIX-2, DECISIONS п. 266):
            // a scene line is met AND spoken in the same sitting, and the minutes say so.
            if ($standing->stage === PlanStage::A && ! $standing->stageComplete && PlanStageLadder::opensBSameDay($kind)) {
                $conversation++;
            }
        }

        return [$material, $conversation];
    }
}

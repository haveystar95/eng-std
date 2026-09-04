<?php

declare(strict_types=1);

namespace App\Modules\Learning\Application\Dto;

/**
 * A plan session, ready to play.
 *
 * `strict` is the field to read first. TRUE is the plan doing its job: this is the focus day, the
 * tasks carry stages, and every success moves a word up its checklist. FALSE is a day the learner
 * opened ahead of the focus (or went back to) — an ordinary, soft run over that day's collection,
 * which schedules nothing and closes no stage. Both are legitimate and they are not the same thing,
 * so the payload says which one it is rather than leaving the client to infer it from the presence
 * of a `stage` field.
 */
final readonly class PlanSessionView
{
    /**
     * @param  list<PlanSessionTaskView>  $tasks
     * @param  array<string, mixed>  $knobs  the level's six knobs as this session ran on them
     */
    public function __construct(
        public string $sessionId,
        public string $planId,
        public int $dayIndex,
        public bool $strict,
        public int $focusDayIndex,
        public array $tasks,
        public array $knobs,
        /**
         * HOW MANY OF `tasks` ARE THE DAY'S — the seam, as one number.
         *
         * `tasks[0 … dayTaskCount - 1]` are this plan's own material and `tasks[dayTaskCount … ]`
         * are the top-up from the ordinary queue ({@see PlanSessionTaskView::$section}). The order
         * is guaranteed, so a client draws the divider at this index and counts the day out of this
         * number rather than out of `count(tasks)`.
         */
        public int $dayTaskCount = 0,
        /**
         * ПРИСЕСТЫ — the task counts of each sitting, in order, adding up to `count(tasks)`.
         *
         * The learner's 10 / 20 / 40 minutes is the length of ONE присест, not a limit on the day
         * ({@see \App\Modules\Learning\Domain\Service\PlanSittings}): the whole day is dealt,
         * and this says where it is honest to stop — always on a section boundary, never inside
         * «Ты ответишь». The client shows its «Присест N пройден» screen at each break and keeps
         * its position durably, so leaving between two of them (or being killed) resumes where it
         * stopped without rebuilding the sitting.
         *
         * A client that ignores this plays the day as one long session, which is what it did
         * before — the field is additive.
         *
         * @var list<int>
         */
        public array $sittings = [],
        /**
         * THE CONVERSATIONS THIS SITTING PLAYS — one chain per scene, in the order the sitting
         * reaches them (наряд DAY-2, канон `docs/plan-dialogue.md` §3).
         *
         * A chain is the WHOLE scene, not the part of it that is owed today: the dialogue screen
         * plays the conversation from its first line, and a role line whose card closed «понимаю»
         * last week still has to be heard for the exchange after it to make sense. So the turns
         * outnumber the tasks, and the client matches them by `term_id` — a turn with a task is a
         * turn where the learner answers, a turn without one is a turn that simply happens.
         *
         * Empty when the sitting has no dialogue section at all: the warm-up, the final day's
         * run-through, and the first day of a plan, whose scene has only just been introduced
         * (канон §10 — «диалог по сцене открывается на следующий календарный день»).
         *
         * @var list<PlanDialogueView>
         */
        public array $dialogues = [],
        /**
         * ВСЁ, ЧТО ЭТА ПОСАДКА МОЖЕТ СЫГРАТЬ ФАЙЛОМ — реплики и спасатели, по одной строке на
         * каждую (наряд TTS-1, Ч.1.3 и Ч.2.1).
         *
         * Отдельным списком, а не полем на карточке, по двум причинам. Первая — предзагрузка:
         * телефон качает озвучку ВСЕЙ посадки на входе в день, включая реплики второго присеста и
         * спасателей, которых сегодня может не быть ни на одной карточке, — и для этого ему нужен
         * список, а не обход задач. Вторая — подача: голос в приложении вызывается ТЕКСТОМ (у
         * `Pronouncer` нет и не должно быть понятия «карточка»), поэтому пара «текст → файл» и есть
         * ровно то, чем клиент пользуется.
         *
         * Пустой список — «озвучки нет», и это законный ответ: выключенная труба, язык без голоса
         * в пакете, или файлы ещё не догнали день.
         *
         * @var list<PlanLineAudioView>
         */
        public array $lineAudio = [],
    ) {}
}

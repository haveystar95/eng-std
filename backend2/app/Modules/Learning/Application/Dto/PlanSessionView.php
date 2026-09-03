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
    ) {}
}

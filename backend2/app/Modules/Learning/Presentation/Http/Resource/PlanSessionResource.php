<?php

declare(strict_types=1);

namespace App\Modules\Learning\Presentation\Http\Resource;

use App\Modules\Learning\Application\Dto\PlanSessionTaskView;
use App\Modules\Learning\Application\Dto\PlanSessionView;
use App\Modules\Learning\Application\Dto\SessionView;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A plan session on the wire.
 *
 * `tasks` and not `cards`, and the difference is the point: each entry carries the app's ordinary
 * card VERBATIM under `card`, with the plan's own envelope around it. A client that already knows
 * how to play a card plays these; the envelope is what lets it also say «слово из дня 1, ступень B,
 * 3 из 4» and «эта карточка стала мягче».
 *
 * The card body is rendered by {@see SessionResource}, so the two payloads cannot drift: a field
 * added to the study card appears here the same day.
 */
final class PlanSessionResource extends JsonResource
{
    /** @param PlanSessionView $resource */
    public function __construct(PlanSessionView $resource)
    {
        parent::__construct($resource);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var PlanSessionView $view */
        $view = $this->resource;

        return [
            'session_id' => $view->sessionId,
            'plan_id' => $view->planId,
            'day_index' => $view->dayIndex,
            // FALSE means this day was opened out of turn: an ordinary soft run over its material,
            // which schedules nothing and closes no stage.
            'strict' => $view->strict,
            'focus_day_index' => $view->focusDayIndex,
            // The level's six, as this session ran on them — including the ones no trainer reads
            // yet, which every task names for itself under `knobs_ignored`.
            'knobs' => $view->knobs,
            // WHERE THE SEAM FALLS. `tasks` is ordered day-first, so the first `day_task_count`
            // entries are this plan's own material and the rest are the top-up from the learner's
            // ordinary queue. A client counts «день пройден» out of THIS number — counting out of
            // `tasks` is how a day of fourteen was announced as twenty-one.
            'day_task_count' => $view->dayTaskCount,
            'tasks' => array_map(static fn (PlanSessionTaskView $task): array => [
                'stage' => $task->stage,
                // `day` | `review` — the same fact as «`from_day_index` is not null», said once
                // here so every client does not re-derive it (and get it wrong).
                'section' => $task->section,
                // «Отпуск в Италии» — where a REVIEW card came from, so the learner is not handed
                // a word out of nowhere in the middle of a plan's lesson. Null on the day's own
                // cards, which need no explanation.
                'origin' => $task->origin,
                'ordinal' => $task->ordinal,
                'of_steps' => $task->ofSteps,
                'from_day_index' => $task->fromDayIndex,
                'softened' => $task->softened,
                'source' => $task->source,
                'speaking_form' => $task->speakingForm,
                // Where a cloze card cuts its gap: the day's own frame when there is one, the
                // card's example otherwise. Null on every other trainer.
                'cloze_source' => $task->clozeSource,
                'speaker' => $task->speaker,
                'knobs_applied' => $task->knobsApplied,
                'knobs_ignored' => $task->knobsIgnored,
                'card' => self::card($task),
            ], $view->tasks),
        ];
    }

    /** @return array<string, mixed> */
    private static function card(PlanSessionTaskView $task): array
    {
        $rendered = (new SessionResource(new SessionView('', [$task->card])))->toArray(request());

        /** @var list<array<string, mixed>> $cards */
        $cards = $rendered['cards'];

        return $cards[0];
    }
}

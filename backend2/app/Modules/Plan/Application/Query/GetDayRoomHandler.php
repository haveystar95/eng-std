<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Application\Dto\DayMetricsView;
use App\Modules\Plan\Application\Dto\DayRoomView;
use App\Modules\Plan\Application\Dto\ProgramUnitView;
use App\Modules\Plan\Application\Dto\StageProgressView;
use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Application\Service\PlanViews;
use App\Modules\Plan\Application\Service\UnitNames;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\DayType;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Shared\Domain\Service\Clock;

/** «Кабинет дня»: three queries — the plan, its cards, nothing else. */
final readonly class GetDayRoomHandler
{
    public function __construct(
        private PlanAccess $access,
        private DayCardRepository $cards,
        private PlanViews $views,
        private LearnerCalendar $calendar,
        private Clock $clock,
    ) {}

    public function __invoke(GetDayRoom $query): DayRoomView
    {
        $plan = $this->access->owned($query->planId, $query->actorId);
        $day = $plan->day($query->number);
        $today = $this->calendar->todayFor($query->actorId, $this->clock->now());
        $scene = $plan->sceneOf($day);
        $cards = $this->cards->forDay($day->id());
        $metrics = $day->metrics();

        return new DayRoomView(
            planId: $plan->id()->value,
            day: $this->views->day($plan, $day, $today),
            scene: $scene === null ? null : $this->views->scene($plan, $scene),
            goalsNative: $scene?->goalsNative() ?? [],
            stages: $this->stages($cards),
            metrics: $day->isClosed() ? new DayMetricsView(
                $metrics->cardsTotal, $metrics->cardsDone, $metrics->minutesSpent, $metrics->firstTryShare,
                $metrics->hardestUnitKind?->value, $metrics->hardestUnitRef, $metrics->hardestUnitText,
            ) : null,
            program: $this->program($cards),
            sheetAvailable: $day->type() === DayType::Scene && $scene !== null && $scene->isReady(),
        );
    }

    /**
     * Every stage in order; the first one with an unanswered card is `current`, the ones before it
     * `done`, the ones after `locked`, and a stage with no cards `absent`.
     *
     * @param  list<DayCard>  $cards
     * @return list<StageProgressView>
     */
    private function stages(array $cards): array
    {
        $totals = [];
        $done = [];
        foreach ($cards as $card) {
            $key = $card->stage()->value;
            $totals[$key] = ($totals[$key] ?? 0) + 1;
            $done[$key] = ($done[$key] ?? 0) + ($card->isAnswered() ? 1 : 0);
        }

        $out = [];
        $currentFound = false;
        foreach (Stage::ordered() as $stage) {
            $total = $totals[$stage->value] ?? 0;
            $answered = $done[$stage->value] ?? 0;
            $state = match (true) {
                $total === 0 => StageProgressView::ABSENT,
                $answered >= $total => StageProgressView::DONE,
                $currentFound => StageProgressView::LOCKED,
                default => StageProgressView::CURRENT,
            };
            if ($state === StageProgressView::CURRENT) {
                $currentFound = true;
            }
            $out[] = new StageProgressView($stage->value, $total, $answered, $state);
        }

        return $out;
    }

    /**
     * The program: one line per unit (word, phrase, exchange) with how far its cards have gone.
     *
     * @param  list<DayCard>  $cards
     * @return list<ProgramUnitView>
     */
    private function program(array $cards): array
    {
        $units = [];
        foreach ($cards as $card) {
            $payload = $card->payload();
            $sceneId = is_string($payload['scene_id'] ?? null) ? $payload['scene_id'] : '';
            $key = $sceneId.':'.$card->unitKind()->value.':'.$card->unitRef();
            if (! isset($units[$key])) {
                $units[$key] = [
                    'kind' => $card->unitKind()->value,
                    'ref' => $card->unitRef(),
                    'scene' => $sceneId,
                    'text_target' => UnitNames::of($card),
                    'text_native' => is_string($payload['text_native'] ?? null)
                        ? $payload['text_native']
                        : (is_string($payload['task_native'] ?? null) ? $payload['task_native'] : (is_string($payload['prompt_native'] ?? null) ? $payload['prompt_native'] : null)),
                    'source' => $card->source()->value,
                    'total' => 0,
                    'done' => 0,
                    'failed' => false,
                ];
            }
            $units[$key]['total']++;
            if ($card->isAnswered()) {
                $units[$key]['done']++;
            }
            if ($card->result() === CardResult::Failed && $card->returns()) {
                $units[$key]['failed'] = true;
            }
        }

        return array_values(array_map(static fn (array $u): ProgramUnitView => new ProgramUnitView(
            unitKind: $u['kind'],
            unitRef: $u['ref'],
            sceneId: $u['scene'],
            textTarget: $u['text_target'],
            textNative: $u['text_native'],
            source: $u['source'],
            cardsTotal: $u['total'],
            cardsDone: $u['done'],
            state: match (true) {
                $u['failed'] => ProgramUnitView::FAILED,
                $u['done'] >= $u['total'] => ProgramUnitView::PASSED,
                default => ProgramUnitView::PENDING,
            },
        ), $units));
    }
}

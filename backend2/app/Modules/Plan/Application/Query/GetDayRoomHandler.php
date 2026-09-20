<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Query;

use App\Modules\Plan\Application\Dto\CardView;
use App\Modules\Plan\Application\Dto\DayMetricsView;
use App\Modules\Plan\Application\Dto\DayRoomView;
use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Dto\ProgramUnitView;
use App\Modules\Plan\Application\Dto\StageProgressView;
use App\Modules\Plan\Application\Port\LearnerCalendar;
use App\Modules\Plan\Application\Service\CardViews;
use App\Modules\Plan\Application\Service\DayDealer;
use App\Modules\Plan\Application\Service\DayWindowViews;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Application\Service\PlanViews;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\Entity\Conversation;
use App\Modules\Plan\Domain\Repository\ConversationRepository;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Service\ConversationRules;
use App\Modules\Plan\Domain\Service\DayStages;
use App\Modules\Plan\Domain\ValueObject\ConversationState;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Shared\Domain\Service\Clock;

/**
 * «Кабинет дня»: the plan, its cards, nothing else.
 *
 * A day that has not been opened yet has no cards in the table — but it has a SHAPE, from the
 * moment its lesson is written: the same stages over the same words, phrases and exchanges that
 * `POST …/open` will deal. The room asks the dealer for that outline instead of reporting five
 * absent stages and an empty programme at a day the learner is looking straight at. `open` moves
 * the day's status; it is not what makes the day exist.
 *
 * A dealt day also carries its cards in every stage, in the registry's envelope (наряд SESSION-1a,
 * D-04) — the outline's cards are never shown: they have no ids to answer.
 */
final readonly class GetDayRoomHandler
{
    public function __construct(
        private PlanAccess $access,
        private DayCardRepository $cards,
        private ConversationRepository $conversations,
        private DayDealer $dealer,
        private PlanViews $views,
        private DayWindowViews $windows,
        private LearnerCalendar $calendar,
        private Clock $clock,
        private CardViews $cardViews,
        private LanguagePacks $packs,
        private PlanConfig $config,
        private ConversationRules $rules,
    ) {}

    public function __invoke(GetDayRoom $query): DayRoomView
    {
        $plan = $this->access->owned($query->planId, $query->actorId);
        $day = $plan->day($query->number);
        $today = $this->calendar->todayFor($query->actorId, $this->clock->now());
        $scene = $plan->sceneOf($day);
        $dealt = $day->openedAt() !== null;
        $cards = $dealt ? $this->cards->forDay($day->id()) : $this->dealer->outline($plan, $day);
        $talk = $dealt ? $this->conversations->latestForDay($day->id()) : null;
        $metrics = $day->metrics();
        $route = $this->views->day($plan, $day, $today, null, $cards);
        $sceneView = $scene === null ? null : $this->views->scene($plan, $scene);

        return new DayRoomView(
            planId: $plan->id()->value,
            day: $route,
            scene: $sceneView,
            stages: $this->stages(
                $cards,
                $dealt ? $this->cardViews->forCards($cards, $plan->targetLang()->value, self::dayNumbers($plan->days())) : [],
                DayStages::walksConversation($day, $this->rules->enabled),
                $talk,
            ),
            // The numbers of a day that is being walked, not only of one that is over: they are
            // refreshed on every answer, and «сколько уже сделано» is the question of a day in
            // progress. A day not yet opened has nothing to count.
            metrics: $dealt ? new DayMetricsView($metrics->cardsTotal, $metrics->minutesSpent) : null,
            program: $this->program($cards),
            window: $this->windows->of($plan, $day, $plan->effectiveDayStatus($day, $today), $plan->isDayBuilding($day), $sceneView, $cards, $talk),
            speech: $this->packs->for($plan->targetLang()->value)->speech(),
            repeatMisses: $this->config->repeatMisses,
        );
    }

    /**
     * Every stage in order; the first one with an unanswered card is `current`, the ones before it
     * `done`, the ones after `locked`, and a stage with no cards `absent`. Each with its dealt cards in
     * position order — none for a day not opened.
     *
     * The sixth stage (наряд CONV-1) has no cards, so it carries none: its row says where the talk
     * stands and its `cards` list is empty — the one place both readings of a day's stages agree.
     *
     * @param  list<DayCard>  $cards
     * @param  list<CardView>  $views  the views of a dealt day's cards, in walking order; empty for the outline
     * @return list<StageProgressView>
     */
    private function stages(array $cards, array $views, bool $walksTalk, ?Conversation $talk): array
    {
        $byStage = [];
        foreach ($views as $view) {
            $byStage[$view->stage][] = $view;
        }
        foreach ($byStage as $stage => $stageViews) {
            usort($stageViews, static fn (CardView $a, CardView $b): int => $a->position <=> $b->position);
            $byStage[$stage] = $stageViews;
        }

        $totals = [];
        $done = [];
        foreach ($cards as $card) {
            $key = $card->stage()->value;
            $totals[$key] = ($totals[$key] ?? 0) + 1;
            $done[$key] = ($done[$key] ?? 0) + ($card->isAnswered() ? 1 : 0);
        }

        $out = [];
        $currentFound = false;
        foreach (Stage::ofCards() as $stage) {
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
            $out[] = new StageProgressView($stage->value, $total, $answered, $state, $byStage[$stage->value] ?? []);
        }

        // A day with no shape at all — its lesson is not written — shows `absent` and nothing else:
        // «нет урока, нет и формы». The talk's row joins the others once the day has one.
        if ($walksTalk && $cards !== []) {
            $out[] = new StageProgressView(Stage::Conversation->value, 0, 0, match (true) {
                $talk?->state() === ConversationState::Ended => StageProgressView::DONE,
                $talk !== null, ! $currentFound => StageProgressView::CURRENT,
                default => StageProgressView::LOCKED,
            }, []);
        }

        return $out;
    }

    /**
     * The program: one line per unit (word, phrase, exchange) with where its cards have gone. The day's
     * listening is not a unit of it (D-05): nothing of it returns, no tab lists it.
     *
     * @param  list<DayCard>  $cards
     * @return list<ProgramUnitView>
     */
    private function program(array $cards): array
    {
        $units = [];
        foreach ($cards as $card) {
            if ($card->unitKind() === UnitKind::Day) {
                continue;
            }
            $sceneId = $card->payload()['scene_id'] ?? null;
            $key = (is_string($sceneId) ? $sceneId : '').':'.$card->unitKind()->value.':'.$card->unitRef();
            $units[$key] ??= ['kind' => $card->unitKind()->value, 'source' => $card->source()->value, 'total' => 0, 'done' => 0, 'failed' => false];
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
            source: $u['source'],
            state: match (true) {
                $u['failed'] => ProgramUnitView::FAILED,
                $u['done'] >= $u['total'] => ProgramUnitView::PASSED,
                default => ProgramUnitView::PENDING,
            },
        ), $units));
    }

    /**
     * @param  list<PlanDay>  $days
     * @return array<string, int> day id → number
     */
    private static function dayNumbers(array $days): array
    {
        $out = [];
        foreach ($days as $day) {
            $out[$day->id()->value] = $day->number();
        }

        return $out;
    }
}

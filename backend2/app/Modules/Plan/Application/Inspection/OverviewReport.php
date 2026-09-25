<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Application\Dto\Inspection\InspectedCard;
use App\Modules\Plan\Application\Dto\Inspection\InspectedPassage;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\ValueObject\DayStatus;
use App\Modules\Plan\Domain\ValueObject\PlanEventKind;

/**
 * THE PLAN AT A GLANCE (наряд ADM-1): the header — what the plan is, whose, how far along, which prompts and which build
 * made it, what it cost — its row in the learner's list of plans, and the table of its days. Status words are the
 * plan's own: the plan's `effectiveStatus` (overdue derived), a day's `effectiveDayStatus` — `building` when the next day's
 * lesson is not written yet (`isDayBuilding`).
 */
final readonly class OverviewReport
{
    public function __construct(private DayMoney $money) {}

    /**
     * @param  list<AttributedCall>  $calls
     * @return array<string, mixed>
     */
    public function header(PlanInspectionData $data, array $calls): array
    {
        $plan = $data->plan;
        $titles = $plan->titles();
        $cards = $data->cards();

        return [
            'id' => $data->id(),
            'code' => $data->code(),
            'user_id' => $plan->userId()->value,
            'title_native' => $titles?->titleNative,
            'title_target' => $titles?->titleTarget,
            'goal_text' => $plan->goalText(),
            'target_lang' => $plan->targetLang()->value,
            'native_lang' => $plan->nativeLang()->value,
            'level' => $plan->level()->value,
            'event_date' => $plan->eventDate()?->format('Y-m-d'),
            'status' => $plan->effectiveStatus($data->today)->value,
            'stored_status' => $plan->status()->value,
            'created_at' => $plan->createdAt()->format(DATE_ATOM),
            'started_at' => $plan->startedAt()?->format(DATE_ATOM),
            'finished_at' => $plan->finishedAt()?->format(DATE_ATOM),
            'today' => $data->today->format('Y-m-d'),
            'cover_image_url' => $data->row->coverImageUrl,
            'progress' => $this->progress($data),
            'versions' => [
                'plan' => $data->row->promptVersion,
                'lesson' => self::distinct(array_map(static fn ($s): ?string => $s->promptVersion, $data->scenes)),
                'repair' => null,
                'seam_judge' => null,
                'slot_judge' => self::distinct(array_map(static fn (InspectedCard $c): ?string => is_array($c->response['judge'] ?? null) ? self::strOrNull($c->response['judge']['prompt_version'] ?? null) : null, $cards)),
                'conversation' => self::distinct(array_merge(...array_map(static fn ($t): array => array_map(static fn ($turn): ?string => $turn->promptVersion, $t->turns), $data->talks()) ?: [[]])),
            ],
            'models' => [
                'plan' => $data->row->model,
                'lesson' => self::distinct(array_map(static fn ($s): ?string => $s->model, $data->scenes)),
            ],
            'build_versions' => [
                'plan' => $data->row->buildVersion,
                'lessons' => self::distinct(array_map(static fn ($s): ?string => $s->buildVersion, $data->scenes)),
            ],
            'cost_usd' => $this->total($data, $calls),
            'not_stored' => [
                'версия промта P2R у плана/сцены не хранится (только в коде и конфиге)',
                'версия промта судьи швов у сцены не хранится',
            ],
        ];
    }

    /**
     * The plan's row in the learner's list (вкладка «Планы»).
     *
     * @return array<string, mixed>
     */
    public function listRow(PlanInspectionData $data): array
    {
        $titles = $data->plan->titles();

        return [
            'id' => $data->id(),
            'code' => $data->code(),
            'title_native' => $titles?->titleNative,
            'title_target' => $titles?->titleTarget,
            'status' => $data->plan->effectiveStatus($data->today)->value,
            'event_date' => $data->plan->eventDate()?->format('Y-m-d'),
            'created_at' => $data->plan->createdAt()->format(DATE_ATOM),
            'progress' => $this->progress($data),
            'cost_usd' => $this->total($data, []),
        ];
    }

    /**
     * @param  list<AttributedCall>  $calls
     * @return list<array<string, mixed>>
     */
    public function days(PlanInspectionData $data, ?int $number, array $calls): array
    {
        $out = [];
        foreach ($data->daysOf($number) as $day) {
            $scene = $data->sceneRow($day->sceneId()?->value);
            $passed = $data->firstEvent(PlanEventKind::DayPassed, $day->number());
            $money = $this->money->of($data, $day, $calls);
            $out[] = [
                'number' => $day->number(),
                'type' => $day->type()->value,
                'scene_id' => $scene?->id,
                'scene_title_native' => $scene?->titleNative,
                'scene_title_target' => $scene?->titleTarget,
                'opens_on' => $day->opensOn()?->format('Y-m-d'),
                'stored_status' => $day->status()->value,
                'status' => $this->statusOf($data, $day),
                'lesson_status' => $scene?->lessonStatus,
                'opened_at' => $day->openedAt()?->format(DATE_ATOM),
                'closed_at' => $day->closedAt()?->format(DATE_ATOM),
                'cards_total' => $day->metrics()->cardsTotal,
                'cards_done' => $day->metrics()->cardsDone,
                'minutes_spent' => $day->metrics()->minutesSpent,
                // A day whose sixth stage is skipped (наряд ACC-1 §3) has its conversation passage with no talk here.
                'stages_passed' => array_values(array_map(static fn (InspectedPassage $p): array => [
                    'stage' => $p->stage,
                    'passed_at' => $p->passedAt->format(DATE_ATOM),
                    'conversation_id' => $p->conversationId,
                ], array_filter($data->passages(), static fn (InspectedPassage $p): bool => $p->dayId === $day->id()->value))),
                'passed_at' => $passed?->occurredAt->format(DATE_ATOM),
                'cost_usd' => [
                    'generation' => $money['generation']['cost_usd'] ?? null,
                    'voice' => $money['voice']['cost_usd'],
                    'conversation' => $money['conversation']['total_usd'],
                    'slot_judge' => $money['slot_judge']['cost_usd'],
                    'total' => $money['total_usd'],
                ],
            ];
        }

        return $out;
    }

    /** The day's status as the client would be told it today: `building` while its lesson is awaited. */
    public function statusOf(PlanInspectionData $data, PlanDay $day): string
    {
        return $data->plan->isDayBuilding($day) ? 'building' : $data->plan->effectiveDayStatus($day, $data->today)->value;
    }

    /** @return array{current_day: int|null, days_total: int, days_closed: int, days_opened: int} */
    private function progress(PlanInspectionData $data): array
    {
        $days = $data->days();

        return [
            'current_day' => $data->plan->currentDay()?->number(),
            'days_total' => count($days),
            'days_closed' => count(array_filter($days, static fn (PlanDay $d): bool => $d->status() === DayStatus::Closed)),
            'days_opened' => count(array_filter($days, static fn (PlanDay $d): bool => $d->openedAt() !== null)),
        ];
    }

    /**
     * Everything the plan cost: its build, every day's money.
     *
     * @param  list<AttributedCall>  $calls
     */
    private function total(PlanInspectionData $data, array $calls): float
    {
        $sum = (float) $data->row->costUsd;
        foreach ($data->days() as $day) {
            $sum += (float) $this->money->of($data, $day, $calls)['total_usd'];
        }

        return round($sum, 6);
    }

    /**
     * @param  list<string|null>  $values
     * @return list<string>
     */
    private static function distinct(array $values): array
    {
        return array_values(array_unique(array_filter($values, static fn (?string $v): bool => $v !== null && $v !== '')));
    }

    private static function strOrNull(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}

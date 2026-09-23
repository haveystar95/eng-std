<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Application\Dto\Inspection\InspectedCard;
use App\Modules\Plan\Application\Dto\Inspection\InspectedPassage;
use App\Modules\Plan\Application\Port\PlanCallJournal;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\ValueObject\PlanEventKind;

/**
 * HOW THE LEARNER WALKED A DAY (наряд ADM-1, «Прохождение»): card after card in the order they were answered — the kind,
 * what the card expected, what came back (for speech: what the phone's recognition heard — the only recognition text the
 * server ever gets), the verdict and who gave it, the attempts, when — and the day's result as stored: its counters, its
 * walked stages, its `day_passed` line. What the client is: only what it sends — the User-Agent of its last call; it
 * sends no build and no device.
 */
final readonly class PassageReport
{
    public function __construct(private PlanCallJournal $journal) {}

    /** @return array<string, mixed> */
    public function of(PlanInspectionData $data, ?int $number): array
    {
        $last = $this->journal->clientCalls(['api/v1/plans/'.$data->id()], [], null, 1)[0] ?? null;

        return [
            'client' => [
                'user_agent' => $last?->userAgent,
                'last_sync_at' => $last?->occurredAt->format(DATE_ATOM),
                'last_path' => $last?->path,
                'build' => null,
                'device' => null,
            ],
            'days' => array_map(fn (PlanDay $day): array => $this->day($data, $day), $data->daysOf($number)),
            'not_stored' => [
                'версия сборки клиента — клиент её не присылает',
                'устройство — клиент его не присылает (только User-Agent Dart)',
                'сырой текст распознавания и альтернативы — сервер получает только итоговую строку heard',
                'время показа карточки — хранится только время ответа (answered_at)',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function day(PlanInspectionData $data, PlanDay $day): array
    {
        $cards = $data->cardsOfDay($day);
        usort($cards, static fn (InspectedCard $a, InspectedCard $b): int => [$a->answeredAt === null, $a->answeredAt, $a->stage, $a->position]
            <=> [$b->answeredAt === null, $b->answeredAt, $b->stage, $b->position]);
        $passed = $data->firstEvent(PlanEventKind::DayPassed, $day->number());

        return [
            'number' => $day->number(),
            'type' => $day->type()->value,
            'status' => $day->status()->value,
            'opened_at' => $day->openedAt()?->format(DATE_ATOM),
            'closed_at' => $day->closedAt()?->format(DATE_ATOM),
            'summary' => [
                'cards_total' => $day->metrics()->cardsTotal,
                'cards_done' => $day->metrics()->cardsDone,
                'minutes_spent' => $day->metrics()->minutesSpent,
                'passed_at' => $passed?->occurredAt->format(DATE_ATOM),
                'results' => self::tally($cards),
                'stages_passed' => array_values(array_map(static fn (InspectedPassage $p): array => [
                    'stage' => $p->stage, 'passed_at' => $p->passedAt->format(DATE_ATOM), 'conversation_id' => $p->conversationId,
                ], array_filter($data->passages(), static fn (InspectedPassage $p): bool => $p->dayId === $day->id()->value))),
            ],
            'cards' => array_map(fn (InspectedCard $card): array => $this->card($data, $card), $cards),
        ];
    }

    /** @return array<string, mixed> */
    private function card(PlanInspectionData $data, InspectedCard $card): array
    {
        $response = $card->response ?? [];
        $judge = is_array($response['judge'] ?? null) ? $response['judge'] : null;
        unset($response['judge']);

        return [
            'id' => $card->id,
            'stage' => $card->stage,
            'position' => $card->position,
            'kind' => $card->kind,
            'unit_kind' => $card->unitKind,
            'unit_ref' => $card->unitRef,
            'source' => $card->source,
            'from_day' => $card->sourceDayId === null ? null : $data->dayNumberOf($card->sourceDayId),
            'retry_of' => $card->retryOf,
            'expected' => ExpectedAnswer::of($card->payload),
            'answer' => $response === [] ? null : $response,
            'heard' => is_string($response['heard'] ?? null) ? $response['heard'] : null,
            'judge' => $judge,
            'result' => $card->result,
            'attempts' => $card->attempts,
            'answered_at' => $card->answeredAt?->format(DATE_ATOM),
            'returns' => $card->returns,
            'payload' => $card->payload,
        ];
    }

    /**
     * @param  list<InspectedCard>  $cards
     * @return array<string, int>
     */
    private static function tally(array $cards): array
    {
        $out = ['passed' => 0, 'hinted' => 0, 'failed' => 0, 'skipped' => 0, 'unanswered' => 0];
        foreach ($cards as $card) {
            $out[$card->result ?? 'unanswered'] = ($out[$card->result ?? 'unanswered'] ?? 0) + 1;
        }

        return $out;
    }
}

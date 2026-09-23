<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Domain\Entity\PlanDay;

/**
 * WHAT THE PLAN COST, STAGE BY STAGE AND DAY BY DAY (наряд ADM-1, «Деньги»), against the canon: a day ≈ $0.16 = generation
 * ≈ $0.08 + voice ≈ $0.08, repairs ≤ 10 % of the day's generation. Models in tokens there and back and $, the voice in
 * credits · characters · $. Every figure is a stored bill ({@see DayMoney}).
 */
final readonly class MoneyReport
{
    public function __construct(private DayMoney $money, private InspectionCanon $canon) {}

    /**
     * @param  list<AttributedCall>  $calls
     * @return array<string, mixed>
     */
    public function of(PlanInspectionData $data, ?int $number, array $calls): array
    {
        $days = array_map(fn (PlanDay $day): array => $this->money->of($data, $day, $calls), $data->daysOf($number));
        $planCalls = array_values(array_filter($calls, static fn (AttributedCall $c): bool => $c->window->kind === 'plan'));
        $planCertain = $planCalls !== [] && array_reduce($planCalls, static fn (bool $ok, AttributedCall $c): bool => $ok && $c->certain(), true);
        $build = $number === null ? round((float) $data->row->costUsd, 6) : 0.0;

        $sum = static fn (callable $pick): float => round(array_sum(array_map($pick, $days)), 6);
        $isum = static fn (callable $pick): int => array_sum(array_map($pick, $days));

        return [
            'canon' => $this->canon->toArray(),
            'plan_build' => [
                'cost_usd' => $data->row->costUsd === null ? null : round((float) $data->row->costUsd, 6),
                'model' => $data->row->model,
                'attempts' => $data->row->attempts,
                'tokens_in' => $planCertain ? array_sum(array_map(static fn (AttributedCall $c): int => (int) $c->call->tokensIn, $planCalls)) : null,
                'tokens_out' => $planCertain ? array_sum(array_map(static fn (AttributedCall $c): int => (int) $c->call->tokensOut, $planCalls)) : null,
                'included' => $number === null,
            ],
            'days' => $days,
            'totals' => [
                'plan_build_usd' => $build,
                'generation_usd' => $sum(static fn (array $d): float => (float) ($d['generation']['cost_usd'] ?? 0.0)),
                'voice' => [
                    'lines' => $isum(static fn (array $d): int => (int) $d['voice']['lines']),
                    'characters' => $isum(static fn (array $d): int => (int) $d['voice']['characters']),
                    'credits' => $isum(static fn (array $d): int => (int) $d['voice']['credits']),
                    'cost_usd' => $sum(static fn (array $d): float => (float) $d['voice']['cost_usd']),
                ],
                'conversation' => [
                    'model_usd' => $sum(static fn (array $d): float => (float) $d['conversation']['model_usd']),
                    'tokens_in' => $isum(static fn (array $d): int => (int) $d['conversation']['tokens_in']),
                    'tokens_out' => $isum(static fn (array $d): int => (int) $d['conversation']['tokens_out']),
                    'speech_usd' => $sum(static fn (array $d): float => (float) $d['conversation']['speech_usd']),
                    'characters' => $isum(static fn (array $d): int => (int) $d['conversation']['characters']),
                    'credits' => $isum(static fn (array $d): int => (int) $d['conversation']['credits']),
                    'total_usd' => $sum(static fn (array $d): float => (float) $d['conversation']['total_usd']),
                ],
                'slot_judge' => [
                    'calls' => $isum(static fn (array $d): int => (int) $d['slot_judge']['calls']),
                    'tokens_in' => $isum(static fn (array $d): int => (int) $d['slot_judge']['tokens_in']),
                    'tokens_out' => $isum(static fn (array $d): int => (int) $d['slot_judge']['tokens_out']),
                    'cost_usd' => $sum(static fn (array $d): float => (float) $d['slot_judge']['cost_usd']),
                ],
                'total_usd' => round($build + $sum(static fn (array $d): float => (float) $d['total_usd']), 6),
            ],
            'not_stored' => [
                'цена фото — не хранится нигде (Pexels бесплатен)',
                'распознавание речи — на телефоне, сервер за него не платит и его не учитывает',
                'разбивка цены урока на урок / P2R / судью швов хранится только в журнале вызовов без ссылки на план — дана, только когда окно сборки не пересекается с чужими',
            ],
        ];
    }
}

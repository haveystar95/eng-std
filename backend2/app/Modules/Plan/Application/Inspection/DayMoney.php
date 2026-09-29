<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Inspection;

use App\Modules\Plan\Application\Dto\Inspection\InspectedAudio;
use App\Modules\Plan\Application\Dto\Inspection\InspectedCard;
use App\Modules\Plan\Domain\Entity\PlanDay;
use App\Modules\Plan\Domain\ValueObject\DayType;

/**
 * WHAT A DAY COST, FROM WHAT IS STORED (наряд ADM-1, «Деньги»). Every figure is a stored bill: the scene's lesson
 * (`cost_usd_lesson` — every attempt, repair and seam judge), its bought lines (`plan_line_audios`: characters · credits ·
 * $), the talks (`conversation_turns`: the model's tokens and $, the voice's characters · credits · $), the slot judges
 * (`day_cards.response.judge`). The split of the lesson's money into lesson / repair / judge exists only in the call
 * journal, so it is given only when every call of the scene's window is certainly the plan's; otherwise null. Photos
 * have no price anywhere (Pexels bills nothing) and the phone's speech recognition costs the server nothing.
 */
final readonly class DayMoney
{
    public function __construct(private InspectionCanon $canon) {}

    /**
     * @param  list<AttributedCall>  $calls
     * @return array<string, mixed>
     */
    public function of(PlanInspectionData $data, PlanDay $day, array $calls): array
    {
        $scene = $day->type() === DayType::Scene ? $data->sceneRow($day->sceneId()?->value) : null;
        $generation = $scene === null ? null : $this->generation($scene->costUsd, $scene->id, $calls);
        $voice = $scene === null ? self::voice([]) : self::voice($data->audiosOfScene($scene->id));
        $talk = $this->talk($data, $day->number());
        $judge = self::judge($data->cardsOfDay($day));

        $build = $generation === null ? null : (float) $generation['cost_usd'] + $voice['cost_usd'];
        $total = ($generation['cost_usd'] ?? 0.0) + $voice['cost_usd'] + $talk['total_usd'] + $judge['cost_usd'];
        $repair = $generation['repair_usd'] ?? null;
        $lessonAndJudge = $generation === null || $repair === null ? null : (float) $generation['cost_usd'] - $repair;

        return [
            'number' => $day->number(),
            'type' => $day->type()->value,
            'generation' => $generation,
            'images' => ['cost_usd' => null],
            'voice' => $voice,
            'conversation' => $talk,
            'slot_judge' => $judge,
            'build_usd' => $build === null ? null : round($build, 6),
            'total_usd' => round($total, 6),
            'over_canon' => $build !== null && $build >= $this->canon->dayUsd * $this->canon->warnRatio,
            'repair_share' => $lessonAndJudge === null || $lessonAndJudge <= 0.0 ? null : round((float) $repair / $lessonAndJudge, 4),
        ];
    }

    /**
     * @param  list<AttributedCall>  $calls
     * @return array<string, mixed>
     */
    private function generation(?string $costUsd, string $sceneId, array $calls): array
    {
        $own = array_values(array_filter($calls, static fn (AttributedCall $c): bool => $c->window->kind === 'scene' && $c->window->subjectId === $sceneId));
        $certain = $own !== [] && array_reduce($own, static fn (bool $ok, AttributedCall $c): bool => $ok && $c->certain(), true);
        /** @param list<string> $purposes */
        $sum = static function (array $purposes) use ($own, $certain): ?float {
            if (! $certain) {
                return null;
            }

            return round(array_sum(array_map(static fn (AttributedCall $c): float => in_array($c->call->purpose, $purposes, true) ? (float) $c->call->costUsd : 0.0, $own)), 6);
        };

        return [
            'cost_usd' => $costUsd === null ? null : round((float) $costUsd, 6),
            'lesson_usd' => $sum(CallAttribution::LESSON_PURPOSES),
            'repair_usd' => $sum(CallAttribution::REPAIR_PURPOSES),
            'judge_usd' => $sum(CallAttribution::JUDGE_PURPOSES),
            'tokens_in' => $certain ? array_sum(array_map(static fn (AttributedCall $c): int => (int) $c->call->tokensIn, $own)) : null,
            'tokens_out' => $certain ? array_sum(array_map(static fn (AttributedCall $c): int => (int) $c->call->tokensOut, $own)) : null,
            'calls' => count($own),
            'certain' => $certain,
        ];
    }

    /**
     * @param  list<InspectedAudio>  $audios
     * @return array{lines: int, characters: int, credits: int, cost_usd: float}
     */
    public static function voice(array $audios): array
    {
        return [
            'lines' => count($audios),
            'characters' => array_sum(array_map(static fn (InspectedAudio $a): int => (int) $a->characters, $audios)),
            'credits' => array_sum(array_map(static fn (InspectedAudio $a): int => (int) $a->credits, $audios)),
            'cost_usd' => round(array_sum(array_map(static fn (InspectedAudio $a): float => (float) $a->costUsd, $audios)), 6),
        ];
    }

    /** @return array{talks: int, turns: int, model_usd: float, tokens_in: int, tokens_out: int, speech_usd: float, characters: int, credits: int, recognition_usd: null, total_usd: float} */
    private function talk(PlanInspectionData $data, int $number): array
    {
        $out = ['talks' => 0, 'turns' => 0, 'model_usd' => 0.0, 'tokens_in' => 0, 'tokens_out' => 0, 'speech_usd' => 0.0, 'characters' => 0, 'credits' => 0, 'recognition_usd' => null, 'total_usd' => 0.0];
        foreach ($data->talksOfDay($number) as $talk) {
            $out['talks']++;
            foreach ($talk->turns as $turn) {
                $out['turns']++;
                $out['model_usd'] += (float) $turn->modelCostUsd;
                $out['speech_usd'] += (float) $turn->speechCostUsd;
                $out['tokens_in'] += (int) $turn->tokensIn;
                $out['tokens_out'] += (int) $turn->tokensOut;
                $out['characters'] += (int) $turn->audioCharacters;
                $out['credits'] += (int) $turn->audioCredits;
            }
        }
        $out['model_usd'] = round($out['model_usd'], 6);
        $out['speech_usd'] = round($out['speech_usd'], 6);
        $out['total_usd'] = round($out['model_usd'] + $out['speech_usd'], 6);

        return $out;
    }

    /**
     * @param  list<InspectedCard>  $cards
     * @return array{calls: int, tokens_in: int, tokens_out: int, cost_usd: float}
     */
    public static function judge(array $cards): array
    {
        $out = ['calls' => 0, 'tokens_in' => 0, 'tokens_out' => 0, 'cost_usd' => 0.0];
        foreach ($cards as $card) {
            $judge = $card->response['judge'] ?? null;
            if (! is_array($judge) || ($judge['by'] ?? null) !== 'model') {
                continue;
            }
            $out['calls']++;
            $out['tokens_in'] += (int) ($judge['tokens_in'] ?? 0);
            $out['tokens_out'] += (int) ($judge['tokens_out'] ?? 0);
            $out['cost_usd'] += (float) ($judge['cost_usd'] ?? 0);
        }
        $out['cost_usd'] = round($out['cost_usd'], 6);

        return $out;
    }
}

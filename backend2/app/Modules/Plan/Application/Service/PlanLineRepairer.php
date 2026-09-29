<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\PlanLineRepairRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Blueprint\SceneBrief;
use App\Modules\Plan\Domain\Check\Blueprint\CharLimitsCheck;
use App\Modules\Plan\Domain\ValueObject\CheckAction;
use App\Modules\Plan\Domain\ValueObject\CheckMode;
use App\Modules\Plan\Domain\ValueObject\Finding;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use Throwable;

/**
 * A SCREEN LINE OVER ITS LIMIT IS SHORTENED, NOT THE PLAN ASKED AGAIN (наряд GEN-4): every line of an accepted plan longer
 * than {@see CharLimitsCheck} allows — the day's name in both languages, the line under it, a goal, the plan's name — goes
 * to a cheap model alone (`plan_line_repair.v1`: «shorten, keeping the meaning and the grammar»), at most {@see MAX_LINES}
 * a plan. The answer is taken when it is a line within the limit; otherwise the line stays as the plan builder wrote it.
 * Every line is a finding `line_repair` — shortened, or still over — and every call is its own purpose in the journal of model
 * calls. A model that does not answer leaves the line as it was: the plan is never held back for a line.
 */
final readonly class PlanLineRepairer
{
    /** The most lines one plan's build shortens — the plans of GEN-4a had at most four over their limits. */
    public const MAX_LINES = 12;

    public const CHECK = 'line_repair';

    public function __construct(private PlanModelPort $model) {}

    public function repair(Blueprint $blueprint, string $nativeLanguage, string $targetLanguage): PlanLineRepairs
    {
        $cost = '0.000000';
        $latency = 0;
        $findings = [];
        foreach (array_slice(CharLimitsCheck::over($blueprint), 0, self::MAX_LINES) as $line) {
            $where = ($line['scene'] === null ? 'plan' : "scene {$line['scene']}").': '.$line['field'].($line['field'] === 'goals_native' ? " {$line['index']}" : '');
            try {
                $reply = $this->model->repairPlanLine(new PlanLineRepairRequest(
                    field: $line['scene'] === null ? 'plan_title_native' : $line['field'],
                    language: $line['native'] ? $nativeLanguage : $targetLanguage,
                    limit: $line['limit'],
                    line: $line['line'],
                ));
            } catch (Throwable $e) {
                $findings[] = self::finding("{$where} «{$line['line']}» (".mb_strlen($line['line']).') not shortened: the model did not answer');

                continue;
            }
            $cost = ModelCall::addCosts($cost, $reply->costUsd);
            $latency += $reply->latencyMs;
            $short = is_string($reply->payload['line'] ?? null) ? trim((string) preg_replace('/\s+/u', ' ', $reply->payload['line'])) : '';
            if ($short === '' || mb_strlen($short) > $line['limit']) {
                $findings[] = self::finding("{$where} «{$line['line']}» (".mb_strlen($line['line']).") still over {$line['limit']}: «{$short}» (".mb_strlen($short).')');

                continue;
            }
            $blueprint = self::put($blueprint, $line, $short);
            $findings[] = self::finding("{$where} «{$line['line']}» (".mb_strlen($line['line']).") → «{$short}» (".mb_strlen($short).')');
        }

        return new PlanLineRepairs($blueprint, $findings, $cost, $latency);
    }

    /** @param array{scene: int|null, field: string, index: int, line: string, limit: int, native: bool} $line */
    private static function put(Blueprint $blueprint, array $line, string $short): Blueprint
    {
        if ($line['scene'] === null) {
            return $blueprint->titles === null ? $blueprint : $blueprint->withTitles($blueprint->titles->withTitleNative($short));
        }

        return $blueprint->withScenes(array_map(
            static fn (SceneBrief $scene): SceneBrief => $scene->order === $line['scene'] ? $scene->withLine($line['field'], $short, $line['index']) : $scene,
            $blueprint->scenes,
        ));
    }

    private static function finding(string $detail): Finding
    {
        return new Finding(self::CHECK, CheckMode::Observe, CheckAction::Counted, $detail);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Exception\PlanModelUnavailable;
use App\Modules\Plan\Application\Port\BuildVersion;
use App\Modules\Plan\Application\Port\CheckCounters;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Blueprint\Blueprint;
use App\Modules\Plan\Domain\Blueprint\BlueprintParser;
use App\Modules\Plan\Domain\Check\BlueprintChecker;
use App\Modules\Plan\Domain\Check\BlueprintContext;
use App\Modules\Plan\Domain\Check\CheckReport;
use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\ValueObject\CheckAction;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use Throwable;

/**
 * ONE PLAN CALL, WITH ITS ONE RETRY. The model is asked; the answer is parsed (off-schema = the
 * model's refusal) and checked; a gate or an off-schema answer buys exactly one more call with
 * the violations quoted as data; the second failure is the plan's failure. Every attempt is paid
 * and every attempt's cost is summed into the call the plan is stamped with.
 */
final readonly class PlanBuildService
{
    public const MAX_ATTEMPTS = 2;

    public function __construct(
        private PlanModelPort $model,
        private BlueprintChecker $checker,
        private BlueprintParser $parser,
        private CheckCounters $counters,
        private BuildVersion $build,
    ) {}

    public function build(PlanRequest $request): PlanBuildOutcome
    {
        $cost = '0.000000';
        $latency = 0;
        $violations = [];
        $lastFindings = [];
        $attempt = 0;

        while (true) {
            $attempt++;
            $reply = $this->ask($request, $violations);
            $cost = ModelCall::addCosts($cost, $reply->costUsd);
            $latency += $reply->latencyMs;
            $call = fn (): ModelCall => new ModelCall($reply->promptVersion, $this->build->current(), $reply->model, $cost, $latency, $attempt);

            try {
                $blueprint = $this->parser->parse($reply->payload);
            } catch (ModelAnswerOffSchema $e) {
                $violations = [$e->getMessage()];
                $lastFindings = [];
                if ($attempt >= self::MAX_ATTEMPTS) {
                    return PlanBuildOutcome::failed($e->getMessage(), $call(), []);
                }

                continue;
            }

            $report = $this->checker->run($blueprint, new BlueprintContext($request->scenesCount, count($request->existingScenes)));
            $this->counters->record($reply->promptVersion, $report->findings);
            $lastFindings = $report->findingsAsArray();

            if (! $report->gated) {
                /** @var CheckReport<Blueprint> $report */
                return $report->answer->isUnclear()
                    ? PlanBuildOutcome::unclear($report->answer->unclearReason ?? '', $call())
                    : PlanBuildOutcome::ok($report->answer, $call(), $lastFindings);
            }

            $violations = $this->gatedDetails($report);
            if ($attempt >= self::MAX_ATTEMPTS) {
                return PlanBuildOutcome::failed('Проверки отбили ответ дважды: '.implode('; ', $violations), $call(), $lastFindings);
            }
        }
    }

    /** @param list<string> $violations */
    private function ask(PlanRequest $request, array $violations): ModelReply
    {
        $withViolations = new PlanRequest(
            $request->goal, $request->targetLanguage, $request->nativeLanguage, $request->level,
            $request->scenesCount, $request->existingScenes, $violations,
        );

        try {
            return $this->model->buildPlan($withViolations);
        } catch (PlanModelUnavailable $e) {
            throw $e;
        } catch (Throwable $e) {
            throw PlanModelUnavailable::because($e->getMessage());
        }
    }

    /**
     * @param  CheckReport<Blueprint>  $report
     * @return list<string>
     */
    private function gatedDetails(CheckReport $report): array
    {
        $out = [];
        foreach ($report->findings as $finding) {
            if ($finding->action === CheckAction::Gated) {
                $out[] = "{$finding->check}: {$finding->detail}";
            }
        }

        return $out;
    }
}

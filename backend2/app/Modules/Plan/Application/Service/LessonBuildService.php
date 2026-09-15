<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Exception\PlanModelUnavailable;
use App\Modules\Plan\Application\Port\BuildVersion;
use App\Modules\Plan\Application\Port\CheckCounters;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\CheckAction;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use Throwable;

/**
 * THE LESSON CALL (`lesson_day.v4.4`). One retry, and only for an answer that is not the schema — the
 * model's refusal. Everything the validator finds is counted and written beside the lesson; warnings never
 * hold the day, the five fatal codes do — the answer goes through {@see LessonGateKeeper}: P2R for at most
 * two cards, the repaired answer stored, or the lesson failed with the fatal code (решение архитектора после
 * GEN-2a). The repairs' cost and time are the lesson's.
 */
final readonly class LessonBuildService
{
    public const MAX_ATTEMPTS = 2;

    public function __construct(
        private PlanModelPort $model,
        private LessonValidator $validator,
        private LessonParser $parser,
        private CheckCounters $counters,
        private BuildVersion $build,
        private LessonGateKeeper $gate,
    ) {}

    public function build(LessonRequest $request): LessonBuildOutcome
    {
        $cost = '0.000000';
        $latency = 0;
        $violations = [];
        $context = LessonCardRepairer::contextOf($request);

        $attempt = 0;
        while (true) {
            $attempt++;
            $reply = $this->ask($request, $violations);
            $cost = ModelCall::addCosts($cost, $reply->costUsd);
            $latency += $reply->latencyMs;
            $call = new ModelCall($reply->promptVersion, $this->build->current(), $reply->model, $cost, $latency, $attempt);

            try {
                $answer = $this->parser->parse($reply->payload);
            } catch (ModelAnswerOffSchema $e) {
                $violations = [$e->getMessage()];
                if ($attempt >= self::MAX_ATTEMPTS) {
                    return LessonBuildOutcome::failed($e->getMessage(), $call);
                }

                continue;
            }

            $found = $this->validator->run($answer, $context);
            $this->counters->recordCodes($reply->promptVersion, self::codes($found));

            $passed = $this->gate->pass($answer, $found, $context, $request);
            $this->counters->recordCodes($reply->promptVersion, self::codes($passed->gated), CheckAction::Gated);
            $call = $call->plusCost($passed->repairCostUsd, $passed->repairLatencyMs);
            if ($passed->answer === null) {
                $this->counters->recordCodes($reply->promptVersion, self::codes($passed->failedOn), CheckAction::Failed);

                return LessonBuildOutcome::failed((string) $passed->failReason, $call, self::rows($passed->findings));
            }

            return LessonBuildOutcome::ok($passed->answer, $call, self::rows($passed->findings));
        }
    }

    /** @param list<string> $violations */
    private function ask(LessonRequest $request, array $violations): ModelReply
    {
        try {
            return $this->model->buildLesson($request->withViolations($violations));
        } catch (PlanModelUnavailable $e) {
            throw $e;
        } catch (Throwable $e) {
            throw PlanModelUnavailable::because($e->getMessage());
        }
    }

    /**
     * @param  list<LessonViolation>  $violations
     * @return list<string>
     */
    private static function codes(array $violations): array
    {
        return array_map(static fn (LessonViolation $v): string => $v->code, $violations);
    }

    /**
     * @param  list<LessonViolation>  $violations
     * @return list<array{code: string, address: string, detail: string}>
     */
    private static function rows(array $violations): array
    {
        return array_map(static fn (LessonViolation $v): array => $v->toArray(), $violations);
    }
}

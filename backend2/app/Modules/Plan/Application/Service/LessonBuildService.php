<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\LessonSeamVerdict;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Exception\PlanModelUnavailable;
use App\Modules\Plan\Application\Port\BuildVersion;
use App\Modules\Plan\Application\Port\CheckCounters;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\CheckAction;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use Throwable;

/**
 * THE LESSON CALL (`lesson_day.v4.6`). One retry, and only for an answer that is not the schema — the model's
 * refusal; a call that got no answer at all (a timeout) is not retried here or anywhere (наряд GEN-3). The answer is spoken
 * in the roles the plan gives, whatever roles the model wrote (the learner's of the plan, the partner's of the scene).
 * Everything the validator finds — with the story so far, the earlier days of the plan — is counted and written beside the
 * lesson; warnings never hold the day, the nine fatal codes do — the answer goes through {@see LessonGateKeeper}: P2R for
 * at most two cards, the repaired answer stored, or the lesson failed with the fatal code. A check the pair's language
 * packs cannot run is counted as `lang.pack_missing` (once a code, over the model's answer), never as a finding.
 *
 * The lesson that passed the gate is read by the seam judge — once a day, every native sentence of its frames in
 * one call ({@see LessonSeamJudge}); what does not read is a warning `filler.native_seam`. A failed lesson is not
 * judged. The repairs' and the judge's cost and time are the lesson's.
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
        private LessonContexts $contexts,
        private LessonSeamJudge $seams,
    ) {}

    public function build(LessonRequest $request): LessonBuildOutcome
    {
        $cost = '0.000000';
        $latency = 0;
        $violations = [];
        $context = $this->contexts->of($request);

        $attempt = 0;
        while (true) {
            $attempt++;
            $reply = $this->ask($request, $violations);
            $cost = ModelCall::addCosts($cost, $reply->costUsd);
            $latency += $reply->latencyMs;
            $call = new ModelCall($reply->promptVersion, $this->build->current(), $reply->model, $cost, $latency, $attempt);

            try {
                $answer = $this->parser->parse($reply->payload)->withRoles($request->roles);
            } catch (ModelAnswerOffSchema $e) {
                $violations = [$e->getMessage()];
                if ($attempt >= self::MAX_ATTEMPTS) {
                    return LessonBuildOutcome::failed($e->getMessage(), $call);
                }

                continue;
            }

            $found = $this->validator->run($answer, $context);
            $this->counters->recordCodes($reply->promptVersion, self::codes($found));
            $this->counters->recordCodes($reply->promptVersion, array_map(static fn (): string => LessonCodes::LANG_PACK_MISSING, $context->skips->codes()));

            $passed = $this->gate->pass($answer, $found, $context, $request);
            $this->counters->recordCodes($reply->promptVersion, self::codes($passed->gated), CheckAction::Gated);
            $call = $call->plusCost($passed->repairCostUsd, $passed->repairLatencyMs);
            if ($passed->answer === null) {
                $this->counters->recordCodes($reply->promptVersion, self::codes($passed->failedOn), CheckAction::Failed);

                return LessonBuildOutcome::failed((string) $passed->failReason, $call, self::rows($passed->findings));
            }

            $judged = $this->seams->judge($passed->answer, $request->nativeLanguage);
            $this->counters->recordCodes($reply->promptVersion, self::codes($judged->violations));
            if ($judged->status === LessonSeamVerdict::UNAVAILABLE) {
                $this->counters->recordCodes($reply->promptVersion, [LessonCodes::JUDGE_UNAVAILABLE]);
            }
            $call = $call->plusCost($judged->costUsd, $judged->latencyMs);

            return LessonBuildOutcome::ok($passed->answer, $call, self::rows([...$passed->findings, ...$judged->violations]));
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

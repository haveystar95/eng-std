<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Exception\PlanModelUnavailable;
use App\Modules\Plan\Application\Port\BuildVersion;
use App\Modules\Plan\Application\Port\CheckCounters;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use Throwable;

/**
 * THE LESSON CALL (`lesson_day.v4.4`). One retry, and only for an answer that is not the schema — the
 * model's refusal. Everything the validator finds is counted, written beside the lesson and never a
 * reason to refuse it: the day comes out whatever it breaks (observation mode, наряд GEN-2a).
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
    ) {}

    public function build(LessonRequest $request): LessonBuildOutcome
    {
        $cost = '0.000000';
        $latency = 0;
        $violations = [];
        // The validator reads language CODES (`ru`), the prompt reads names («Russian»).
        $context = new LessonValidationContext(
            $request->vocabularyCount,
            $request->dialogueCount,
            $request->nativeLangCode !== '' ? $request->nativeLangCode : $request->nativeLanguage,
            $request->targetLangCode !== '' ? $request->targetLangCode : $request->targetLanguage,
            $request->learnerGender,
        );

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
            $this->counters->recordCodes($reply->promptVersion, array_map(static fn (LessonViolation $v): string => $v->code, $found));

            return LessonBuildOutcome::ok($answer, $call, array_map(static fn (LessonViolation $v): array => $v->toArray(), $found));
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
}

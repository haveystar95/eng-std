<?php

declare(strict_types=1);

namespace App\Modules\Plan\Application\Service;

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Exception\PlanModelUnavailable;
use App\Modules\Plan\Application\Port\BuildVersion;
use App\Modules\Plan\Application\Port\CheckCounters;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Check\CheckReport;
use App\Modules\Plan\Domain\Check\LessonChecker;
use App\Modules\Plan\Domain\Check\LessonContext;
use App\Modules\Plan\Domain\Exception\ModelAnswerOffSchema;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\CheckAction;
use App\Modules\Plan\Domain\ValueObject\ModelCall;
use Throwable;

/** The lesson call with its one retry — the same deal the plan call gets ({@see PlanBuildService}). */
final readonly class LessonBuildService
{
    public const MAX_ATTEMPTS = 2;

    public function __construct(
        private PlanModelPort $model,
        private LessonChecker $checker,
        private LessonParser $parser,
        private CheckCounters $counters,
        private BuildVersion $build,
    ) {}

    public function build(LessonRequest $request): LessonBuildOutcome
    {
        $cost = '0.000000';
        $latency = 0;
        $violations = [];
        $findings = [];
        // The checks read language CODES (`ru`), the prompt reads names («Russian»).
        $context = new LessonContext(
            $request->phrasesCount, $request->vocabularyCount, $request->dialogueCount,
            $request->nativeLangCode !== '' ? $request->nativeLangCode : $request->nativeLanguage,
            $request->targetLangCode !== '' ? $request->targetLangCode : $request->targetLanguage,
        );

        $attempt = 0;
        while (true) {
            $attempt++;
            $reply = $this->ask($request, $violations);
            $cost = ModelCall::addCosts($cost, $reply->costUsd);
            $latency += $reply->latencyMs;
            $call = fn (): ModelCall => new ModelCall($reply->promptVersion, $this->build->current(), $reply->model, $cost, $latency, $attempt);

            try {
                $lesson = $this->parser->parse($reply->payload);
            } catch (ModelAnswerOffSchema $e) {
                $violations = [$e->getMessage()];
                if ($attempt >= self::MAX_ATTEMPTS) {
                    return LessonBuildOutcome::failed($e->getMessage(), $call(), []);
                }

                continue;
            }

            $report = $this->checker->run($lesson, $context);
            $this->counters->record($reply->promptVersion, $report->findings);
            $findings = $report->findingsAsArray();

            if (! $report->gated) {
                /** @var CheckReport<Lesson> $report */
                return LessonBuildOutcome::ok($report->answer, $call(), $findings);
            }

            $violations = [];
            foreach ($report->findings as $finding) {
                if ($finding->action === CheckAction::Gated) {
                    $violations[] = "{$finding->check}: {$finding->detail}";
                }
            }
            if ($attempt >= self::MAX_ATTEMPTS) {
                return LessonBuildOutcome::failed('Проверки отбили урок дважды: '.implode('; ', $violations), $call(), $findings);
            }
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

<?php

declare(strict_types=1);

/**
 * GEN-3 · «БЫЛО»: day 2 on `lesson_day.v4.5` AS IT WAS — run by the code BEFORE the order (commit f02cbc64), on the same plan
 * and the same written day 1 that `live.php plans` left on the e2e database. The v4.5 request knows nothing of day 1 (no
 * roles, no EARLIER_DAYS): the scene's title, its brief with the learner's goal, the pair, the level, the gender, the counts.
 * The validator, the gate (seven fatal codes), P2R v1.1 and the seam judge are the ones that code had. Nothing is written.
 *
 * This file is read by THAT code, so it names only what existed at f02cbc64. It is run from an export of that commit, in a
 * container of its own, with this folder mounted in so the answers land beside the new ones:
 *
 *   git archive f02cbc64 backend2 | tar -x -C /Users/yalantisdenys/gen3-before
 *   cp backend2/.env /Users/yalantisdenys/gen3-before/backend2/.env
 *   docker compose run --rm --no-deps \
 *     -v /Users/yalantisdenys/gen3-before/backend2:/wt -v "$PWD/vendor:/wt/vendor" -v "$PWD/docs/research/gen-3:/wt/docs/research/gen-3" \
 *     -w /wt -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync -e CACHE_STORE=array -e SPEECH_ENABLED=false \
 *     app php docs/research/gen-3/tools/before.php interview rent bank restaurant airport doctor
 */

use App\Modules\Plan\Application\Command\BuildLessonHandler;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;
use App\Modules\Plan\Application\Port\LearnerGender;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Service\LessonBuildService;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\Service\LanguageName;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.pgsql.database');
if ($database !== 'wordtrainer_e2e_test') {
    fwrite(STDERR, "Refusing to run against {$database}: only wordtrainer_e2e_test.\n");
    exit(1);
}
fwrite(STDERR, "database={$database} (code of f02cbc64) lesson=".config('plan.model.lesson_model').' repair='.config('plan.model.repair_model').' timeout='.config('plan.model.lesson_timeout')."\n");

final class RecordingPlanModel implements PlanModelPort
{
    /** @var list<array<string, mixed>> */
    public array $calls = [];

    public function __construct(private readonly PlanModelPort $inner) {}

    public function buildPlan(PlanRequest $request): ModelReply
    {
        throw new LogicException('no plan is built here');
    }

    public function buildLesson(LessonRequest $request): ModelReply
    {
        return $this->record('lesson', ['violations' => $request->previousViolations], fn () => $this->inner->buildLesson($request));
    }

    public function repairLessonCard(LessonCardRepairRequest $request): ModelReply
    {
        return $this->record('repair', ['address' => $request->address, 'kind' => $request->kind, 'findings' => $request->findings, 'card' => $request->card], fn () => $this->inner->repairLessonCard($request));
    }

    public function judgeNativeSeams(NativeSeamJudgeRequest $request): ModelReply
    {
        return $this->record('judge', ['items' => count($request->items)], fn () => $this->inner->judgeNativeSeams($request));
    }

    public function judgeSlot(SlotJudgeRequest $request): ModelReply
    {
        throw new LogicException('the slot judge is not part of this run');
    }

    public function planPromptVersion(): string
    {
        return $this->inner->planPromptVersion();
    }

    public function lessonPromptVersion(): string
    {
        return $this->inner->lessonPromptVersion();
    }

    public function repairPromptVersion(): string
    {
        return $this->inner->repairPromptVersion();
    }

    public function judgePromptVersion(): string
    {
        return $this->inner->judgePromptVersion();
    }

    public function slotJudgePromptVersion(): string
    {
        return $this->inner->slotJudgePromptVersion();
    }

    /** @param array<string, mixed> $asked */
    private function record(string $kind, array $asked, Closure $call): ModelReply
    {
        $at = now()->toIso8601String();
        $reply = $call();
        $this->calls[] = [
            'call' => $kind, 'at' => $at, 'prompt_version' => $reply->promptVersion, 'model' => $reply->model,
            'tokens_in' => $reply->tokensIn, 'tokens_out' => $reply->tokensOut, 'cost_usd' => $reply->costUsd,
            'latency_ms' => $reply->latencyMs, 'asked' => $asked, 'payload' => $reply->payload,
        ];

        return $reply;
    }
}

function json(mixed $value): string
{
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)."\n";
}

$dir = realpath(__DIR__.'/..');
$recorder = new RecordingPlanModel($app->make(PlanModelPort::class));
$app->instance(PlanModelPort::class, $recorder);

foreach (array_slice($argv, 1) as $slug) {
    $recorder->calls = [];
    $run = json_decode((string) file_get_contents("{$dir}/runs/{$slug}.json"), true);
    $plan = $app->make(PlanRepository::class)->findById(PlanId::fromString($run['plan_id']));
    $scene = $plan?->scene(PlanSceneId::fromString($run['scene_ids'][1]));
    if ($plan === null || $scene === null) {
        fwrite(STDOUT, "{$slug}: plan gone\n");
        continue;
    }
    $counts = $app->make(PlanConfig::class)->countsFor($plan->level());
    $request = new LessonRequest(
        topic: $scene->titleNative(),
        topicDescription: BuildLessonHandler::topicDescription($scene->topicDescription(), $plan->goalText()),
        targetLanguage: LanguageName::of($plan->targetLang()->value),
        nativeLanguage: LanguageName::of($plan->nativeLang()->value),
        level: $plan->level(),
        learnerGender: $app->make(LearnerGender::class)->of($plan->userId()),
        vocabularyCount: $counts['vocabulary'],
        dialogueCount: $counts['dialogue'],
        targetLangCode: $plan->targetLang()->value,
        nativeLangCode: $plan->nativeLang()->value,
    );
    $started = now()->toIso8601String();
    $t0 = microtime(true);
    $outcome = $app->make(LessonBuildService::class)->build($request);
    $wall = microtime(true) - $t0;
    $lessonCalls = array_values(array_filter($recorder->calls, static fn (array $c): bool => $c['call'] === 'lesson'));
    file_put_contents("{$dir}/answers/{$slug}-day2-v4.5.json", json($lessonCalls === [] ? null : end($lessonCalls)['payload']));
    file_put_contents("{$dir}/final/{$slug}-day2-v4.5.json", json($outcome->lesson?->toArray()));
    file_put_contents("{$dir}/runs/{$slug}-day2-v4.5.json", json([
        'slug' => $slug, 'version' => 'lesson_day.v4.5', 'code' => 'f02cbc64',
        'status' => $outcome->lesson === null ? 'failed' : 'ready', 'fail_reason' => $outcome->failReason,
        'cost_usd' => $outcome->call?->costUsd, 'latency_ms' => $outcome->call?->latencyMs, 'attempts' => $outcome->call?->attempts,
        'findings' => $outcome->findings, 'wall_s' => round($wall, 1), 'started_at' => $started, 'finished_at' => now()->toIso8601String(),
        'calls' => $recorder->calls,
    ]));
    fwrite(STDOUT, sprintf("%s %s day 2 v4.5 %s%s · calls %s · $%s · %.1fs\n", date('H:i:s'), $slug, $outcome->lesson === null ? 'failed' : 'ready', $outcome->failReason ? " ({$outcome->failReason})" : '', implode(',', array_map(static fn (array $c): string => $c['call'].($c['call'] === 'repair' ? ':'.$c['asked']['address'] : ''), $recorder->calls)), $outcome->call?->costUsd, $wall));
}

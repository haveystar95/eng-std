<?php

declare(strict_types=1);

/**
 * GEN-3 · «БЫЛО / СТАЛО», THE NEW CODE: day 1 and day 2 of a two-scene plan on `lesson_day.v4.6`.
 *
 *   plans <slugs…>  — a QA learner per topic (`qa-gen3-<slug>@wt.test`, no gender), a plan of two days built by
 *                     `plan-builder-v2` through `BuildPlanHandler`, and day 1's lesson through `BuildLessonHandler` — the
 *                     production build: the lesson, the gate with P2R, the seam judge, the lesson WRITTEN into scene 1 (so
 *                     day 2 reads it back as EARLIER_DAYS). No job is queued: images and voice are not bought.
 *   after <slugs…>  — day 2 on v4.6: the request as production builds it (`LessonRequests`: the roles, EARLIER_DAYS from
 *                     the database), through `LessonBuildService` — the gate, P2R and the judge as in battle — NOT written:
 *                     scene 2 stays pending, so the v4.5 day 2 (`before.php`, the pre-order code) reads the same day 1.
 *
 * Every model call is recorded (what was asked, what came back, tokens, the cached part, cost, time). Written:
 * `runs/<slug>.json` (the plan, day 1), `runs/<slug>-day2-v4.6.json`, and the answers — `answers/` as the model wrote
 * them (the last lesson call), `final/` as they passed the gate.
 *
 * ONLY the disposable database:
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync -e CACHE_STORE=array \
 *     -e SPEECH_ENABLED=false app php docs/research/gen-3/tools/live.php plans interview rent bank restaurant airport doctor
 */

use App\Modules\Plan\Application\Command\BuildLesson;
use App\Modules\Plan\Application\Command\BuildLessonHandler;
use App\Modules\Plan\Application\Command\BuildPlan;
use App\Modules\Plan\Application\Command\BuildPlanHandler;
use App\Modules\Plan\Application\Command\CreatePlan;
use App\Modules\Plan\Application\Command\CreatePlanHandler;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Service\LessonBuildService;
use App\Modules\Plan\Application\Service\LessonRequests;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$database = (string) config('database.connections.pgsql.database');
if ($database !== 'wordtrainer_e2e_test') {
    fwrite(STDERR, "Refusing to run against {$database}: only wordtrainer_e2e_test.\n");
    exit(1);
}
fwrite(STDERR, "database={$database} plan.driver=".config('plan.model.driver').' lesson='.config('plan.model.lesson_model').' repair='.config('plan.model.repair_model').' judge='.config('plan.model.judge_model').' timeout='.config('plan.model.lesson_timeout')."\n");

/** Every model call of a day, in order. */
final class RecordingPlanModel implements PlanModelPort
{
    /** @var list<array<string, mixed>> */
    public array $calls = [];

    public function __construct(private readonly PlanModelPort $inner) {}

    public function buildPlan(PlanRequest $request): ModelReply
    {
        return $this->record('plan', ['scenes' => $request->scenesCount], fn () => $this->inner->buildPlan($request));
    }

    public function buildLesson(LessonRequest $request): ModelReply
    {
        return $this->record('lesson', ['violations' => $request->previousViolations, 'earlier_days' => count($request->earlierDays->days), 'roles' => (array) $request->roles], fn () => $this->inner->buildLesson($request));
    }

    public function repairLessonCard(LessonCardRepairRequest $request): ModelReply
    {
        return $this->record('repair', ['address' => $request->address, 'kind' => $request->kind, 'findings' => $request->findings, 'card' => $request->card, 'neighbours' => $request->neighbours], fn () => $this->inner->repairLessonCard($request));
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
            'tokens_in' => $reply->tokensIn, 'cached_tokens' => $reply->cachedTokensIn, 'tokens_out' => $reply->tokensOut,
            'cost_usd' => $reply->costUsd, 'latency_ms' => $reply->latencyMs, 'asked' => $asked, 'payload' => $reply->payload,
        ];

        return $reply;
    }
}

/** No job leaves the run: no images, no voice, no second lesson build. */
final class NoDispatch implements PlanDispatcher
{
    public function buildPlan(PlanId $planId, int $scenesToAdd = 0): void {}

    public function buildLesson(PlanSceneId $sceneId): void {}

    public function attachImages(PlanId $planId): void {}

    public function illustrateScene(PlanSceneId $sceneId): void {}

    public function voiceScene(PlanSceneId $sceneId): void {}
}

function say(string $line): void
{
    fwrite(STDOUT, date('H:i:s').' '.$line."\n");
}

function json(mixed $value): string
{
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)."\n";
}

/** The QA learner of a topic, logged in the way the phone logs in (`/auth/dev`), with the pair ru→en. */
function learner(Kernel $kernel, string $email): UserId
{
    app('cache')->store()->flush();
    $request = Request::create('/api/v1/auth/dev', 'POST', [], [], [], [
        'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
    ], json_encode(['email' => $email, 'device_name' => 'gen-3', 'timezone' => 'Europe/Kyiv'], JSON_THROW_ON_ERROR));
    $response = $kernel->handle($request);
    $decoded = json_decode((string) $response->getContent(), true);
    if ($response->getStatusCode() !== 200 || ! is_array($decoded) || ! isset($decoded['user']['id'])) {
        throw new RuntimeException('dev login failed: '.$response->getStatusCode().' '.$response->getContent());
    }
    DB::table('profiles')->where('user_id', $decoded['user']['id'])->update(['native_language' => 'ru', 'target_language' => 'en', 'gender' => null]);

    return UserId::fromString((string) $decoded['user']['id']);
}

/** @param list<array<string, mixed>> $calls */
function spent(array $calls, string $kind): string
{
    return number_format(array_sum(array_map(static fn (array $c): float => (float) $c['cost_usd'], array_filter($calls, static fn (array $c): bool => $c['call'] === $kind))), 6, '.', '');
}

$dir = realpath(__DIR__.'/..');
foreach (['runs', 'answers', 'final'] as $sub) {
    @mkdir("{$dir}/{$sub}");
}
$topics = require __DIR__.'/topics.php';
$recorder = new RecordingPlanModel($app->make(PlanModelPort::class));
$app->instance(PlanModelPort::class, $recorder);
$app->instance(PlanDispatcher::class, new NoDispatch);

$phase = $argv[1] ?? '';
foreach (array_slice($argv, 2) as $slug) {
    if (! isset($topics[$slug])) {
        say("unknown topic {$slug}");
        continue;
    }
    [$goal, $level] = $topics[$slug];
    $recorder->calls = [];

    if ($phase === 'plans') {
        $suffix = (string) getenv('RUN_SUFFIX');
        $userId = learner($kernel, "qa-gen3-{$slug}{$suffix}@wt.test");
        $t0 = microtime(true);
        $planId = $app->make(CreatePlanHandler::class)(new CreatePlan($userId, $goal, new LanguageCode('en'), PlanLevel::from($level), 2, null));
        $app->make(BuildPlanHandler::class)(new BuildPlan($planId));
        $plan = $app->make(PlanRepository::class)->findById($planId);
        $scenes = $plan?->scenes() ?? [];
        usort($scenes, static fn ($a, $b): int => $a->order() <=> $b->order());
        if ($plan === null || count($scenes) < 2) {
            say("{$slug}: no two-scene plan — ".($plan?->status()->value ?? 'none').' '.($plan?->failReason() ?? $plan?->unclearReason() ?? ''));
            continue;
        }
        $planCalls = $recorder->calls;
        $recorder->calls = [];
        $t1 = microtime(true);
        $dayOneStarted = now()->toIso8601String();
        $app->make(BuildLessonHandler::class)(new BuildLesson($scenes[0]->id()));
        $dayOneFinished = now()->toIso8601String();
        $wall = microtime(true) - $t1;
        $row = DB::table('plan_scenes')->where('id', $scenes[0]->id()->value)->first();
        $lessonCalls = array_values(array_filter($recorder->calls, static fn (array $c): bool => $c['call'] === 'lesson'));
        file_put_contents("{$dir}/answers/{$slug}-day1.json", json($lessonCalls === [] ? null : end($lessonCalls)['payload']));
        file_put_contents("{$dir}/final/{$slug}-day1.json", json($row?->lesson_json === null ? null : json_decode((string) $row->lesson_json, true)));
        file_put_contents("{$dir}/runs/{$slug}.json", json([
            'slug' => $slug, 'goal' => $goal, 'level' => $level, 'user_id' => $userId->value, 'plan_id' => $planId->value,
            'scene_ids' => array_map(static fn ($s): string => $s->id()->value, $scenes),
            'scenes' => array_map(static fn ($s): array => ['title_native' => $s->titleNative(), 'title_target' => $s->titleTarget(), 'partner' => $s->partnerRoleTarget().' / '.$s->partnerRoleNative(), 'brief' => $s->topicDescription()], $scenes),
            'learner_role' => $plan->titles()?->learnerRoleTarget.' / '.$plan->titles()?->learnerRoleNative,
            'plan_call' => ['cost_usd' => spent($planCalls, 'plan'), 'wall_s' => round($t1 - $t0, 1), 'calls' => $planCalls],
            'day1' => [
                'status' => $row?->lesson_status, 'fail_reason' => $row?->fail_reason, 'prompt_version' => $row?->prompt_version_lesson,
                'cost_usd' => $row?->cost_usd_lesson, 'latency_ms' => $row?->latency_ms_lesson, 'attempts' => $row?->attempts_lesson,
                'findings' => json_decode((string) ($row?->checks_json ?? '[]'), true), 'wall_s' => round($wall, 1),
                'started_at' => $dayOneStarted, 'finished_at' => $dayOneFinished, 'calls' => $recorder->calls,
            ],
        ]));
        say(sprintf('%s plan $%s (%.1fs) · day 1 %s%s · lesson $%s repair $%s judge $%s · %.1fs', $slug, spent($planCalls, 'plan'), $t1 - $t0, $row?->lesson_status, $row?->fail_reason ? " ({$row->fail_reason})" : '', spent($recorder->calls, 'lesson'), spent($recorder->calls, 'repair'), spent($recorder->calls, 'judge'), $wall));

        continue;
    }

    if ($phase === 'after') {
        $run = json_decode((string) file_get_contents("{$dir}/runs/{$slug}.json"), true);
        $plan = $app->make(PlanRepository::class)->findById(PlanId::fromString($run['plan_id']));
        $scene = $plan?->scene(PlanSceneId::fromString($run['scene_ids'][1]));
        if ($plan === null || $scene === null) {
            say("{$slug}: plan gone");
            continue;
        }
        $request = $app->make(LessonRequests::class)->for($plan, $scene);
        $started = now()->toIso8601String();
        $t0 = microtime(true);
        $outcome = $app->make(LessonBuildService::class)->build($request);
        $wall = microtime(true) - $t0;
        $lessonCalls = array_values(array_filter($recorder->calls, static fn (array $c): bool => $c['call'] === 'lesson'));
        file_put_contents("{$dir}/answers/{$slug}-day2-v4.6.json", json($lessonCalls === [] ? null : end($lessonCalls)['payload']));
        file_put_contents("{$dir}/final/{$slug}-day2-v4.6.json", json($outcome->lesson?->toArray()));
        file_put_contents("{$dir}/runs/{$slug}-day2-v4.6.json", json([
            'slug' => $slug, 'version' => 'lesson_day.v4.6', 'earlier_days' => count($request->earlierDays->days),
            'roles' => (array) $request->roles,
            'status' => $outcome->lesson === null ? 'failed' : 'ready', 'fail_reason' => $outcome->failReason,
            'cost_usd' => $outcome->call?->costUsd, 'latency_ms' => $outcome->call?->latencyMs, 'attempts' => $outcome->call?->attempts,
            'findings' => $outcome->findings, 'wall_s' => round($wall, 1), 'started_at' => $started, 'finished_at' => now()->toIso8601String(),
            'calls' => $recorder->calls,
        ]));
        say(sprintf('%s day 2 v4.6 %s%s · calls %s · $%s · %.1fs', $slug, $outcome->lesson === null ? 'failed' : 'ready', $outcome->failReason ? " ({$outcome->failReason})" : '', implode(',', array_map(static fn (array $c): string => $c['call'].($c['call'] === 'repair' ? ':'.$c['asked']['address'] : ''), $recorder->calls)), $outcome->call?->costUsd, $wall));

        continue;
    }

    say("unknown phase «{$phase}»: plans | after");
    exit(1);
}

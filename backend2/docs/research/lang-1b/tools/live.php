<?php

declare(strict_types=1);

/**
 * LANG-1b §8 · THREE DAYS ON e2e, THE BRANCH'S CODE (the new gates of §1 with the one automatic rebuild, `lesson_day.v4.9`):
 *
 *   (a) `pl-en:retry` — day 1 of LANG-1 part D's pl→en plan (`01M3DGPN35MXD4HJEF9B5H1868`, 7 builds of 7 failed on
 *       `options.form_mismatch`), built again as the learner's «ещё раз» builds it: `RetryLessonHandler` (the scene back to
 *       `pending`), then `BuildLessonHandler` — no new plan, the same plan and scene the seven failures had;
 *   (b) `de-en:female`, `es-en:female` — a new plan of two days for a QA learner whose profile says `female`, day 1 built on
 *       v4.9: the reading in the native's Latin letters, the formal address, the learner's gender.
 *
 * Everything else is LANG-1's harness (`docs/research/lang-1/tools/live.php`): the QA learner logged in as the phone logs in,
 * the production handlers, every model call recorded (`RecordingPlanModel`), no job queued (`NoDispatch`: no photos, no
 * voice, no second day), the raw answer read again by the validator. A pair's lesson calls are ALL kept (`day1.calls`, the
 * rebuild's too); `answers/<pair>.json` is the LAST lesson answer, `answers/<pair>.all.json` every one of them.
 *
 * MONEY (the order's cap, $0.60 for the three days): before a pair, what this run has spent plus the dearest a pair can be
 * (`LIVE_WORST_PAIR`, $0.30: a plan call and two builds of a lesson with two repairs each and the judge) must fit under
 * `LIVE_CAP` ($0.60) — or the pair is skipped and said so.
 *
 *   docker exec -w /wt -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync -e CACHE_STORE=array \
 *     -e SPEECH_ENABLED=false -e RUN_SUFFIX=-0926b wt_lang1b php docs/research/lang-1b/tools/live.php pl-en:retry de-en:female es-en:female
 *
 * Written under `docs/research/lang-1b/live/` (or `DRY_DIR`): `runs/`, `answers/`, `final/` as LANG-1's `export.php` reads
 * them (`DRY_DIR=docs/research/lang-1b/live php docs/research/lang-1/tools/export.php` writes `days/<pair>.md`).
 */

use App\Modules\Plan\Application\Command\BuildLesson;
use App\Modules\Plan\Application\Command\BuildLessonHandler;
use App\Modules\Plan\Application\Command\BuildPlan;
use App\Modules\Plan\Application\Command\BuildPlanHandler;
use App\Modules\Plan\Application\Command\CreatePlan;
use App\Modules\Plan\Application\Command\CreatePlanHandler;
use App\Modules\Plan\Application\Command\RetryLesson;
use App\Modules\Plan\Application\Command\RetryLessonHandler;
use App\Modules\Plan\Application\Dto\ConversationAgentRequest;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;
use App\Modules\Plan\Application\Port\PlanDispatcher;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Service\LessonContexts;
use App\Modules\Plan\Application\Service\LessonRequests;
use App\Modules\Plan\Domain\Check\Language\PackSkip;
use App\Modules\Plan\Domain\Check\LessonGate;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\Service\LanguageCatalog;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// ── the guards ───────────────────────────────────────────────────────────────────────────────────────────────────────
$database = (string) DB::connection()->getDatabaseName();
$driver = (string) config('plan.model.driver');
if (! ($database === 'wordtrainer_e2e_test' || ($database === 'wordtrainer_lang1b_test' && $driver === 'fake'))) {
    fwrite(STDERR, "Refusing to run against {$database} (driver {$driver}): only wordtrainer_e2e_test, or wordtrainer_lang1b_test with PLAN_MODEL_DRIVER=fake.\n");
    exit(1);
}
if ((bool) config('generation.speech.enabled')) {
    fwrite(STDERR, "Refusing: the voice is on (SPEECH_ENABLED) — pass -e SPEECH_ENABLED=false.\n");
    exit(1);
}
if ((string) config('queue.default') !== 'sync') {
    fwrite(STDERR, 'Refusing: the queue is «'.config('queue.default')."», not sync — pass -e QUEUE_CONNECTION=sync.\n");
    exit(1);
}
// `learner()` flushes the default cache store before every login (the dev door's throttle): only the process's own
// `array` store may be flushed — the sidecar's .env says `database`, and a `redis` store is the one the live stack shares.
if ((string) config('cache.default') !== 'array') {
    fwrite(STDERR, 'Refusing: the cache store is «'.config('cache.default')."», not array — pass -e CACHE_STORE=array.\n");
    exit(1);
}
fwrite(STDERR, "database={$database} plan.driver={$driver} provider=".config('plan.model.provider', 'openai').' plan='.config('plan.model.plan_model').' lesson='.config('plan.model.lesson_model').' repair='.config('plan.model.repair_model').' judge='.config('plan.model.judge_model').' timeout='.config('plan.model.lesson_timeout').' plan.languages='.implode(',', (array) config('plan.languages')).' packs='.implode(',', array_keys((array) config('lesson.lang', [])))."\n");
if ($driver === 'fake') {
    fwrite(STDERR, "NOTE: the FAKE model — every pair gets the same canned English lesson; the numbers prove the harness, not the prompts.\n");
} else {
    fwrite(STDERR, "NOTE: the REAL model ({$driver}) — every call below is paid.\n");
}

/** Every model call of a pair, in order. */
final class RecordingPlanModel implements PlanModelPort
{
    /** @var list<array<string, mixed>> */
    public array $calls = [];

    public function __construct(private readonly PlanModelPort $inner) {}

    public function buildPlan(PlanRequest $request): ModelReply
    {
        return $this->record('plan', [
            'scenes' => $request->scenesCount, 'goal' => $request->goal, 'target_language' => $request->targetLanguage,
            'native_language' => $request->nativeLanguage, 'level' => $request->level->value, 'violations' => $request->previousViolations,
        ], fn () => $this->inner->buildPlan($request));
    }

    public function buildLesson(LessonRequest $request): ModelReply
    {
        return $this->record('lesson', [
            'topic' => $request->topic, 'target_language' => $request->targetLanguage, 'native_language' => $request->nativeLanguage,
            'target_code' => $request->targetLangCode, 'native_code' => $request->nativeLangCode,
            'violations' => $request->previousViolations, 'earlier_days' => count($request->earlierDays->days), 'roles' => (array) $request->roles,
        ], fn () => $this->inner->buildLesson($request));
    }

    public function repairLessonCard(LessonCardRepairRequest $request): ModelReply
    {
        return $this->record('repair', [
            'address' => $request->address, 'kind' => $request->kind, 'findings' => $request->findings,
            'card' => $request->card, 'neighbours' => $request->neighbours,
        ], fn () => $this->inner->repairLessonCard($request));
    }

    public function judgeNativeSeams(NativeSeamJudgeRequest $request): ModelReply
    {
        // Every sentence as it was asked (id, pattern, value, sentence); the verdicts come back in the payload.
        return $this->record('judge', ['native_language' => $request->nativeLanguage, 'items' => $request->items], fn () => $this->inner->judgeNativeSeams($request));
    }

    public function judgeSlot(SlotJudgeRequest $request): ModelReply
    {
        return $this->record('slot_judge', [], fn () => $this->inner->judgeSlot($request));
    }

    public function conversationTurn(ConversationAgentRequest $request): ModelReply
    {
        return $this->record('conversation', [], fn () => $this->inner->conversationTurn($request));
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

    public function conversationPromptVersion(): string
    {
        return $this->inner->conversationPromptVersion();
    }

    /**
     * A call that throws (a timeout, a vendor error) is written too — with its error and the time it took, no payload —
     * and thrown on: the build decides what it means.
     *
     * @param  array<string, mixed>  $asked
     */
    private function record(string $kind, array $asked, Closure $call): ModelReply
    {
        $at = now()->toIso8601String();
        $t = microtime(true);
        try {
            $reply = $call();
        } catch (Throwable $e) {
            $this->calls[] = [
                'call' => $kind, 'at' => $at, 'prompt_version' => null, 'model' => null, 'tokens_in' => null, 'cached_tokens' => null,
                'tokens_out' => null, 'cost_usd' => '0.000000', 'latency_ms' => (int) round((microtime(true) - $t) * 1000), 'call_id' => null,
                'error' => $e::class.': '.mb_substr($e->getMessage(), 0, 500), 'asked' => $asked, 'payload' => null,
            ];

            throw $e;
        }
        $this->calls[] = [
            'call' => $kind, 'at' => $at, 'prompt_version' => $reply->promptVersion, 'model' => $reply->model,
            'tokens_in' => $reply->tokensIn, 'cached_tokens' => $reply->cachedTokensIn, 'tokens_out' => $reply->tokensOut,
            'cost_usd' => $reply->costUsd, 'latency_ms' => $reply->latencyMs, 'call_id' => $reply->callId,
            'asked' => $asked, 'payload' => $reply->payload,
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

    public function voiceRescueKit(PlanId $planId): void {}
}

function say(string $line): void
{
    fwrite(STDOUT, date('H:i:s').' '.$line."\n");
}

function json(mixed $value): string
{
    return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE)."\n";
}

/** The QA learner of a pair, logged in the way the phone logs in (`/auth/dev`), with the pair and no gender. */
function learner(Kernel $kernel, string $email, string $native, string $target, ?string $gender = null): UserId
{
    app('cache')->store()->flush();
    $request = Request::create('/api/v1/auth/dev', 'POST', [], [], [], [
        'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
    ], json_encode(['email' => $email, 'device_name' => 'lang-1', 'timezone' => 'Europe/Kyiv'], JSON_THROW_ON_ERROR));
    $response = $kernel->handle($request);
    $decoded = json_decode((string) $response->getContent(), true);
    if ($response->getStatusCode() !== 200 || ! is_array($decoded) || ! isset($decoded['user']['id'])) {
        throw new RuntimeException('dev login failed (DEV_LOGIN_ENABLED?): '.$response->getStatusCode().' '.mb_substr((string) $response->getContent(), 0, 300));
    }
    DB::table('profiles')->where('user_id', $decoded['user']['id'])->update(['native_language' => $native, 'target_language' => $target, 'gender' => $gender]);
    // Read back, not trusted: a plan created on a profile that did not take the pair is built in the default `ru`.
    $profile = DB::table('profiles')->where('user_id', $decoded['user']['id'])->first(['native_language', 'target_language', 'gender']);
    if ($profile === null || $profile->native_language !== $native || $profile->target_language !== $target || $profile->gender !== $gender) {
        throw new RuntimeException("the profile of {$email} did not take {$native}→{$target} with gender ".($gender ?? 'none').': '.json_encode($profile, JSON_UNESCAPED_UNICODE));
    }

    return UserId::fromString((string) $decoded['user']['id']);
}

/** The lesson request as the export needs it to read the answer again (`--packs-now`) and to speak in the plan's roles. */
function requestSnapshot(LessonRequest $request): array
{
    return [
        'topic' => $request->topic, 'topic_description' => $request->topicDescription,
        'target_language' => $request->targetLanguage, 'native_language' => $request->nativeLanguage,
        'target_lang_code' => $request->targetLangCode, 'native_lang_code' => $request->nativeLangCode,
        'level' => $request->level->value, 'learner_gender' => $request->learnerGender?->value,
        'vocabulary_count' => $request->vocabularyCount, 'dialogue_count' => $request->dialogueCount,
        'roles' => (array) $request->roles, 'earlier_days' => count($request->earlierDays->days),
    ];
}

/**
 * @param  list<array<string, mixed>>  $calls
 * @param  list<string>  $kinds
 */
function spent(array $calls, array $kinds): string
{
    $sum = 0.0;
    foreach ($calls as $c) {
        if (in_array($c['call'], $kinds, true)) {
            $sum += (float) $c['cost_usd'];
        }
    }

    return number_format($sum, 6, '.', '');
}

/**
 * @param  list<array<string, mixed>>  $calls
 * @return array{in: int, cached: int, out: int}
 */
function tokens(array $calls): array
{
    $out = ['in' => 0, 'cached' => 0, 'out' => 0];
    foreach ($calls as $c) {
        $out['in'] += (int) ($c['tokens_in'] ?? 0);
        $out['cached'] += (int) ($c['cached_tokens'] ?? 0);
        $out['out'] += (int) ($c['tokens_out'] ?? 0);
    }

    return $out;
}

// ── the pairs ────────────────────────────────────────────────────────────────────────────────────────────────────────
$pairs = array_slice($argv, 1);
if ($pairs === []) {
    fwrite(STDERR, "usage: live.php <native>-<target>[:female|:male|:retry] … (e.g. pl-en:retry de-en:female es-en:female)\n");
    exit(1);
}
/** The plans of LANG-1 part D a `:retry` builds day 1 of again. */
$RETRY = ['pl-en' => '01M3DGPN35MXD4HJEF9B5H1868'];
$cap = (float) (getenv('LIVE_CAP') ?: '0.60');
$worstPair = (float) (getenv('LIVE_WORST_PAIR') ?: '0.30');

$dry = trim((string) getenv('DRY_DIR'));
$dir = $dry === '' ? (string) realpath(__DIR__.'/../live') : (str_starts_with($dry, '/') ? $dry : base_path($dry));
foreach (['runs', 'answers', 'final'] as $sub) {
    if (! is_dir("{$dir}/{$sub}") && ! mkdir("{$dir}/{$sub}", 0775, true) && ! is_dir("{$dir}/{$sub}")) {
        fwrite(STDERR, "cannot create {$dir}/{$sub}\n");
        exit(1);
    }
}
fwrite(STDERR, "writing to {$dir}\n");

$goals = require __DIR__.'/topics.php';
$recorder = new RecordingPlanModel($app->make(PlanModelPort::class));
$app->instance(PlanModelPort::class, $recorder);
$app->instance(PlanDispatcher::class, new NoDispatch);
$suffix = (string) getenv('RUN_SUFFIX');
$level = 'beginner';
$totals = ['usd' => 0.0, 's' => 0.0, 'pairs' => 0, 'errors' => 0];

foreach ($pairs as $arg) {
    if (preg_match('/^([a-z]{2})-([a-z]{2})(?::(female|male|retry))?$/', $arg, $m) !== 1) {
        say("{$arg}: not a pair <native>-<target>[:female|:male|:retry]");
        continue;
    }
    [, $native, $target] = $m;
    $mode = $m[3] ?? '';
    $pair = "{$native}-{$target}";
    $gender = in_array($mode, ['female', 'male'], true) ? $mode : null;
    if ($mode === 'retry' && ! isset($RETRY[$pair])) {
        say("{$arg}: no plan of part D to build again for {$pair}");
        continue;
    }
    if ($totals['usd'] + $worstPair > $cap) {
        say(sprintf('%s: SKIPPED — spent $%.4f, and a pair may cost up to $%.2f: over the cap $%.2f', $arg, $totals['usd'], $worstPair, $cap));
        continue;
    }
    $unknown = array_values(array_filter([$native, $target], static fn (string $c): bool => ! LanguageCatalog::knows($c)));
    if ($unknown !== [] || ! isset($goals[$native]) || $native === $target) {
        say("{$pair}: skipped — ".match (true) {
            $unknown !== [] => 'the language catalogue does not know «'.implode('», «', $unknown).'»',
            $native === $target => 'the native language and the target are one',
            default => "topics.php has no goal in «{$native}»",
        });
        continue;
    }
    $goal = $goals[$native];
    $recorder->calls = [];
    $totals['pairs']++;
    // Whatever an earlier run of this pair left is overwritten now: a pair that throws never sits beside an old lesson.
    file_put_contents("{$dir}/answers/{$pair}.json", json(null));
    file_put_contents("{$dir}/answers/{$pair}.all.json", json(null));
    file_put_contents("{$dir}/final/{$pair}.json", json(null));
    $phase = 'login';
    $planWall = 0.0;
    $dayWall = 0.0;
    $t0 = null;
    $t1 = null;
    $run = [
        'pair' => $pair, 'mode' => $mode === '' ? 'new' : $mode, 'learner_gender' => $gender,
        'native' => $native, 'target' => $target, 'level' => $level, 'goal' => $goal, 'run_suffix' => $suffix,
        'database' => $database, 'driver' => $driver,
        'models' => ['plan' => config('plan.model.plan_model'), 'lesson' => config('plan.model.lesson_model'), 'repair' => config('plan.model.repair_model'), 'judge' => config('plan.model.judge_model')],
        'prompt_versions' => ['plan' => $recorder->planPromptVersion(), 'lesson' => $recorder->lessonPromptVersion(), 'repair' => $recorder->repairPromptVersion(), 'judge' => $recorder->judgePromptVersion()],
        'packs' => array_keys((array) config('lesson.lang', [])),
        'started_at' => now()->toIso8601String(),
        'error' => null,
    ];
    $planCalls = [];

    try {
        $phase = 'plan';
        $planStarted = now()->toIso8601String();
        $t0 = microtime(true);
        if ($mode === 'retry') {
            // (a): the plan of part D as it stands — its learner, its scenes; day 1 is built again below.
            $planId = PlanId::fromString($RETRY[$pair]);
            $userId = UserId::fromString((string) DB::table('plans')->where('id', $planId->value)->value('user_id'));
            $run['email'] = (string) DB::table('users')->where('id', $userId->value)->value('email');
        } else {
            // (b): a learner of its own, then the two production handlers.
            $email = "qa-lang1b-{$native}-{$target}{$suffix}@wt.test";
            $phase = 'login';
            $userId = learner($kernel, $email, $native, $target, $gender);
            $phase = 'plan';
            $run['email'] = $email;
            $planId = $app->make(CreatePlanHandler::class)(new CreatePlan($userId, $goal, new LanguageCode($target), PlanLevel::from($level), 2, null));
            $app->make(BuildPlanHandler::class)(new BuildPlan($planId));
        }
        $run['user_id'] = $userId->value;
        $run['plan_id'] = $planId->value;
        $planWall = microtime(true) - $t0;
        $planCalls = $recorder->calls;
        $recorder->calls = [];
        $phase = 'after_plan';
        $plan = $app->make(PlanRepository::class)->findById($planId);
        $planRow = DB::table('plans')->where('id', $planId->value)->first();
        $run['plan'] = [
            'status' => $plan?->status()->value, 'fail_reason' => $plan?->failReason(), 'unclear_reason' => $plan?->unclearReason(),
            'native_lang' => $plan?->nativeLang()->value, 'target_lang' => $plan?->targetLang()->value,
            'prompt_version' => $planRow?->prompt_version_plan, 'model' => $planRow?->model_plan, 'cost_usd_plan' => $planRow?->cost_usd_plan,
            'cost_usd' => spent($planCalls, ['plan']), 'wall_s' => round($planWall, 1), 'started_at' => $planStarted, 'finished_at' => now()->toIso8601String(),
            'calls' => $planCalls,
        ];
        if ($plan === null) {
            throw new RuntimeException('the plan is gone after its build');
        }
        if ($plan->nativeLang()->value !== $native || $plan->targetLang()->value !== $target) {
            throw new RuntimeException("the plan was built for {$plan->nativeLang()->value}→{$plan->targetLang()->value}, not {$native}→{$target} (the learner's calendar read the profile before it was set)");
        }
        $scenes = $plan->scenes();
        usort($scenes, static fn (PlanScene $a, PlanScene $b): int => $a->order() <=> $b->order());
        if ($scenes === []) {
            throw new RuntimeException('no scenes — plan '.$plan->status()->value.' '.($plan->failReason() ?? $plan->unclearReason() ?? ''));
        }
        $first = null;
        foreach ($scenes as $s) {
            if ($s->order() === 1) {
                $first = $s;
                break;
            }
        }
        $first ??= $scenes[0];
        $run['scene_ids'] = array_map(static fn (PlanScene $s): string => $s->id()->value, $scenes);
        $run['scene_id'] = $first->id()->value;
        $run['scenes'] = array_map(static fn (PlanScene $s): array => [
            'order' => $s->order(), 'kind' => $s->kind()->value, 'title_native' => $s->titleNative(), 'title_target' => $s->titleTarget(),
            'partner_target' => $s->partnerRoleTarget(), 'partner_native' => $s->partnerRoleNative(), 'brief' => $s->topicDescription(),
        ], $scenes);
        $run['learner_role'] = ['target' => $plan->titles()?->learnerRoleTarget, 'native' => $plan->titles()?->learnerRoleNative];
        $run['plan_title'] = ['native' => $plan->titles()?->titleNative, 'target' => $plan->titles()?->titleTarget];

        // Day 1: the production build of its lesson. Its request is written first — the handler builds the same one — so
        // a day that throws still has its roles and its language codes in the run file.
        $run['request'] = requestSnapshot($app->make(LessonRequests::class)->for($plan, $first));
        $run['status_before'] = DB::table('plan_scenes')->where('id', $first->id()->value)->value('lesson_status');
        $phase = 'day';
        $dayStarted = now()->toIso8601String();
        $t1 = microtime(true);
        if ($mode === 'retry') {
            // The learner's «ещё раз»: the failed scene back to `pending` (the job it queues goes nowhere — NoDispatch).
            $app->make(RetryLessonHandler::class)(new RetryLesson($planId, $first->id(), $userId));
        }
        $app->make(BuildLessonHandler::class)(new BuildLesson($first->id()));
        $dayWall = microtime(true) - $t1;
        $t1 = null;
        $phase = 'after_day';
        $dayCalls = $recorder->calls;
        $row = DB::table('plan_scenes')->where('id', $first->id()->value)->first();
        // The last answer the model gave (a call that threw gave none).
        $answered = array_values(array_filter($dayCalls, static fn (array $c): bool => $c['call'] === 'lesson' && is_array($c['payload'])));
        $rawPayload = $answered === [] ? null : $answered[count($answered) - 1]['payload'];
        $stored = $row?->lesson_json === null ? null : json_decode((string) $row->lesson_json, true);

        // The raw answer read again in the production context: the findings BEFORE any repair, and the pack skips.
        $fresh = $app->make(PlanRepository::class)->findById($planId);
        $scene = $fresh?->scene($first->id());
        $request = $fresh === null || $scene === null ? null : $app->make(LessonRequests::class)->for($fresh, $scene);
        // `skips_known`: the skips are written down while the rules run — an answer that never reached the validator
        // (none came, or it is not the schema) says nothing about the packs, and the export must not print «всё проверено».
        $rawCheck = ['violations' => [], 'fatal' => 0, 'skips' => [], 'skips_known' => false, 'parse_error' => null,
            'native_code' => null, 'target_code' => null];
        if ($request !== null) {
            $context = $app->make(LessonContexts::class)->of($request);
            $rawCheck['native_code'] = $context->native->code;
            $rawCheck['target_code'] = $context->target->code;
            if (! is_array($rawPayload)) {
                $rawCheck['parse_error'] = 'no lesson call answered';
            } else {
                try {
                    $answer = (new LessonParser)->parse($rawPayload)->withRoles($request->roles);
                    $found = $app->make(LessonValidator::class)->run($answer, $context);
                    $rawCheck['violations'] = array_map(static fn (LessonViolation $v): array => $v->toArray(), $found);
                    $rawCheck['fatal'] = count(LessonGate::fatal($found));
                    $rawCheck['skips_known'] = true;
                } catch (Throwable $e) {
                    $rawCheck['parse_error'] = $e::class.': '.$e->getMessage();
                }
            }
            $rawCheck['skips'] = array_map(static fn (PackSkip $s): array => $s->toArray(), $context->skips->all());
            $run['request'] = requestSnapshot($request);
        }

        $findings = json_decode((string) ($row?->checks_json ?? '[]'), true);
        $run['day1'] = [
            'status' => $row?->lesson_status, 'fail_reason' => $row?->fail_reason, 'prompt_version' => $row?->prompt_version_lesson,
            'model' => $row?->model_lesson, 'cost_usd_lesson' => $row?->cost_usd_lesson, 'latency_ms_lesson' => $row?->latency_ms_lesson,
            'attempts' => $row?->attempts_lesson, 'findings' => is_array($findings) ? $findings : [],
            'spent' => [
                'lesson' => spent($dayCalls, ['lesson']), 'repair' => spent($dayCalls, ['repair']), 'judge' => spent($dayCalls, ['judge']),
                'total' => spent($dayCalls, ['lesson', 'repair', 'judge']),
            ],
            'tokens' => tokens($dayCalls),
            'wall_s' => round($dayWall, 1), 'started_at' => $dayStarted, 'finished_at' => now()->toIso8601String(),
            'calls' => $dayCalls,
        ];
        $run['raw_check'] = $rawCheck;
        $run['finished_at'] = now()->toIso8601String();

        file_put_contents("{$dir}/answers/{$pair}.json", json($rawPayload));
        file_put_contents("{$dir}/answers/{$pair}.all.json", json(array_map(static fn (array $c): mixed => $c['payload'], $answered)));
        file_put_contents("{$dir}/final/{$pair}.json", json($stored));
        file_put_contents("{$dir}/runs/{$pair}.json", json($run));
        $phase = 'written';

        $skipSide = static fn (string $side): int => count(array_unique(array_map(static fn (array $s): string => $s['code'], array_filter($rawCheck['skips'], static fn (array $s): bool => $s['side'] === $side))));
        $repairs = array_values(array_filter($dayCalls, static fn (array $c): bool => $c['call'] === 'repair'));
        say(sprintf('%s plan $%s (%.1fs) · day 1 %s%s · lesson $%s · P2R %d $%s%s · judge $%s · raw fatal %d / all %d · pack_missing %s · day $%s (%.1fs) · pair $%.4f',
            $pair, spent($planCalls, ['plan']), $planWall, $row?->lesson_status, $row?->fail_reason ? " ({$row->fail_reason})" : '',
            spent($dayCalls, ['lesson']), count($repairs), spent($dayCalls, ['repair']),
            $repairs === [] ? '' : ' ('.implode(',', array_map(static fn (array $c): string => (string) $c['asked']['address'], $repairs)).')',
            spent($dayCalls, ['judge']), $rawCheck['fatal'], count($rawCheck['violations']),
            $rawCheck['skips_known'] ? $skipSide('native').'/'.$skipSide('target') : '?/?',
            spent($dayCalls, ['lesson', 'repair', 'judge']), $dayWall,
            (float) spent($planCalls, ['plan']) + (float) spent($dayCalls, ['lesson', 'repair', 'judge'])));
    } catch (Throwable $e) {
        $totals['errors']++;
        // The phase that threw decides where its calls go: a plan that threw leaves its calls in the recorder — they are
        // the plan's, never the day's (the spend of a pair is plan + day, each call counted once).
        if ($phase === 'plan') {
            $planCalls = $recorder->calls;
            $recorder->calls = [];
            $planWall = $t0 === null ? 0.0 : microtime(true) - $t0;
        }
        if ($phase === 'day' && $t1 !== null) {
            $dayWall = microtime(true) - $t1;
        }
        $run['error'] = $e::class.': '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine();
        $run['error_phase'] = $phase;
        $run['plan'] ??= [];
        $run['plan']['calls'] = $planCalls;
        $run['plan']['cost_usd'] = spent($planCalls, ['plan']);
        $run['plan']['wall_s'] ??= $phase === 'login' ? null : round($planWall, 1);
        // Calls of the day that were made before it threw (none when the plan threw); `day1`, when written, has them too.
        $run['calls_at_error'] = isset($run['day1']) ? [] : $recorder->calls;
        if (! isset($run['day1']) && $phase === 'day') {
            $run['day_wall_s_at_error'] = round($dayWall, 1);
        }
        $run['finished_at'] = now()->toIso8601String();
        $answered = array_values(array_filter($recorder->calls, static fn (array $c): bool => $c['call'] === 'lesson' && is_array($c['payload'])));
        if ($phase !== 'written') {
            file_put_contents("{$dir}/runs/{$pair}.json", json($run));
            if (! isset($run['day1']) && $answered !== []) {
                file_put_contents("{$dir}/answers/{$pair}.json", json($answered[count($answered) - 1]['payload']));
            }
        }
        $dayCalls = $run['day1']['calls'] ?? $recorder->calls;
        say("{$pair} ERROR ({$phase}) {$run['error']} · spent \$".number_format((float) spent($planCalls, ['plan']) + (float) spent($dayCalls, ['lesson', 'repair', 'judge']), 4, '.', ''));
    }
    // Counted once a pair, whatever happened: the plan's calls and the day's.
    $totals['usd'] += (float) spent($planCalls, ['plan']) + (float) spent($run['day1']['calls'] ?? $recorder->calls, ['lesson', 'repair', 'judge']);
    $totals['s'] += $planWall + $dayWall;
}

say(sprintf('done: %d pair(s), %d with an error · $%.4f · %.1fs', $totals['pairs'], $totals['errors'], $totals['usd'], $totals['s']));
exit(0);

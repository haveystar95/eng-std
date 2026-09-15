<?php

declare(strict_types=1);

/**
 * GEN-2b · THE LIVE DAYS OF `lesson_day.v4.5` — through the lesson build as production runs it.
 *
 * The six topics of GEN-2a take the SAME inputs their v4.4 day had: the scene's title and brief with the learner's own
 * words after it, the level, no gender (a QA account), 8 words and 8 exchanges — read from the GEN-2a scenes on this
 * database. Two new days write their scene first with the plan builder (one scene, the goal in the learner's own
 * language): an airport day for a Romanian learner (beginner) and a doctor's day for a Ukrainian one (intermediate).
 *
 * Every day goes through `LessonBuildService` — one lesson call (a retry only for an answer off the schema), the
 * validator, the gate with live P2R for fatal cards (the cheap repair model), the seam judge once — and every model
 * call on the way is recorded: what was asked, what came back, tokens, cost, time. Nothing is written to a plan or a
 * scene: the raw answer goes to `answers/<slug>.json`, the answer that passed the gate to `final/<slug>.json`, the run
 * to `runs.json`. The counters of the build land in this database's `plan_check_counters`.
 *
 * Run ONLY against the disposable database:
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync -e CACHE_STORE=array \
 *     -e SPEECH_ENABLED=false app php docs/research/gen-2b/tools/live-run.php interview rent bank restaurant airport doctor airport-ro doctor-uk
 */

use App\Modules\Plan\Application\Command\BuildLessonHandler;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Service\LessonBuildService;
use App\Modules\Plan\Application\Service\LessonContexts;
use App\Modules\Plan\Application\Service\PlanBuildService;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\Service\LanguageName;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.pgsql.database');
if ($database === 'wordtrainer') {
    fwrite(STDERR, "Refusing to run against the main database.\n");
    exit(1);
}
fwrite(STDERR, "database={$database} plan.driver=".config('plan.model.driver').' lesson='.config('plan.model.lesson_model').' repair='.config('plan.model.repair_model').' judge='.config('plan.model.judge_model')."\n");

/** The two new days: the goal in the learner's own language, the pair, the level. */
const NEW_DAYS = [
    'airport-ro' => ['ro', 'beginner', 'Check-in la zborul din aeroport: pașaport, bagaj, loc în avion. Zbor cu o valiză și un rucsac'],
    'doctor-uk' => ['uk', 'intermediate', 'Іду до лікаря з сином: у нього третій день температура і болить горло. Треба розповісти симптоми і зрозуміти призначення'],
];

/** Every model call of the run, in order — the port the build asks, with a record of each question and answer. */
final class RecordingPlanModel implements PlanModelPort
{
    /** @var list<array<string, mixed>> */
    public array $calls = [];

    public function __construct(private readonly PlanModelPort $inner) {}

    public function buildPlan(PlanRequest $request): ModelReply
    {
        return $this->record('plan', [], $this->inner->buildPlan($request));
    }

    public function buildLesson(LessonRequest $request): ModelReply
    {
        return $this->record('lesson', ['violations' => $request->previousViolations], $this->inner->buildLesson($request));
    }

    public function repairLessonCard(LessonCardRepairRequest $request): ModelReply
    {
        return $this->record('repair', ['address' => $request->address, 'kind' => $request->kind, 'findings' => $request->findings, 'card' => $request->card], $this->inner->repairLessonCard($request));
    }

    public function judgeNativeSeams(NativeSeamJudgeRequest $request): ModelReply
    {
        return $this->record('judge', ['native' => $request->nativeLanguage, 'items' => $request->items], $this->inner->judgeNativeSeams($request));
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

    /** @param array<string, mixed> $asked */
    private function record(string $kind, array $asked, ModelReply $reply): ModelReply
    {
        $this->calls[] = [
            'call' => $kind,
            'prompt_version' => $reply->promptVersion,
            'model' => $reply->model,
            'tokens_in' => $reply->tokensIn,
            'tokens_out' => $reply->tokensOut,
            'cost_usd' => $reply->costUsd,
            'latency_ms' => $reply->latencyMs,
            'asked' => $asked,
            'payload' => $reply->payload,
        ];

        return $reply;
    }
}

$dir = realpath(__DIR__.'/..');
@mkdir("{$dir}/answers");
@mkdir("{$dir}/final");
$runsFile = "{$dir}/runs.json";
$runs = is_file($runsFile) ? (array) json_decode((string) file_get_contents($runsFile), true) : [];
$gen2a = [];
foreach (json_decode((string) file_get_contents("{$dir}/../gen-2a/runs.json"), true) as $run) {
    $gen2a[$run['slug']] = $run;
}
$json = static fn (mixed $value): string => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";

$recorder = new RecordingPlanModel($app->make(PlanModelPort::class));
$app->instance(PlanModelPort::class, $recorder);
$config = $app->make(PlanConfig::class);

foreach (array_slice($argv, 1) as $slug) {
    $recorder->calls = [];
    $t0 = microtime(true);
    if (isset($gen2a[$slug])) {
        // The same inputs as the GEN-2a day: its scene, its plan's goal.
        $source = $gen2a[$slug];
        $scene = DB::table('plan_scenes')->where('id', $source['scene_id'])->first();
        $plan = DB::table('plans')->where('id', $source['plan_id'])->first();
        if ($scene === null || $plan === null) {
            fwrite(STDERR, "{$slug}: the GEN-2a scene is gone\n");
            continue;
        }
        $level = PlanLevel::from((string) $plan->level);
        [$native, $goal, $title, $brief] = ['ru', (string) $source['goal'], (string) $scene->title_native, (string) $scene->topic_description];
    } elseif (isset(NEW_DAYS[$slug])) {
        [$native, $levelName, $goal] = NEW_DAYS[$slug];
        $level = PlanLevel::from($levelName);
        $built = $app->make(PlanBuildService::class)->build(new PlanRequest($goal, LanguageName::of('en'), LanguageName::of($native), $level, 1));
        $brief = $built->blueprint?->scenes[0] ?? null;
        if ($brief === null) {
            fwrite(STDERR, "{$slug}: no scene — ".($built->failReason ?? $built->unclearReason ?? '?')."\n");
            continue;
        }
        [$title, $brief] = [$brief->titleNative, $brief->topicDescription];
    } else {
        fwrite(STDERR, "unknown day {$slug}\n");
        continue;
    }

    $counts = $config->countsFor($level);
    $request = new LessonRequest(
        topic: $title,
        topicDescription: BuildLessonHandler::topicDescription($brief, $goal),
        targetLanguage: LanguageName::of('en'),
        nativeLanguage: LanguageName::of($native),
        level: $level,
        learnerGender: null,
        vocabularyCount: $counts['vocabulary'],
        dialogueCount: $counts['dialogue'],
        targetLangCode: 'en',
        nativeLangCode: $native,
    );
    $outcome = $app->make(LessonBuildService::class)->build($request);
    $wall = microtime(true) - $t0;

    $lessonCalls = array_values(array_filter($recorder->calls, static fn (array $c): bool => $c['call'] === 'lesson'));
    $raw = $lessonCalls === [] ? null : $lessonCalls[count($lessonCalls) - 1]['payload'];
    file_put_contents("{$dir}/answers/{$slug}.json", $json($raw));
    file_put_contents("{$dir}/final/{$slug}.json", $json($outcome->lesson?->toArray()));

    // What the pair's packs could not check, over the raw answer.
    $context = $app->make(LessonContexts::class)->of($request);
    $rawFindings = $raw === null ? [] : $app->make(LessonValidator::class)->run((new LessonParser)->parse($raw), $context);

    $record = [
        'slug' => $slug,
        'pair' => "{$native}→en",
        'level' => $level->value,
        'goal' => $goal,
        'topic' => $title,
        'topic_description' => $request->topicDescription,
        'source_scene' => $gen2a[$slug]['scene_id'] ?? null,
        'status' => $outcome->lesson === null ? 'failed' : 'ready',
        'fail_reason' => $outcome->failReason,
        'lesson_cost_usd' => $outcome->call?->costUsd,
        'lesson_latency_ms' => $outcome->call?->latencyMs,
        'lesson_attempts' => $outcome->call?->attempts,
        'findings_final' => $outcome->findings,
        'findings_raw' => count($rawFindings),
        'pack_skips' => array_map(static fn ($s): array => $s->toArray(), $context->skips->all()),
        'calls' => array_map(static function (array $c): array {
            unset($c['payload']['phrases'], $c['payload']['dialogue'], $c['payload']['vocabulary'], $c['payload']['listening'], $c['payload']['topic'], $c['payload']['learner_role']);

            return $c;
        }, $recorder->calls),
        'repair_payloads' => array_values(array_map(
            static fn (array $c): array => ['address' => $c['asked']['address'], 'payload' => $c['payload']],
            array_filter($recorder->calls, static fn (array $c): bool => $c['call'] === 'repair'),
        )),
        'wall_s' => round($wall, 1),
        'at' => now()->toIso8601String(),
    ];
    $runs = array_values(array_filter($runs, static fn (array $r): bool => $r['slug'] !== $slug));
    $runs[] = $record;
    file_put_contents($runsFile, $json($runs));

    $sum = static fn (string $kind): string => number_format(array_sum(array_map(static fn (array $c): float => (float) $c['cost_usd'], array_filter($recorder->calls, static fn (array $c): bool => $c['call'] === $kind))), 6, '.', '');
    fwrite(STDOUT, sprintf(
        "%s %s: %s%s · calls %s · lesson $%s · repair $%s · judge $%s · plan $%s · raw findings %d · final %d · pack skips %d · %.1fs\n",
        date('H:i:s'), $slug, $record['status'], $outcome->failReason ? " ({$outcome->failReason})" : '',
        implode(',', array_map(static fn (array $c): string => $c['call'].($c['call'] === 'repair' ? ':'.$c['asked']['address'] : ''), $recorder->calls)),
        $sum('lesson'), $sum('repair'), $sum('judge'), $sum('plan'), count($rawFindings), count($outcome->findings), count($context->skips->codes()), $wall,
    ));
}

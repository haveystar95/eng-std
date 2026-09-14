<?php

declare(strict_types=1);

/**
 * GEN-2a · THE LIVE RUN OF `lesson_day.v4.4`: one day per topic, one lesson call per day, no retries
 * and no repairs counted in — what the model writes is what the validator counts.
 *
 * Each topic is a one-day plan built through the HTTP surface with the queue inline, so `POST /plans`
 * returns when the plan AND its day-1 lesson are written. The run record (plan, scene, cost, time,
 * tokens) is appended to `docs/research/gen-2a/runs.json`; `export.php` writes the readable days.
 *
 * Run ONLY against the disposable database:
 *
 *   docker compose exec -T \
 *     -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync -e CACHE_STORE=array \
 *     -e SPEECH_ENABLED=false \
 *     app php docs/research/gen-2a/tools/live-run.php interview rent bank restaurant airport doctor
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$database = (string) config('database.connections.pgsql.database');
if ($database === 'wordtrainer') {
    fwrite(STDERR, "Refusing to run against the main database.\n");
    exit(1);
}
fwrite(STDERR, "database={$database} queue=".config('queue.default').' plan.driver='.config('plan.model.driver').' lesson_model='.config('plan.model.lesson_model').' speech='.var_export(config('generation.speech.enabled'), true)."\n");

const TOPICS = [
    'interview' => ['Собеседование на позицию менеджера проектов в IT-компании. У меня пять лет опыта, руковожу командой из шести человек', 'intermediate'],
    'rent' => ['Снимаю квартиру в Берлине на год: смотрю квартиру, спрашиваю про депозит, коммунальные платежи и договор. Работаю удалённо, у меня кошка', 'intermediate'],
    'bank' => ['Открываю счёт и банковскую карту в банке. Я студент, приехал учиться на год', 'beginner'],
    'restaurant' => ['Ужин в ресторане с семьёй: заказать еду, спросить про блюда, попросить счёт. У дочки аллергия на орехи', 'beginner'],
    'airport' => ['Регистрация на рейс в аэропорту: паспорт, багаж, место в самолёте. Лечу с одним чемоданом и рюкзаком', 'beginner'],
    'doctor' => ['Иду к врачу с сыном: у него третий день температура и болит горло. Нужно рассказать симптомы и понять назначения', 'intermediate'],
];

/** @return array{0: int, 1: array<string, mixed>} */
function call(Kernel $kernel, string $token, string $method, string $uri, array $body = []): array
{
    app('cache')->store()->flush();
    app('auth')->forgetGuards();
    $request = Request::create($uri, $method, [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        'HTTP_ACCEPT' => 'application/json',
        'CONTENT_TYPE' => 'application/json',
    ], $body === [] ? null : json_encode($body, JSON_THROW_ON_ERROR));
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    $decoded = json_decode((string) $response->getContent(), true);

    return [$response->getStatusCode(), is_array($decoded) ? $decoded : ['raw' => mb_substr((string) $response->getContent(), 0, 300)]];
}

function say(string $line): void
{
    fwrite(STDOUT, date('H:i:s').' '.$line."\n");
}

function token(Kernel $kernel, string $email): string
{
    app('cache')->store()->flush();
    $request = Request::create('/api/v1/auth/dev', 'POST', [], [], [], [
        'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
    ], json_encode(['email' => $email, 'device_name' => 'gen-2a', 'timezone' => 'Europe/Kyiv'], JSON_THROW_ON_ERROR));
    $response = $kernel->handle($request);
    $decoded = json_decode((string) $response->getContent(), true);
    if ($response->getStatusCode() !== 200 || ! is_array($decoded) || ! isset($decoded['token'])) {
        throw new RuntimeException('dev login failed: '.$response->getStatusCode().' '.$response->getContent());
    }
    DB::table('profiles')->where('user_id', $decoded['user']['id'])->update(['native_language' => 'ru', 'target_language' => 'en']);

    return (string) $decoded['token'];
}

$runsFile = __DIR__.'/../runs.json';
$runs = is_file($runsFile) ? (array) json_decode((string) file_get_contents($runsFile), true) : [];
$slugs = array_slice($argv, 1);
$suffix = (string) getenv('RUN_SUFFIX');

foreach ($slugs as $slug) {
    if (! isset(TOPICS[$slug])) {
        say("unknown topic {$slug}");
        continue;
    }
    [$goal, $level] = TOPICS[$slug];
    $token = token($kernel, "qa-gen2a-{$slug}{$suffix}@wt.test");
    // Log ids are ULIDs — time-ordered: only the calls made after this one belong to this topic.
    $lastLog = (string) DB::table('api_request_logs')->max('id');
    say("=== {$slug} ({$level}): «{$goal}»");

    $t0 = microtime(true);
    [$status, $build] = call($kernel, $token, 'POST', '/api/v1/plans', ['goal_text' => $goal, 'target_lang' => 'en', 'level' => $level, 'days_total' => 1]);
    $wall = microtime(true) - $t0;
    say(sprintf('POST /plans → %d in %.1fs: status=%s', $status, $wall, $build['data']['status'] ?? '?'));
    if ($status !== 202 || ($build['data']['status'] ?? null) !== 'ready') {
        say(json_encode($build, JSON_UNESCAPED_UNICODE));
        continue;
    }
    $planId = (string) $build['data']['id'];
    $scene = DB::table('plan_scenes')->where('plan_id', $planId)->orderBy('order')->first();
    $plan = DB::table('plans')->where('id', $planId)->first();

    $calls = DB::table('api_request_logs')->where('direction', 'outbound')->where('purpose', 'plan')->where('id', '>', $lastLog)
        ->orderBy('id')->get(['id', 'duration_ms', 'response_body', 'request_body']);
    $lessonCalls = [];
    foreach ($calls as $row) {
        $request = json_decode((string) $row->request_body, true);
        $system = (string) ($request['messages'][0]['content'] ?? '');
        if (! str_contains($system, 'UNIVERSAL AI LANGUAGE LESSON GENERATOR')) {
            continue;
        }
        $response = json_decode((string) $row->response_body, true);
        $lessonCalls[] = [
            'log_id' => $row->id,
            'duration_ms' => (int) $row->duration_ms,
            'tokens_in' => (int) ($response['usage']['prompt_tokens'] ?? 0),
            'tokens_out' => (int) ($response['usage']['completion_tokens'] ?? 0),
            'cached' => (int) ($response['usage']['prompt_tokens_details']['cached_tokens'] ?? 0),
        ];
    }
    $findings = json_decode((string) $scene->checks_json, true) ?: [];
    $record = [
        'slug' => $slug,
        'goal' => $goal,
        'level' => $level,
        'plan_id' => $planId,
        'scene_id' => $scene->id,
        'scene_title' => $scene->title_native,
        'lesson_status' => $scene->lesson_status,
        'prompt_version' => $scene->prompt_version_lesson,
        'model' => $scene->model_lesson,
        'plan_cost_usd' => $plan->cost_usd_plan,
        'lesson_cost_usd' => $scene->cost_usd_lesson,
        'lesson_latency_ms' => $scene->latency_ms_lesson,
        'lesson_attempts' => $scene->attempts_lesson,
        'lesson_calls' => $lessonCalls,
        'findings' => count($findings),
        'wall_s' => round($wall, 1),
        'at' => now()->toIso8601String(),
    ];
    $runs[] = $record;
    file_put_contents($runsFile, json_encode($runs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    say(sprintf('  scene «%s» lesson=%s cost=%s latency=%sms attempts=%s calls=%d tokens=%s findings=%d',
        $scene->title_native, $scene->lesson_status, $scene->cost_usd_lesson, $scene->latency_ms_lesson, $scene->attempts_lesson,
        count($lessonCalls), json_encode(array_map(static fn ($c) => $c['tokens_in'].'/'.$c['tokens_out'], $lessonCalls)), count($findings)));
}

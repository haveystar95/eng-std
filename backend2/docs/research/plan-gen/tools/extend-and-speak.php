<?php

declare(strict_types=1);

/**
 * THE TWO LIVE GATES THE FIRST RUN LEFT OUT: an EXTENSION of an existing plan (more days → the
 * builder is asked again with EXISTING_SCENES and SCENES_COUNT = how many to add) and the
 * partner-line AUDIO of one lesson through the real voice. Same in-process HTTP kernel as
 * `live-run.php`; run only against a disposable database.
 *
 *   docker compose exec -T \
 *     -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync -e CACHE_STORE=array \
 *     -e SPEECH_ENABLED=true \
 *     app php docs/research/plan-gen/tools/extend-and-speak.php <plan-id> <owner-email> <days_total>
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

if ((string) config('database.connections.pgsql.database') === 'wordtrainer') {
    fwrite(STDERR, "Refusing to run against the main database.\n");
    exit(1);
}
[$planId, $email, $days] = [$argv[1] ?? '', $argv[2] ?? '', (int) ($argv[3] ?? 8)];
fwrite(STDERR, 'speech=' . var_export(config('generation.speech.enabled'), true) . ' driver=' . config('generation.speech.driver') . "\n");

function say(string $line): void
{
    fwrite(STDOUT, date('H:i:s') . ' ' . $line . "\n");
}

function call(Kernel $kernel, ?string $token, string $method, string $uri, array $body = []): array
{
    app('cache')->store()->flush();
    app('auth')->forgetGuards();
    $headers = ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'];
    if ($token !== null) {
        $headers['HTTP_AUTHORIZATION'] = 'Bearer ' . $token;
    }
    $request = Request::create($uri, $method, [], [], [], $headers, $body === [] ? null : json_encode($body, JSON_THROW_ON_ERROR));
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);
    $decoded = json_decode((string) $response->getContent(), true);

    return [$response->getStatusCode(), is_array($decoded) ? $decoded : ['raw' => mb_substr((string) $response->getContent(), 0, 300)]];
}

[$status, $login] = call($kernel, null, 'POST', '/api/v1/auth/dev', ['email' => $email, 'device_name' => 'extend', 'timezone' => 'Europe/Kyiv']);
$token = (string) $login['token'];

// ---- extension --------------------------------------------------------------------------
$before = DB::table('plan_scenes')->where('plan_id', $planId)->orderBy('order')->get(['order', 'title_native', 'kind', 'priority', 'lesson_status']);
say('before: ' . json_encode($before->map(static fn ($s) => "{$s->order}:{$s->title_native}[{$s->kind} p{$s->priority} {$s->lesson_status}]")->all(), JSON_UNESCAPED_UNICODE));
$planBefore = DB::table('plans')->where('id', $planId)->first(['cost_usd_plan', 'latency_ms_plan', 'attempts_plan']);

$t0 = microtime(true);
[$status, $plan] = call($kernel, $token, 'PATCH', "/api/v1/plans/{$planId}/schedule", ['days_total' => $days]);
$wall = microtime(true) - $t0;
say(sprintf('PATCH schedule days_total=%d → %d in %.1fs', $days, $status, $wall));
if ($status !== 200) {
    say(json_encode($plan, JSON_UNESCAPED_UNICODE));
    exit(1);
}
say('route: ' . $plan['data']['route_summary'] . ' | ' . implode(' · ', array_map(static fn ($d) => $d['type'] . ($d['title_native'] ? "«{$d['title_native']}»" : '') . '/' . ($d['lesson_status'] ?? '-'), $plan['data']['days'])));
$after = DB::table('plan_scenes')->where('plan_id', $planId)->orderBy('order')->get(['order', 'title_native', 'kind', 'priority', 'lesson_status', 'cost_usd_lesson', 'latency_ms_lesson']);
say('after: ' . json_encode($after->map(static fn ($s) => "{$s->order}:{$s->title_native}[{$s->kind} p{$s->priority} {$s->lesson_status}" . ($s->cost_usd_lesson ? " \${$s->cost_usd_lesson} {$s->latency_ms_lesson}ms" : '') . ']')->all(), JSON_UNESCAPED_UNICODE));
$planAfter = DB::table('plans')->where('id', $planId)->first(['cost_usd_plan', 'latency_ms_plan', 'attempts_plan', 'checks_json']);
say(sprintf('plan call: cost %s → %s (extension ≈ $%.6f), latency %s → %s ms, attempts %s → %s, checks=%s',
    $planBefore->cost_usd_plan, $planAfter->cost_usd_plan, (float) $planAfter->cost_usd_plan - (float) $planBefore->cost_usd_plan,
    $planBefore->latency_ms_plan, $planAfter->latency_ms_plan, $planBefore->attempts_plan, $planAfter->attempts_plan, $planAfter->checks_json));
$log = DB::table('api_request_logs')->where('purpose', 'plan')->orderByDesc('occurred_at')->first(['duration_ms', 'status']);
say('last plan call in request log: ' . json_encode($log));

// ---- speech -----------------------------------------------------------------------------
$scene = DB::table('plan_scenes')->where('plan_id', $planId)->where('lesson_status', 'ready')->orderBy('order')->first();
if ($scene === null) {
    say('no ready scene to speak');
    exit(0);
}
$lines = count(array_filter(
    json_decode((string) $scene->lesson_json, true)['dialogue'] ?? [],
    static fn (array $e): bool => count(array_filter($e['messages'], static fn (array $m): bool => $m['speaker'] === 'A')) > 0,
));
$t0 = microtime(true);
app(\App\Modules\Plan\Application\Command\SpeakSceneLinesHandler::class)(new \App\Modules\Plan\Application\Command\SpeakSceneLines(\App\Modules\Plan\Domain\ValueObject\PlanSceneId::fromString($scene->id)));
$wall = microtime(true) - $t0;
$audios = DB::table('plan_line_audios')->where('scene_id', $scene->id)->get();
say(sprintf('speech: scene %d «%s», %d partner lines → %d files in %.1fs, cost $%.6f, bytes %d, formats %s, voice %s',
    $scene->order, $scene->title_native, $lines, $audios->count(), $wall, (float) $audios->sum('cost_usd'), (int) $audios->sum('bytes'),
    json_encode(array_values(array_unique($audios->pluck('format')->all()))), $audios->first()?->voice_key ?? '-'));
$speechLog = DB::table('api_request_logs')->where('purpose', 'speech')->selectRaw('count(*) as n, round(avg(duration_ms)) as avg_ms, max(duration_ms) as max_ms')->first();
say('speech request log: ' . json_encode($speechLog));

// The card list resolves audio ids at read time — check one dialogue card carries them.
$day = DB::table('plan_days')->where('scene_id', $scene->id)->first();
if ($day !== null) {
    [$status, $cards] = call($kernel, $token, 'GET', "/api/v1/plans/{$planId}/days/{$day->number}/cards");
    $withAudio = count(array_filter($cards['data']['cards'] ?? [], static fn (array $c): bool => ($c['payload']['audio_id'] ?? null) !== null));
    say(sprintf('cards of day %d with audio_id: %d of %d (%d)', $day->number, $withAudio, count($cards['data']['cards'] ?? []), $status));
    $first = array_values(array_filter($cards['data']['cards'] ?? [], static fn (array $c): bool => ($c['payload']['audio_id'] ?? null) !== null))[0] ?? null;
    if ($first !== null) {
        [$status, $body] = call($kernel, $token, 'GET', "/api/v1/plans/audio/{$first['payload']['audio_id']}");
        say("GET /plans/audio/{$first['payload']['audio_id']} → {$status}");
    }
}

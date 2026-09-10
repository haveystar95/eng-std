<?php

declare(strict_types=1);

/**
 * THE LIVE GATE OF PLAN-GEN (§11): four goals through the HTTP surface, on the real model, with
 * the queue run inline — so a `POST /plans` returns only when the plan AND day 1's lesson are
 * written, and the walk-through of a day happens against the same code the phone will use.
 *
 * Run ONLY with the environment overridden to a disposable database, never against `wordtrainer`:
 *
 *   docker compose exec -T \
 *     -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync -e CACHE_STORE=array \
 *     -e SPEECH_ENABLED=false \
 *     app php docs/research/plan-gen/tools/live-run.php [goal-index…]
 *
 * Every request goes through the kernel (`Request::create` → `Kernel::handle`): routes,
 * middleware, controllers, jobs — the whole stack, minus the socket.
 */

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../../../../vendor/autoload.php';
$app = require __DIR__ . '/../../../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$database = (string) config('database.connections.pgsql.database');
if ($database === 'wordtrainer') {
    fwrite(STDERR, "Refusing to run against the main database.\n");
    exit(1);
}
fwrite(STDERR, "database={$database} queue=" . config('queue.default') . ' plan.driver=' . config('plan.model.driver') . ' model=' . config('plan.model.plan_model') . ' speech=' . var_export(config('generation.speech.enabled'), true) . "\n");

/** @return array{0: int, 1: array<string, mixed>} */
function call(Kernel $kernel, string $token, string $method, string $uri, array $body = []): array
{
    // A walked day is eighty answers in a second; the per-minute throttle is not what is measured.
    app('cache')->store()->flush();
    // The request guard remembers the last bearer within one process; every call names its own.
    app('auth')->forgetGuards();
    $request = Request::create($uri, $method, [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
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
    fwrite(STDOUT, date('H:i:s') . ' ' . $line . "\n");
}

function token(Kernel $kernel, string $email): string
{
    app('cache')->store()->flush();
    $request = Request::create('/api/v1/auth/dev', 'POST', [], [], [], [
        'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
    ], json_encode(['email' => $email, 'device_name' => 'live-run', 'timezone' => 'Europe/Kyiv'], JSON_THROW_ON_ERROR));
    $response = $kernel->handle($request);
    $decoded = json_decode((string) $response->getContent(), true);
    if ($response->getStatusCode() !== 200 || ! is_array($decoded) || ! isset($decoded['token'])) {
        throw new RuntimeException('dev login failed: ' . $response->getStatusCode() . ' ' . $response->getContent());
    }
    DB::table('profiles')->where('user_id', $decoded['user']['id'])->update(['native_language' => 'ru', 'target_language' => 'en']);

    return (string) $decoded['token'];
}

/** Walk a day: every card passed, except the scripted indexes. Returns the room after close. */
function walkDay(Kernel $kernel, string $token, string $planId, int $number, array $script): array
{
    [$status, $open] = call($kernel, $token, 'POST', "/api/v1/plans/{$planId}/days/{$number}/open");
    if ($status !== 200) {
        say("  open day {$number}: {$status} " . json_encode($open, JSON_UNESCAPED_UNICODE));

        return ['status' => $status];
    }
    $cards = $open['data']['cards'];
    say(sprintf('  day %d open: %d cards (%s)', $number, count($cards), implode(', ', array_count_values(array_column($cards, 'stage')) ? array_map(static fn ($k, $v) => "{$k}:{$v}", array_keys(array_count_values(array_column($cards, 'stage'))), array_count_values(array_column($cards, 'stage'))) : [])));
    $queue = $cards;
    $seen = [];
    $failedOnce = [];
    $used = [];
    $t0 = microtime(true);
    while ($queue !== []) {
        $card = array_shift($queue);
        if (isset($seen[$card['id']]) || $card['result'] !== null) {
            continue;
        }
        $seen[$card['id']] = true;
        // The script names KINDS: the first card of that kind gets the verdict; a card dealt again
        // after a scripted failure fails again, so the unit returns tomorrow.
        [$result, $attempts] = ['passed', 1];
        if ($card['retry_of'] !== null && isset($failedOnce[$card['retry_of']])) {
            [$result, $attempts] = ['failed', 2];
        } elseif (isset($script[$card['kind']]) && ! isset($used[$card['kind']])) {
            [$result, $attempts] = $script[$card['kind']];
            $used[$card['kind']] = true;
            if ($result === 'failed') {
                $failedOnce[$card['id']] = true;
            }
        }
        [$s, $out] = call($kernel, $token, 'POST', "/api/v1/plans/{$planId}/days/{$number}/cards/{$card['id']}/answer", ['result' => $result, 'attempts' => $attempts]);
        if ($s !== 200) {
            say("  answer {$card['kind']}: {$s} " . json_encode($out, JSON_UNESCAPED_UNICODE));

            continue;
        }
        if ($out['data']['requeued'] !== null) {
            $queue[] = $out['data']['requeued'];
            say("  requeued {$card['kind']} {$card['unit_ref']} → position {$out['data']['requeued']['position']}");
        }
        if ($out['data']['card']['returns']) {
            say("  returns tomorrow: {$card['kind']} {$card['unit_ref']}");
        }
    }
    foreach (['words', 'phrases', 'dialogue', 'listen', 'speak'] as $stage) {
        [$s] = call($kernel, $token, 'POST', "/api/v1/plans/{$planId}/days/{$number}/stages/{$stage}/close");
        if ($s !== 200) {
            say("  close stage {$stage}: {$s}");
        }
    }
    [$s, $closed] = call($kernel, $token, 'POST', "/api/v1/plans/{$planId}/days/{$number}/close");
    say(sprintf('  day %d closed: %d, answers in %.1fs, metrics=%s', $number, $s, microtime(true) - $t0, json_encode($closed['data']['metrics'] ?? null, JSON_UNESCAPED_UNICODE)));

    return $closed['data'] ?? [];
}

function planRow(string $planId): string
{
    $row = DB::table('plans')->where('id', $planId)->first();
    $scenes = DB::table('plan_scenes')->where('plan_id', $planId)->orderBy('order')->get();
    $lines = [sprintf('plan status=%s cost=%s latency=%sms attempts=%s prompt=%s build=%s model=%s checks=%s',
        $row->status, $row->cost_usd_plan, $row->latency_ms_plan, $row->attempts_plan, $row->prompt_version_plan, $row->build_version, $row->model_plan, $row->checks_json)];
    foreach ($scenes as $s) {
        $lines[] = sprintf('  scene %d [%s p%d] «%s» / «%s» lesson=%s cost=%s latency=%sms attempts=%s checks=%s',
            $s->order, $s->kind, $s->priority, $s->title_native, $s->teaches_native, $s->lesson_status, $s->cost_usd_lesson ?? '-', $s->latency_ms_lesson ?? '-', $s->attempts_lesson ?? '-', $s->checks_json ?? '-');
    }

    return implode("\n", $lines);
}

$goals = [
    ['doctor', 'Иду к врачу с ребёнком, болит спина. Первый раз в местной клинике, боюсь не понять назначения', 5, 'beginner'],
    ['rent', 'аренда квартиры', 3, 'intermediate'],
    ['vague', 'хочу подтянуть английский', 3, 'beginner'],
    ['lisbon', 'Поездка в Лиссабон с семьёй на неделю, первый раз за границей с ребёнком', 10, 'beginner'],
];
$only = array_map('intval', array_slice($argv, 1));

foreach ($goals as $i => [$slug, $goal, $days, $level]) {
    if ($only !== [] && ! in_array($i, $only, true)) {
        continue;
    }
    $suffix = (string) getenv('RUN_SUFFIX');
    $token = token($kernel, "qa-plangen-{$slug}{$suffix}@wt.test");
    say("=== {$slug}: «{$goal}» {$days} days {$level}");

    $t0 = microtime(true);
    [$status, $build] = call($kernel, $token, 'POST', '/api/v1/plans', ['goal_text' => $goal, 'target_lang' => 'en', 'level' => $level, 'days_total' => $days]);
    $wall = microtime(true) - $t0;
    say(sprintf('POST /plans → %d in %.1fs: %s', $status, $wall, json_encode($build['data'] ?? $build, JSON_UNESCAPED_UNICODE)));
    if ($status !== 202) {
        continue;
    }
    $planId = $build['data']['id'];
    say(planRow($planId));
    if ($build['data']['status'] !== 'ready') {
        continue;
    }

    [$status, $plan] = call($kernel, $token, 'GET', "/api/v1/plans/{$planId}");
    say('route: ' . $plan['data']['route_summary'] . ' | ' . implode(' · ', array_map(static fn ($d) => $d['type'] . ($d['title_native'] ? "«{$d['title_native']}»" : ''), $plan['data']['days'])));

    [$status] = call($kernel, $token, 'POST', "/api/v1/plans/{$planId}/start");
    say("start → {$status}");

    $t0 = microtime(true);
    [$status, $open] = call($kernel, $token, 'POST', "/api/v1/plans/{$planId}/days/1/open");
    say(sprintf('open day 1 → %d in %.1fs (day 2 lesson written inline)', $status, microtime(true) - $t0));
    say(planRow($planId));

    if ($slug === 'doctor') {
        // Day 1 with two errors on one word (the first choose, failed twice) and one skip on a
        // spoken card, then day 2 tomorrow with the word back.
        $closed = walkDay($kernel, $token, $planId, 1, ['word_choose' => ['failed', 1], 'word_say' => ['skipped', 2]]);
        Artisan::call('plan:shift-day', ['plan' => $planId, '--days' => 1]);
        say('  calendar shifted one day');
        [$status, $two] = call($kernel, $token, 'POST', "/api/v1/plans/{$planId}/days/2/open");
        $returned = array_values(array_filter($two['data']['cards'] ?? [], static fn ($c) => $c['source'] === 'returned'));
        say(sprintf('  day 2 open → %d, %d cards, returned: %s', $status, count($two['data']['cards'] ?? []), json_encode(array_map(static fn ($c) => $c['kind'] . ' ' . $c['unit_ref'] . ' @' . $c['position'], $returned), JSON_UNESCAPED_UNICODE)));
        [$status, $room] = call($kernel, $token, 'GET', "/api/v1/plans/{$planId}/days/1");
        say('  day 1 room metrics: ' . json_encode($room['data']['metrics'] ?? null, JSON_UNESCAPED_UNICODE));
    }

    if ($slug === 'rent') {
        walkDay($kernel, $token, $planId, 1, []);
        Artisan::call('plan:shift-day', ['plan' => $planId, '--days' => 1]);
        walkDay($kernel, $token, $planId, 2, []);
        Artisan::call('plan:shift-day', ['plan' => $planId, '--days' => 1]);
        [$status, $three] = call($kernel, $token, 'POST', "/api/v1/plans/{$planId}/days/3/open");
        say(sprintf('  rehearsal open → %d, %d cards, stages=%s', $status, count($three['data']['cards'] ?? []), json_encode(array_unique(array_column($three['data']['cards'] ?? [], 'stage')))));
        say(planRow($planId));
    }
}

say('=== counters: ' . json_encode(DB::table('plan_check_counters')->orderBy('check_name')->get()->map(static fn ($r) => "{$r->prompt_version}/{$r->check_name}/{$r->action}={$r->hits}")->all(), JSON_UNESCAPED_UNICODE));
say('=== request log (purpose=plan): ' . json_encode(DB::table('api_request_logs')->where('purpose', 'plan')->selectRaw('count(*) as n, avg(duration_ms) as avg_ms, max(duration_ms) as max_ms')->first()));
say('=== totals: ' . json_encode(DB::table('plans')->selectRaw('count(*) as plans, sum(cost_usd_plan) as plan_cost')->first()) . ' ' . json_encode(DB::table('plan_scenes')->selectRaw("count(*) filter (where lesson_status='ready') as lessons, sum(cost_usd_lesson) as lesson_cost, max(latency_ms_lesson) as max_latency" )->first()));

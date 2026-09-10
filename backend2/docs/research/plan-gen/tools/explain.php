<?php

declare(strict_types=1);

/**
 * EXPLAIN FOR EVERY §10 ENDPOINT — on a database seeded with `plan:seed-load` (≥ 50 plans,
 * 300 days, 20 000 cards). Each endpoint is called through the kernel with the query log on; every
 * SQL statement it ran is printed with the number of statements per call (constant, never N+1)
 * and `EXPLAIN (ANALYZE, BUFFERS)` of the reads that touch the growing tables.
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync \
 *     -e CACHE_STORE=array -e PLAN_MODEL_DRIVER=fake -e SPEECH_ENABLED=false \
 *     app php docs/research/plan-gen/tools/explain.php <user-email>
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

$email = $argv[1] ?? 'qa-plangen-doctor@wt.test';
$request = Request::create('/api/v1/auth/dev', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], json_encode(['email' => $email, 'device_name' => 'explain'], JSON_THROW_ON_ERROR));
$decoded = json_decode((string) $kernel->handle($request)->getContent(), true);
$token = (string) $decoded['token'];
$userId = (string) $decoded['user']['id'];

$plan = DB::table('plans')->where('user_id', $userId)->where('status', 'active')->orderByDesc('created_at')->first()
    ?? DB::table('plans')->where('user_id', $userId)->orderByDesc('created_at')->first();
if ($plan === null) {
    fwrite(STDERR, "No plan for {$email}; seed one first.\n");
    exit(1);
}
$planId = $plan->id;
$day = DB::table('plan_days')->where('plan_id', $planId)->whereIn('status', ['in_progress', 'open', 'closed'])->orderBy('number')->first()
    ?? DB::table('plan_days')->where('plan_id', $planId)->orderBy('number')->first();
$number = (int) $day->number;
$card = DB::table('day_cards')->where('day_id', $day->id)->whereNull('result')->orderBy('position')->first();

echo "tables: plans=" . DB::table('plans')->count() . ' days=' . DB::table('plan_days')->count() . ' cards=' . DB::table('day_cards')->count() . ' terms=' . DB::table('plan_terms')->count() . "\n\n";

$endpoints = [
    ['GET', '/api/v1/plans'],
    ['GET', '/api/v1/plans/current'],
    ['GET', "/api/v1/plans/{$planId}"],
    ['GET', "/api/v1/plans/{$planId}/build"],
    ['GET', "/api/v1/plans/{$planId}/days/{$number}"],
    ['GET', "/api/v1/plans/{$planId}/days/{$number}/cards"],
    ['GET', "/api/v1/plans/{$planId}/days/{$number}/sheet"],
];
if ($card !== null) {
    $endpoints[] = ['POST', "/api/v1/plans/{$planId}/days/{$number}/cards/{$card->id}/answer", ['result' => 'passed', 'attempts' => 1]];
}

foreach ($endpoints as $endpoint) {
    [$method, $uri] = $endpoint;
    $body = $endpoint[2] ?? null;
    DB::flushQueryLog();
    DB::enableQueryLog();
    $req = Request::create($uri, $method, [], [], [], [
        'HTTP_AUTHORIZATION' => 'Bearer ' . $token, 'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
    ], $body !== null ? json_encode($body, JSON_THROW_ON_ERROR) : null);
    $t0 = microtime(true);
    $response = $kernel->handle($req);
    $ms = (microtime(true) - $t0) * 1000;
    $log = DB::getQueryLog();
    DB::disableQueryLog();

    printf("=== %s %s → %d in %.0f ms, %d statements\n", $method, $uri, $response->getStatusCode(), $ms, count($log));
    foreach ($log as $i => $q) {
        $sql = $q['query'];
        $bindings = $q['bindings'];
        $lower = strtolower(ltrim($sql));
        $isRead = str_starts_with($lower, 'select');
        printf("  [%d] %.1f ms  %s\n", $i + 1, $q['time'], mb_substr($sql, 0, 220));
        if (! $isRead || ! preg_match('/\b(plans|plan_scenes|plan_days|day_cards|plan_terms|plan_line_audios)\b/', $sql)) {
            continue;
        }
        if (str_contains($lower, 'personal_access_tokens')) {
            continue;
        }
        try {
            $rows = DB::select('EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) ' . $sql, $bindings);
            foreach ($rows as $row) {
                $line = (string) $row->{'QUERY PLAN'};
                if (preg_match('/Seq Scan|Index|Bitmap|rows=|Execution Time|Planning Time/', $line)) {
                    echo '        ' . trim($line) . "\n";
                }
            }
        } catch (Throwable $e) {
            echo '        explain failed: ' . $e->getMessage() . "\n";
        }
    }
    echo "\n";
}

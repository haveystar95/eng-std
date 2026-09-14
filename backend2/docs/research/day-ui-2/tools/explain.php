<?php

declare(strict_types=1);

/**
 * EXPLAIN FOR GET ДНЯ WITH THE DAY WINDOW (DAY-UI-2) — on a database seeded with `plan:seed-load`.
 *
 * The day is read in its three shapes — passed (day 1 of the seed), in progress (day 2), not opened
 * (day 3: the dealer's outline, no `day_cards` read) — through the HTTP kernel with the query log on.
 * Every statement is printed with the count per call (constant, never N+1) and
 * `EXPLAIN (ANALYZE, BUFFERS)` of the reads on the plan tables. The seed has no spoken lines, so the
 * tool first gives every scene of the learner fourteen (`x1…x8`, `p1…p6`) under the voice the server
 * would read them with — the window's `plan_line_audios` read is then measured on real rows.
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php artisan migrate:fresh --force
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/day-ui-2/tools/explain.php <email> seed
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/day-ui-2/tools/explain.php <email>
 */

use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

if (! str_ends_with((string) config('database.connections.pgsql.database'), '_test')) {
    fwrite(STDERR, "Only on a *_test database.\n");
    exit(1);
}

$email = $argv[1] ?? 'qa-dayui2-explain@wt.test';
$request = Request::create('/api/v1/auth/dev', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], json_encode(['email' => $email, 'device_name' => 'explain'], JSON_THROW_ON_ERROR));
$decoded = json_decode((string) $kernel->handle($request)->getContent(), true);
$token = (string) $decoded['token'];
$userId = (string) $decoded['user']['id'];

if (($argv[2] ?? '') === 'seed') {
    // Two learners' worth of load: this one — 50 plans, a neighbour — 200.
    $console = $app->make(ConsoleKernel::class);
    $console->call('plan:seed-load', ['user' => $userId, '--plans' => 50]);
    $console->call('plan:seed-load', ['user' => Ulid::generate(), '--plans' => 200]);
    $voice = (string) $app->make(LineSpeaker::class)->voiceKeyFor('en');
    $now = now();
    foreach (DB::table('plan_scenes')->select(['id', 'user_id'])->cursor() as $scene) {
        $rows = [];
        foreach ([...array_map(static fn (int $i): string => "x{$i}", range(1, 8)), ...array_map(static fn (int $i): string => "p{$i}", range(1, 6))] as $ref) {
            $rows[] = ['id' => Ulid::generate(), 'scene_id' => $scene->id, 'user_id' => $scene->user_id, 'line_ref' => $ref, 'voice_key' => $voice, 'format' => 'wav', 'path' => "seed/{$scene->id}/{$ref}.wav", 'bytes' => 1, 'created_at' => $now];
        }
        DB::table('plan_line_audios')->insert($rows);
    }
    // The seed writes a closed and an in-progress day without `opened_at`, and a day without it is
    // read as not opened (the dealer's outline) — the dealt cards would never be measured.
    DB::table('plan_days')->whereIn('status', ['closed', 'in_progress'])->whereNull('opened_at')->update(['opened_at' => $now]);
    DB::statement('ANALYZE');
    echo $console->output();
    exit(0);
}

$plan = DB::table('plans')->where('user_id', $userId)->where('status', 'active')->first();
if ($plan === null) {
    fwrite(STDERR, "No active plan for {$email}; run with `seed` first.\n");
    exit(1);
}

echo 'tables: plans='.DB::table('plans')->count().' scenes='.DB::table('plan_scenes')->count().' days='.DB::table('plan_days')->count()
    .' cards='.DB::table('day_cards')->count().' terms='.DB::table('plan_terms')->count().' line_audios='.DB::table('plan_line_audios')->count()."\n\n";

foreach ([1 => 'passed', 2 => 'in progress', 3 => 'not opened (outline)'] as $number => $shape) {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $req = Request::create("/api/v1/plans/{$plan->id}/days/{$number}", 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json']);
    $t0 = microtime(true);
    $response = $kernel->handle($req);
    $ms = (microtime(true) - $t0) * 1000;
    $log = DB::getQueryLog();
    DB::disableQueryLog();
    $window = json_decode((string) $response->getContent(), true)['data']['window'] ?? null;

    printf("=== GET day %d — %s → %d in %.0f ms, %d statements; window status %s\n", $number, $shape, $response->getStatusCode(), $ms, count($log), $window['day']['status'] ?? '—');
    foreach ($log as $i => $q) {
        $sql = $q['query'];
        printf("  [%d] %.1f ms  %s\n", $i + 1, $q['time'], mb_substr($sql, 0, 200));
        if (! str_starts_with(strtolower(ltrim($sql)), 'select') || ! preg_match('/\b(plans|plan_scenes|plan_days|day_cards|plan_terms|plan_line_audios)\b/', $sql)) {
            continue;
        }
        foreach (DB::select('EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) '.$sql, $q['bindings']) as $row) {
            $line = (string) $row->{'QUERY PLAN'};
            if (preg_match('/Seq Scan|Index|Bitmap|Buffers|Execution Time/', $line)) {
                echo '        '.trim($line)."\n";
            }
        }
    }
    echo "\n";
}

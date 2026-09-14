<?php

declare(strict_types=1);

/**
 * EXPLAIN FOR THE GEN-2a READS AND WRITES — on a disposable database seeded with `plan:seed-load`.
 *
 * GEN-2a adds no new read path: the day window reads the same rows with more columns (`plan_terms`
 * frame, slot, used_in; the listening block comes out of `lesson_json`). What is new is three writes
 * and one read: the lesson validator's counters (`CheckCounters::recordCodes`, an upsert per code),
 * the card repair's term rewrite (`PlanTermRepository::rewriteTexts`, an UPDATE per term by
 * `(scene_id, ref)`), and the learner's gender on `/auth/me` and at lesson time (`profiles.gender`).
 * Seed first (50 plans for the learner, 200 for a neighbour, 30 spoken rows a scene), then measure
 * with the query log on: statements per call and the plan of each.
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_test app php artisan migrate:fresh --force
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_test app php docs/research/gen-2a/tools/explain.php <email> seed
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_test app php docs/research/gen-2a/tools/explain.php <email> > docs/research/gen-2a/explain.txt
 */

use App\Modules\Plan\Application\Port\CheckCounters;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
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

$email = $argv[1] ?? 'qa-gen2a-explain@wt.test';
$request = Request::create('/api/v1/auth/dev', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], json_encode(['email' => $email, 'device_name' => 'explain'], JSON_THROW_ON_ERROR));
$decoded = json_decode((string) $kernel->handle($request)->getContent(), true);
$token = (string) $decoded['token'];
$userId = (string) $decoded['user']['id'];

if (($argv[2] ?? '') === 'seed') {
    $console = $app->make(ConsoleKernel::class);
    $console->call('plan:seed-load', ['user' => $userId, '--plans' => 50]);
    $console->call('plan:seed-load', ['user' => Ulid::generate(), '--plans' => 200]);
    $speaker = $app->make(LineSpeaker::class);
    $female = (string) $speaker->voiceKeyFor('en', VoiceGender::Female);
    $male = (string) $speaker->voiceKeyFor('en', VoiceGender::Male);
    $now = now();
    DB::table('plan_scenes')->update(['partner_voice_gender' => 'female']);
    $refs = [
        ...array_map(static fn (int $i): array => ["x{$i}", $female], range(1, 8)),
        ...array_map(static fn (int $i): array => ["x{$i}b", $male], range(1, 8)),
        ...array_map(static fn (int $i): array => ["p{$i}", $male], range(1, 6)),
        ...array_map(static fn (int $i): array => ["v{$i}", $male], range(1, 8)),
    ];
    foreach (DB::table('plan_scenes')->select(['id', 'user_id'])->cursor() as $scene) {
        $rows = [];
        foreach ($refs as [$ref, $voice]) {
            $rows[] = ['id' => Ulid::generate(), 'scene_id' => $scene->id, 'user_id' => $scene->user_id, 'line_ref' => $ref, 'voice_key' => $voice, 'format' => 'mp3', 'path' => "seed/{$scene->id}/{$ref}.mp3", 'bytes' => 1, 'created_at' => $now];
        }
        DB::table('plan_line_audios')->insert($rows);
    }
    DB::table('plan_days')->whereIn('status', ['closed', 'in_progress'])->whereNull('opened_at')->update(['opened_at' => $now]);
    // Neighbours with profiles, a third of them with a gender — one profile row is read whole by any planner.
    foreach (array_chunk(range(1, 3000), 500) as $chunk) {
        $users = [];
        $profiles = [];
        foreach ($chunk as $n) {
            $id = Ulid::generate();
            $users[] = ['id' => $id, 'name' => "load {$n}", 'email' => "load-{$n}-{$id}@wt.test", 'created_at' => $now, 'updated_at' => $now];
            $profiles[] = ['id' => Ulid::generate(), 'user_id' => $id, 'gender' => [null, 'female', 'male'][$n % 3], 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('users')->insert($users);
        DB::table('profiles')->insert($profiles);
    }
    DB::table('profiles')->where('user_id', $userId)->update(['gender' => 'female']);
    // Counters of a live prompt history: every code under three versions, so the upsert meets rows.
    foreach (['lesson_day.v4.2', 'lesson_day.v4.3', 'lesson_day.v4.4'] as $version) {
        $app->make(CheckCounters::class)->recordCodes($version, LessonCodes::all());
    }
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
    .' cards='.DB::table('day_cards')->count().' terms='.DB::table('plan_terms')->count().' line_audios='.DB::table('plan_line_audios')->count()
    .' profiles='.DB::table('profiles')->count().' check_counters='.DB::table('plan_check_counters')->count()."\n\n";

/** Print every statement of $log with its EXPLAIN — ANALYZE for reads, the plan alone for writes. */
$explain = static function (array $log): void {
    foreach ($log as $i => $q) {
        $sql = $q['query'];
        printf("  [%d] %.1f ms  %s\n", $i + 1, $q['time'], mb_substr($sql, 0, 220));
        if (! preg_match('/\b(plans|plan_scenes|plan_days|day_cards|plan_terms|plan_line_audios|profiles|plan_check_counters)\b/', $sql)) {
            continue;
        }
        $verb = strtolower(strtok(ltrim($sql), ' ') ?: '');
        if (! in_array($verb, ['select', 'update', 'insert'], true)) {
            continue;
        }
        $prefix = $verb === 'select' ? 'EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) ' : 'EXPLAIN (FORMAT TEXT) ';
        foreach (DB::select($prefix.$sql, $q['bindings']) as $row) {
            $line = (string) $row->{'QUERY PLAN'};
            if (preg_match('/Seq Scan|Index|Bitmap|Buffers|Execution Time|Update on|Insert on|Conflict/', $line)) {
                echo '        '.trim($line)."\n";
            }
        }
    }
    echo "\n";
};

$measure = static function (string $title, callable $run) use ($explain): void {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $t0 = microtime(true);
    $note = $run();
    $ms = (microtime(true) - $t0) * 1000;
    $log = DB::getQueryLog();
    DB::disableQueryLog();
    printf("=== %s → %.0f ms, %d statements%s\n", $title, $ms, count($log), $note === null ? '' : "; {$note}");
    $explain($log);
};

$get = static function (string $path) use ($kernel, $token): array {
    $req = Request::create($path, 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json']);
    $response = $kernel->handle($req);

    return [$response->getStatusCode(), json_decode((string) $response->getContent(), true)];
};

foreach ([1 => 'passed', 2 => 'in progress', 3 => 'not opened (outline)'] as $number => $shape) {
    $measure("GET day {$number} — {$shape}", static function () use ($get, $plan, $number): string {
        [$status, $body] = $get("/api/v1/plans/{$plan->id}/days/{$number}");
        $window = $body['data']['window'] ?? null;
        $framed = count(array_filter($window['program']['phrases']['items'] ?? [], static fn (array $p): bool => ($p['frame'] ?? null) !== null));

        return "HTTP {$status}, window {$window['day']['status']}, phrases with a frame {$framed}, listening ".count($window['listening'] ?? []);
    });
}

$measure('GET /auth/me — the profile with gender', static function () use ($get): string {
    [$status, $body] = $get('/api/v1/auth/me');

    $profile = $body['data']['profile'] ?? [];

    return "HTTP {$status}, gender ".(array_key_exists('gender', $profile) ? var_export($profile['gender'], true) : 'absent');
});

$sceneId = PlanSceneId::fromString((string) DB::table('plan_scenes')->where('plan_id', $plan->id)->orderBy('order')->value('id'));
$measure('card repair apply — rewriteTexts (UPDATE per term by scene_id, ref)', static function () use ($app, $sceneId): string {
    $terms = $app->make(PlanTermRepository::class)->forScene($sceneId);
    DB::flushQueryLog();
    $app->make(PlanTermRepository::class)->rewriteTexts($sceneId, $terms);

    return 'terms '.count($terms);
});

$measure('lesson validator — recordCodes (upsert per code)', static function () use ($app): string {
    $codes = [LessonCodes::KEY_NO_CONTENT_WORD, LessonCodes::KEY_NO_CONTENT_WORD, LessonCodes::CHECK_VERBATIM, LessonCodes::LISTENING_DISTRACTOR_NOT_FILLER];
    $app->make(CheckCounters::class)->recordCodes('lesson_day.v4.4', $codes);

    return 'codes '.count(array_unique($codes));
});

<?php

declare(strict_types=1);

/**
 * EXPLAIN FOR THE DAY-UI-3 READS AND WRITES — on a database seeded with `plan:seed-load`.
 *
 * The DAY-UI-2 harness (`../day-ui-2/tools/explain.php`) with the voice DAY-UI-3 stores: every scene
 * of the seed gets its two voices (`partner_voice_gender`) and thirty spoken rows — the partner's
 * lines `x1…x8` in the partner's voice, the learner's `x1b…x8b`, the phrases `p1…p6` and the words
 * `v1…v8` in the learner's — so the window's `plan_line_audios` read (two voice keys now) runs on
 * real rows. Then, with the query log on:
 * - GET day in its three shapes (passed, in progress, not opened) — statements per call, EXPLAIN;
 * - the voice job's read of what a scene still owes (`SceneVoiceQueue::owed`);
 * - the photo job's writes and reads: `finishIllustration`, `castSceneVoices` (plain EXPLAIN — they write),
 *   and the backfills' `photographedWithoutPrompt`, `repeatingDayPhotos` and scene list.
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php artisan migrate:fresh --force
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/day-ui-3/tools/explain.php <email> seed
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/day-ui-3/tools/explain.php <email>
 */

use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Service\SceneVoiceQueue;
use App\Modules\Plan\Domain\Repository\PlanRepository;
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

$email = $argv[1] ?? 'qa-dayui3-explain@wt.test';
$request = Request::create('/api/v1/auth/dev', 'POST', [], [], [], ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], json_encode(['email' => $email, 'device_name' => 'explain'], JSON_THROW_ON_ERROR));
$decoded = json_decode((string) $kernel->handle($request)->getContent(), true);
$token = (string) $decoded['token'];
$userId = (string) $decoded['user']['id'];
$speaker = $app->make(LineSpeaker::class);
$female = (string) $speaker->voiceKeyFor('en', VoiceGender::Female);
$male = (string) $speaker->voiceKeyFor('en', VoiceGender::Male);

if (($argv[2] ?? '') === 'seed') {
    // Two learners' worth of load: this one — 50 plans, a neighbour — 200.
    $console = $app->make(ConsoleKernel::class);
    $console->call('plan:seed-load', ['user' => $userId, '--plans' => 50]);
    $console->call('plan:seed-load', ['user' => Ulid::generate(), '--plans' => 200]);
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

/** Print every statement of $log with its EXPLAIN — ANALYZE for reads, the plan alone for writes. */
$explain = static function (array $log): void {
    foreach ($log as $i => $q) {
        $sql = $q['query'];
        printf("  [%d] %.1f ms  %s\n", $i + 1, $q['time'], mb_substr($sql, 0, 220));
        if (! preg_match('/\b(plans|plan_scenes|plan_days|day_cards|plan_terms|plan_line_audios)\b/', $sql)) {
            continue;
        }
        $verb = strtolower(strtok(ltrim($sql), ' ') ?: '');
        $prefix = $verb === 'select' ? 'EXPLAIN (ANALYZE, BUFFERS, FORMAT TEXT) ' : 'EXPLAIN (FORMAT TEXT) ';
        if (! in_array($verb, ['select', 'update'], true)) {
            continue;
        }
        foreach (DB::select($prefix.$sql, $q['bindings']) as $row) {
            $line = (string) $row->{'QUERY PLAN'};
            if (preg_match('/Seq Scan|Index|Bitmap|Buffers|Execution Time|Update on/', $line)) {
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

foreach ([1 => 'passed', 2 => 'in progress', 3 => 'not opened (outline)'] as $number => $shape) {
    $measure("GET day {$number} — {$shape}", static function () use ($kernel, $plan, $number, $token): string {
        $req = Request::create("/api/v1/plans/{$plan->id}/days/{$number}", 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json']);
        $response = $kernel->handle($req);
        $window = json_decode((string) $response->getContent(), true)['data']['window'] ?? null;
        $voiced = 0;
        foreach ($window['program']['dialogue']['items'] ?? [] as $pair) {
            $voiced += (int) (($pair['partner']['audio_url'] ?? null) !== null) + (int) (($pair['learner']['audio_url'] ?? null) !== null);
        }

        return "HTTP {$response->getStatusCode()}, window {$window['day']['status']}, dialogue lines voiced {$voiced}";
    });
}

$sceneId = PlanSceneId::fromString((string) DB::table('plan_scenes')->where('plan_id', $plan->id)->orderBy('order')->value('id'));
$measure('voice job — what the scene still owes (SceneVoiceQueue::owed)', static function () use ($app, $sceneId): string {
    $debt = $app->make(SceneVoiceQueue::class)->owed($sceneId);

    return 'batches '.count($debt->batches ?? []);
});

$measure('photo job end — finishIllustration (conditional UPDATE)', static function () use ($app, $sceneId): string {
    return 'changed '.var_export($app->make(PlanRepository::class)->finishIllustration($sceneId), true);
});

$measure('voice job — castSceneVoices (conditional UPDATE)', static function () use ($app, $sceneId): string {
    return 'changed '.var_export($app->make(PlanRepository::class)->castSceneVoices($sceneId, VoiceGender::Female), true);
});

$measure('images backfill --requery — photographedWithoutPrompt (all plans)', static function () use ($app): string {
    return 'scenes '.count($app->make(PlanTermRepository::class)->photographedWithoutPrompt(null));
});

$measure('images backfill --requery — repeatingDayPhotos (all plans)', static function () use ($app): string {
    return 'scenes '.count($app->make(PlanTermRepository::class)->repeatingDayPhotos(null));
});

$measure('speak backfill — the scene list', static function (): string {
    return 'scenes '.DB::table('plan_scenes')
        ->join('plans', 'plans.id', '=', 'plan_scenes.plan_id')
        ->where('plans.status', '<>', 'deleted')
        ->whereIn('plan_scenes.lesson_status', ['ready', 'illustrating'])
        ->orderBy('plans.created_at', 'desc')
        ->orderBy('plan_scenes.order')
        ->pluck('plan_scenes.id')
        ->count();
});

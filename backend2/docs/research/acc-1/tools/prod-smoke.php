<?php

/**
 * ACC-1 — «СБОРКА (21) РАБОТАЕТ КАК РАБОТАЛА»: what the phone reads from the stand, rebuilt by the application's own read
 * path, read-only — once with main's code BEFORE the deploy and once AFTER it, with the clock frozen at the same moment,
 * so the two can be compared field by field (`compare-smoke.py`): the only difference allowed is what the order adds
 * (`days[].lock_reason`, `access` on `/auth/me`).
 *
 * What it reads, for every plan that is not deleted, of every learner: `GET /plans/{id}` (the route — statuses, stages,
 * slots) and `GET /plans/{id}/days/{n}` of the plan's current day (the room and its window); and `GET /auth/me` of every
 * learner (without the generation block — it moves with the clock of the quota).
 *
 * NOTHING IS WRITTEN (the guards of GYM-DUMP-2): the session is READ ONLY, the queue is a connection that does not exist,
 * the cache is an array, the vendors' keys are empty, stray HTTP is refused, the log goes to stderr.
 *
 * Run (main's code is /app; the script is copied in, because the old code has no such file):
 *   docker cp docs/research/acc-1/tools/prod-smoke.php wt_app:/tmp/prod-smoke.php
 *   docker exec -e PGOPTIONS='-c default_transaction_read_only=on' -e LOG_CHANNEL=stderr -e CACHE_STORE=array \
 *     -e QUEUE_CONNECTION=acc1_smoke_none -e OPENAI_API_KEY= -e GEMINI_API_KEY= -e ELEVENLABS_API_KEY= \
 *     -e ANTHROPIC_API_KEY= wt_app php /tmp/prod-smoke.php <frozen ISO moment> /tmp/smoke-<before|after>.json
 */

declare(strict_types=1);

use App\Modules\Identity\Application\Port\UserReader;
use App\Modules\Identity\Presentation\Http\Resource\UserResource;
use App\Modules\Plan\Application\Query\GetDayRoom;
use App\Modules\Plan\Application\Query\GetDayRoomHandler;
use App\Modules\Plan\Application\Query\GetPlan;
use App\Modules\Plan\Application\Query\GetPlanHandler;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Presentation\Http\PlanJson;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

[$script, $frozen, $out] = $argv + [null, null, null];
if (! is_string($frozen) || ! is_string($out)) {
    fwrite(STDERR, "usage: php prod-smoke.php <frozen ISO moment> <out.json>\n");
    exit(2);
}

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// ── the guards ────────────────────────────────────────────────────────────────────────────────────
DB::connection()->getPdo()->exec('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');
$session = DB::selectOne("select current_database() as db, current_setting('default_transaction_read_only') as default_ro, current_setting('transaction_read_only') as tx_ro");
if ($session->default_ro !== 'on' || $session->tx_ro !== 'on') {
    fwrite(STDERR, "session is not read-only — stop\n");
    exit(1);
}
foreach (['OPENAI_API_KEY', 'GEMINI_API_KEY', 'ELEVENLABS_API_KEY', 'ANTHROPIC_API_KEY'] as $key) {
    if ((string) env($key) !== '') {
        fwrite(STDERR, "{$key} is set — run with the key emptied\n");
        exit(1);
    }
}
if (config('logging.default') !== 'stderr' || config('cache.default') !== 'array' || config('queue.connections.'.config('queue.default')) !== null) {
    fwrite(STDERR, "log / cache / queue guards are not in place — stop\n");
    exit(1);
}
Http::preventStrayRequests();
URL::forceRootUrl('https://greedily-thermos-finer.ngrok-free.dev');

$at = new DateTimeImmutable($frozen);
$app->instance(Clock::class, new class($at) implements Clock
{
    public function __construct(private DateTimeImmutable $at) {}

    public function now(): DateTimeImmutable
    {
        return $this->at;
    }
});

$plans = DB::table('plans')->where('status', '!=', 'deleted')->orderBy('created_at')->get(['id', 'user_id']);
$users = DB::table('users')->orderBy('created_at')->pluck('id')->all();
$result = ['db' => $session->db, 'frozen_at' => $at->format(DATE_ATOM), 'plans' => [], 'me' => []];

foreach ($plans as $row) {
    $actor = UserId::fromString((string) $row->user_id);
    $plan = PlanJson::plan(app(GetPlanHandler::class)(new GetPlan(PlanId::fromString((string) $row->id), $actor)));
    $entry = ['plan' => $plan];
    $current = $plan['current_day']['number'] ?? null;
    if (is_int($current)) {
        $room = app(GetDayRoomHandler::class)(new GetDayRoom(PlanId::fromString((string) $row->id), $current, $actor));
        $entry['room'] = PlanJson::room($room);
    }
    $result['plans'][(string) $row->id] = $entry;
}

foreach ($users as $id) {
    $view = app(UserReader::class)->byId(UserId::fromString((string) $id));
    if ($view === null) {
        continue;
    }
    $me = UserResource::make($view)->resolve(Request::create('/api/v1/auth/me'));
    // The access block (ACC-1) is attached by the controller; read it the way `me()` does, where the code has it.
    if (class_exists(App\Modules\Identity\Application\Query\GetAccessHandler::class)) {
        $me['access'] = app(App\Modules\Identity\Application\Query\GetAccessHandler::class)(new App\Modules\Identity\Application\Query\GetAccess(UserId::fromString((string) $id)))->toArray();
    }
    $result['me'][(string) $id] = $me;
}

file_put_contents($out, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)."\n");
fwrite(STDERR, sprintf("%s: %d plans, %d rooms, %d accounts → %s\n", $session->db, count($result['plans']), count(array_filter($result['plans'], static fn (array $e): bool => isset($e['room']))), count($result['me']), $out));

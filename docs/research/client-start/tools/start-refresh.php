<?php

/**
 * CLIENT-START — the fields the server's older fixtures lack (`line_native`, `partner_gender`), read from the e2e stand
 * by the application's own read path, READ ONLY. For the plans/talks the fixtures were taken from:
 *   - the doctor plan 01M2QRH5MYEFZ1DEQ78RH54P6X: rooms of day 2 (review) and day 3 (rehearsal);
 *   - the airport plan 01M2H1ABCMNQXQ6YP9C9A3HD6B: room of day 1;
 *   - a ru→fr plan 01M3D5WM2THKC9XNGSE1R4KHWH: room of day 1 (the French speech pack for the numbers of §6);
 *   - the talks 01M34XJP15Q823PSC7FZ694YC6 (airport, day 1) and 01M34XAFSNAEGCMBPTEY6RQMWE (rehearsal, day 3).
 *
 * Guards as start-fixtures.php: READ ONLY session, no queue, array cache, stderr log, empty vendor keys, no stray HTTP.
 * Run:
 *   docker cp start-refresh.php wt_app:/tmp/start-refresh.php
 *   docker exec -e DB_DATABASE=wordtrainer_e2e_test -e PGOPTIONS='-c default_transaction_read_only=on' -e LOG_CHANNEL=stderr \
 *     -e CACHE_STORE=array -e QUEUE_CONNECTION=start_fixtures_none -e OPENAI_API_KEY= -e GEMINI_API_KEY= -e ELEVENLABS_API_KEY= \
 *     -e ANTHROPIC_API_KEY= wt_app php /tmp/start-refresh.php /tmp/start-refresh.json
 */

declare(strict_types=1);

use App\Modules\Plan\Application\Query\GetConversation;
use App\Modules\Plan\Application\Query\GetConversationHandler;
use App\Modules\Plan\Application\Query\GetDayRoom;
use App\Modules\Plan\Application\Query\GetDayRoomHandler;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Presentation\Http\PlanJson;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

[$script, $out] = $argv + [null, null];
if (! is_string($out)) {
    fwrite(STDERR, "usage: php start-refresh.php <out.json>\n");
    exit(2);
}

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

DB::connection()->getPdo()->exec('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');
$session = DB::selectOne("select current_database() as db, current_setting('default_transaction_read_only') as default_ro, current_setting('transaction_read_only') as tx_ro");
if ($session->db !== 'wordtrainer_e2e_test' || $session->default_ro !== 'on' || $session->tx_ro !== 'on') {
    fwrite(STDERR, "not the read-only e2e session ({$session->db}) — stop\n");
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

$result = ['db' => $session->db];

$rooms = [
    'room_doctor_day2' => ['01M2QRH5MYEFZ1DEQ78RH54P6X', 2],
    'room_doctor_day3' => ['01M2QRH5MYEFZ1DEQ78RH54P6X', 3],
    'room_airport_day1' => ['01M2H1ABCMNQXQ6YP9C9A3HD6B', 1],
    'room_fr_day1' => ['01M3D5WM2THKC9XNGSE1R4KHWH', 1],
];
foreach ($rooms as $name => [$planId, $n]) {
    $row = DB::table('plans')->where('id', $planId)->first(['id', 'user_id']);
    if ($row === null) {
        $result[$name] = null;
        fwrite(STDERR, "{$name}: plan {$planId} not on e2e\n");
        continue;
    }
    try {
        $room = app(GetDayRoomHandler::class)(new GetDayRoom(PlanId::fromString($planId), $n, UserId::fromString((string) $row->user_id)));
        $result[$name] = PlanJson::room($room);
    } catch (Throwable $e) {
        $result[$name] = null;
        fwrite(STDERR, "{$name}: ".get_class($e).': '.$e->getMessage()."\n");
    }
}

foreach (['talk_airport_day1' => '01M34XJP15Q823PSC7FZ694YC6', 'talk_rehearsal_day3' => '01M34XAFSNAEGCMBPTEY6RQMWE'] as $name => $id) {
    $row = DB::table('conversations')->join('plans', 'plans.id', '=', 'conversations.plan_id')->where('conversations.id', $id)->first(['plans.user_id']);
    if ($row === null) {
        $result[$name] = null;
        fwrite(STDERR, "{$name}: conversation {$id} not on e2e\n");
        continue;
    }
    try {
        $view = app(GetConversationHandler::class)(new GetConversation(ConversationId::fromString($id), UserId::fromString((string) $row->user_id)));
        $result[$name] = PlanJson::conversation($view);
    } catch (Throwable $e) {
        $result[$name] = null;
        fwrite(STDERR, "{$name}: ".get_class($e).': '.$e->getMessage()."\n");
    }
}

file_put_contents($out, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)."\n");
fwrite(STDERR, "{$session->db}: written {$out}\n");

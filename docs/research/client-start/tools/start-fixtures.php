<?php

/**
 * CLIENT-START fixtures — READ ONLY render of the e2e stand by the application's own read path.
 *
 * - the paywall switch is turned on IN THIS PROCESS ONLY (config override), so a free QA plan's days 2+ come out with
 *   `lock_reason: subscription` exactly as the server would send them with the switch on;
 * - two conversations are rendered as `GET …/conversation/{id}` would (for `targets[].line_native`).
 *
 * Guards as ACC-1's prod-smoke.php: READ ONLY session, no queue, array cache, stderr log, empty vendor keys, no stray HTTP.
 * Run:
 *   docker cp start-fixtures.php wt_app:/tmp/start-fixtures.php
 *   docker exec -e DB_DATABASE=wordtrainer_e2e_test -e PGOPTIONS='-c default_transaction_read_only=on' -e LOG_CHANNEL=stderr \
 *     -e CACHE_STORE=array -e QUEUE_CONNECTION=start_fixtures_none -e OPENAI_API_KEY= -e GEMINI_API_KEY= -e ELEVENLABS_API_KEY= \
 *     -e ANTHROPIC_API_KEY= wt_app php /tmp/start-fixtures.php /tmp/start-fixtures.json
 */

declare(strict_types=1);

use App\Modules\Plan\Application\Query\GetConversation;
use App\Modules\Plan\Application\Query\GetConversationHandler;
use App\Modules\Plan\Application\Query\GetDayRoom;
use App\Modules\Plan\Application\Query\GetDayRoomHandler;
use App\Modules\Plan\Application\Query\GetPlan;
use App\Modules\Plan\Application\Query\GetPlanHandler;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Presentation\Http\PlanJson;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

[$script, $out] = $argv + [null, null];
if (! is_string($out)) {
    fwrite(STDERR, "usage: php start-fixtures.php <out.json>\n");
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

// THE SWITCH, IN THIS PROCESS ONLY.
config(['access.paywall_enabled' => true]);

$result = ['db' => $session->db, 'paywall_enabled' => config('access.paywall_enabled')];

// A free QA plan (ru→ro, three days): the route and the rooms of day 1 and day 2.
$planRow = DB::table('plans')->where('id', '01M3F4WDHZX7VNSD35C6P67P7Z')->first(['id', 'user_id']);
$actor = UserId::fromString((string) $planRow->user_id);
$planId = PlanId::fromString((string) $planRow->id);
$result['plan_free'] = PlanJson::plan(app(GetPlanHandler::class)(new GetPlan($planId, $actor)));
foreach ([1, 2] as $n) {
    $room = app(GetDayRoomHandler::class)(new GetDayRoom($planId, $n, $actor));
    $result["room_free_day{$n}"] = PlanJson::room($room);
}

// The doctor plan (day 1 closed, day 2 next): with the switch on — 21-3 «по подписке».
$doctor = DB::table('plans')->where('id', '01M2QRH5MYEFZ1DEQ78RH54P6X')->first(['id', 'user_id']);
$doctorActor = UserId::fromString((string) $doctor->user_id);
$doctorId = PlanId::fromString((string) $doctor->id);
$result['plan_doctor'] = PlanJson::plan(app(GetPlanHandler::class)(new GetPlan($doctorId, $doctorActor)));
$result['room_doctor_day2'] = PlanJson::room(app(GetDayRoomHandler::class)(new GetDayRoom($doctorId, 2, $doctorActor)));

// Two talks for `line_native`: a day's (ru→de, day 1) and the doctor rehearsal (day 3).
foreach (['talk_day_ru_de' => '01M3DH6BPSZASRT1X97NXXXG7B', 'talk_rehearsal' => '01M3CPMKMHB5G3KEABW57ETPX0'] as $name => $id) {
    $row = DB::table('conversations')->join('plans', 'plans.id', '=', 'conversations.plan_id')->where('conversations.id', $id)->first(['plans.user_id']);
    $view = app(GetConversationHandler::class)(new GetConversation(ConversationId::fromString($id), UserId::fromString((string) $row->user_id)));
    $result[$name] = PlanJson::conversation($view);
}

file_put_contents($out, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)."\n");
fwrite(STDERR, "{$session->db}: written {$out}\n");

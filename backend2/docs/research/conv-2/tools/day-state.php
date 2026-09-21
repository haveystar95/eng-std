<?php

declare(strict_types=1);

/**
 * CONV-2 · WHERE A DAY'S SIXTH STAGE STANDS — read through the same handler the phone reads (`GET …/days/{n}`), nothing
 * written, nothing bought: the talk row of the window, the day room's stage row, the passage in the journal of stages,
 * the day's talks. Used before and after `plan:reconcile-talks` (report §1.2, §2.6, §6). The session is READ ONLY — it is
 * run on the live database too, and the database is told so rather than trusted.
 *
 *   docker exec wt_conv2_e2e php docs/research/conv-2/tools/day-state.php <plan> <day>
 *   the live stack:  docker cp …/day-state.php wt_app:/tmp/ && docker exec -e APP_ROOT=/app wt_app php /tmp/day-state.php <plan> <day>
 */

use App\Modules\Plan\Application\Query\GetDayRoom;
use App\Modules\Plan\Application\Query\GetDayRoomHandler;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = getenv('APP_ROOT') ?: dirname(__DIR__, 4);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Nothing below may write — and the database is told so, not asked nicely.
DB::statement('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');

[$planId, $number] = [$argv[1] ?? '', (int) ($argv[2] ?? 1)];
$plan = DB::table('plans')->where('id', $planId)->first();
if ($plan === null) {
    fwrite(STDERR, "no plan {$planId}\n");
    exit(1);
}
$day = DB::table('plan_days')->where('plan_id', $planId)->where('number', $number)->first();
$room = app(GetDayRoomHandler::class)(new GetDayRoom(PlanId::fromString($planId), $number, UserId::fromString((string) $plan->user_id)));

$talkRow = $room->window->stages[count($room->window->stages) - 1];
$roomRow = $room->stages[count($room->stages) - 1];
printf("день %d (%s), has_conversation=%s\n", $number, $day->status, $day->has_conversation ? 'true' : 'false');
printf("окно: %s — %s, «%s», сцен %s\n", $talkRow->stage, $talkRow->state, (string) $talkRow->talkTitleNative, var_export($talkRow->scenesCount, true));
printf("кабинет: %s — %s\n", $roomRow->stage, $roomRow->state);
printf("«Что было хорошо»: %s\n", $room->window->highlights === [] ? '—' : implode(' · ', $room->window->highlights));
$passage = DB::table('plan_stage_passages')->where('day_id', $day->id)->where('stage', 'conversation')->first();
printf("прохождение: %s\n", $passage === null ? 'нет' : "разговор {$passage->conversation_id} · {$passage->passed_at}");
foreach (DB::table('conversations')->where('day_id', $day->id)->orderBy('started_at')->get() as $talk) {
    printf("  разговор %s · %s · %s · %s → %s\n", $talk->id, $talk->state, $talk->ended_reason ?? '—', $talk->started_at, $talk->ended_at ?? '…');
}

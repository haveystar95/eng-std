<?php

declare(strict_types=1);

/**
 * GEN-1 — пересобрать ОДИН день плана на тестовой базе (то, что делает кнопка «Собрать заново»).
 *
 *   php docs/research/gen-1/tools/rebuild-day.php <plan_id> <day_index>
 *
 * Тот же `RebuildPlanDayHandler`, что и `POST /plans/{id}/days/{n}/rebuild`: день получает ещё два
 * вызова P2 и пишется в этом процессе (`QUEUE_CONNECTION=sync`).
 */

use App\Modules\Learning\Application\Command\RebuildPlanDay;
use App\Modules\Learning\Application\Command\RebuildPlanDayHandler;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

$app = require __DIR__ . '/bootstrap.php';

[$script, $planId, $dayIndex] = $argv + [null, null, null];
if ($dayIndex === null) {
    fwrite(STDERR, "usage: rebuild-day.php <plan_id> <day_index>\n");
    exit(2);
}

$plan = DB::table('learning_plans')->where('id', $planId)->first();
if ($plan === null) {
    fwrite(STDERR, "no plan {$planId}\n");
    exit(1);
}

$before = DB::table('generation_requests')->where('plan_id', $planId)->count();
$status = $app->make(RebuildPlanDayHandler::class)(new RebuildPlanDay(
    actorId: UserId::fromString((string) $plan->user_id),
    planId: (string) $planId,
    dayIndex: (int) $dayIndex,
));
$day = DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', (int) $dayIndex)->first();
$spend = DB::table('generation_requests')->where('plan_id', $planId)->orderBy('created_at')->skip($before)->take(1000)->get();

fwrite(STDOUT, sprintf(
    "day %d → %s (handler said %s) attempts=%d repairs=%d%s\n",
    (int) $dayIndex,
    $day->status,
    $status,
    $day->generation_attempts,
    $day->repair_calls,
    $day->fail_code !== null ? " FAIL {$day->fail_code}: " . mb_substr((string) $day->fail_reason, 0, 300) : '',
));
fwrite(STDOUT, sprintf("rebuild calls=%d cost=$%.6f\n", $spend->count(), (float) $spend->sum('cost_usd')));

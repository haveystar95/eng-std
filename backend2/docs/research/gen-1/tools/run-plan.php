<?php

declare(strict_types=1);

/**
 * GEN-1 — собрать ОДИН живой план на тестовой базе и написать его дни.
 *
 *   php docs/research/gen-1/tools/run-plan.php <slug> <support> <target> <level> <days_to_event> "<goal>"
 *
 * Делает ровно то, что делает телефон: пользователь + профиль → POST /plans → outline (P1) →
 * start (день 1 → день 2 … по очереди, `QUEUE_CONNECTION=sync`). Печатает id плана и статусы дней.
 * Что заплатили — в `generation_requests` с `plan_id` (читает `dump-plan.php`).
 *
 * Аккаунт: `gen1-<slug>@wt.test`. Один слаг — один пользователь; повторный запуск того же слага
 * ставит на паузу активный план (второй активный план запрещён) и заводит новый.
 */

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Learning\Application\Command\BuildPlanOutline;
use App\Modules\Learning\Application\Command\BuildPlanOutlineHandler;
use App\Modules\Learning\Application\Command\CreatePlan;
use App\Modules\Learning\Application\Command\CreatePlanHandler;
use App\Modules\Learning\Application\Command\StartPlan;
use App\Modules\Learning\Application\Command\StartPlanHandler;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;

$app = require __DIR__ . '/bootstrap.php';

[$script, $slug, $support, $target, $level, $daysToEvent, $goal] = $argv + [null, null, null, null, null, null, null];
if ($goal === null) {
    fwrite(STDERR, "usage: run-plan.php <slug> <support> <target> <level> <days_to_event> \"<goal>\"\n");
    exit(2);
}

$email = "gen1-{$slug}@wt.test";
$user = User::query()->where('email', $email)->first();
if ($user === null) {
    $user = User::query()->create([
        'name' => "GEN-1 {$slug}",
        'email' => $email,
        'email_verified_at' => now(),
        'google_id' => 'gen1-' . $slug . '-' . bin2hex(random_bytes(4)),
        'avatar' => null,
    ]);
}
Profile::updateOrCreate(['user_id' => $user->id], [
    'native_language' => $support,
    'target_language' => $target,
    'timezone' => 'Europe/Bucharest',
]);

// Второй активный план запрещён — предыдущий прогон того же слага уходит на паузу.
DB::table('learning_plans')->where('user_id', $user->id)->where('status', 'active')->update(['status' => 'paused']);

$userId = UserId::fromString((string) $user->id);
$eventDate = now()->addDays((int) $daysToEvent)->format('Y-m-d');

$startedAt = microtime(true);
$planId = $app->make(CreatePlanHandler::class)(new CreatePlan(
    actorId: $userId,
    goalText: $goal,
    targetLang: $target,
    level: $level,
    eventDate: $eventDate,
    minutesPerDay: 20,
));
fwrite(STDOUT, "plan {$planId->value} created for {$email} (event {$eventDate})\n");

$app->make(BuildPlanOutlineHandler::class)(new BuildPlanOutline($planId, $userId));
fwrite(STDOUT, "outline ready (" . round(microtime(true) - $startedAt) . "s)\n");

$app->make(StartPlanHandler::class)(new StartPlan($planId, $userId));
fwrite(STDOUT, "started; days written synchronously (" . round(microtime(true) - $startedAt) . "s)\n");

foreach (DB::table('learning_plan_days')->where('plan_id', $planId->value)->orderBy('day_index')->get() as $day) {
    fwrite(STDOUT, sprintf(
        "  day %d [%s] %s — %s attempts=%d repairs=%d%s\n",
        $day->day_index,
        $day->kind,
        $day->status,
        $day->title,
        $day->generation_attempts,
        $day->repair_calls,
        $day->fail_code !== null ? " FAIL {$day->fail_code}: " . mb_substr((string) $day->fail_reason, 0, 300) : '',
    ));
}

$spend = DB::table('generation_requests')->where('plan_id', $planId->value)->orderBy('created_at')->get();
fwrite(STDOUT, sprintf("calls=%d cost=$%.6f\n", $spend->count(), (float) $spend->sum('cost_usd')));
fwrite(STDOUT, "PLAN_ID={$planId->value}\n");

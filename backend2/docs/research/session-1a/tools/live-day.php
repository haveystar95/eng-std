<?php

declare(strict_types=1);

/**
 * SESSION-1a · THE DOCTOR'S DAY, DEALT LIVE — day 1 of the e2e doctor plan opened through the handlers production runs
 * (`StartPlan` when the plan is still `ready`, then `OpenDay`), and printed as the order asks: stage → cards → minutes by
 * `DayPace`, and every kind in the order it is walked. The room the client reads (`GET …/days/1`) is written next to the
 * report as JSON. No model call, no purchase: the lesson is the scene's stored one (`lesson_day.v4.4` on this database —
 * GEN-2b never wrote its v4.5 days into scenes).
 *
 * Run ONLY against the disposable database, after the session migration:
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync -e SPEECH_ENABLED=false \
 *     app php docs/research/session-1a/tools/live-day.php [plan id]
 */

use App\Modules\Plan\Application\Command\OpenDay;
use App\Modules\Plan\Application\Command\OpenDayHandler;
use App\Modules\Plan\Application\Command\StartPlan;
use App\Modules\Plan\Application\Command\StartPlanHandler;
use App\Modules\Plan\Application\Query\GetDayRoom;
use App\Modules\Plan\Application\Query\GetDayRoomHandler;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Presentation\Http\PlanJson;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.pgsql.database');
if ($database === 'wordtrainer') {
    fwrite(STDERR, "Refusing to run against the main database.\n");
    exit(1);
}

$planId = $argv[1] ?? '01M2H13E1QT6F5D4FKJSEKTAD7';
$plan = DB::table('plans')->where('id', $planId)->first();
if ($plan === null) {
    fwrite(STDERR, "No plan {$planId} on {$database}.\n");
    exit(1);
}
$actor = UserId::fromString((string) $plan->user_id);
$scene = DB::table('plan_scenes')->where('plan_id', $planId)->orderBy('order')->first();
echo "database={$database} plan={$planId} level={$plan->level} status={$plan->status} scene={$scene?->id} lesson={$scene?->prompt_version_lesson}\n";

if ($plan->status === 'ready') {
    app(StartPlanHandler::class)(new StartPlan(PlanId::fromString($planId), $actor));
    echo "started\n";
}
app(OpenDayHandler::class)(new OpenDay(PlanId::fromString($planId), 1, $actor));

$day = DB::table('plan_days')->where('plan_id', $planId)->where('number', 1)->first();
$cards = app(DayCardRepository::class)->forDay(PlanDayId::fromString((string) $day->id));
$pace = app(DayPace::class);

echo "\n| этап | карточек | минут по DayPace | секунд |\n|---|---|---|---|\n";
$totalSeconds = 0;
foreach (Stage::ordered() as $stage) {
    $of = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === $stage));
    $seconds = $pace->secondsOf($of);
    $totalSeconds += $seconds;
    echo '| '.$stage->value.' | '.count($of).' | '.DayPace::minutes($seconds)." | {$seconds} |\n";
}
echo '| **день** | **'.count($cards).'** | **'.DayPace::minutes($totalSeconds)."** | {$totalSeconds} |\n";

echo "\nВиды по порядку:\n";
foreach (Stage::ordered() as $stage) {
    $of = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === $stage));
    echo "- {$stage->value}: ".implode(', ', array_map(
        static fn (DayCard $c): string => $c->kind()->value.' '.$c->unitRef(),
        $of,
    ))."\n";
}

$room = app(GetDayRoomHandler::class)(new GetDayRoom(PlanId::fromString($planId), 1, $actor));
$out = __DIR__.'/../e2e-day-doctor.json';
file_put_contents($out, json_encode(PlanJson::room($room), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
echo "\nroom → ".realpath($out).' (window.day.minutes_estimate='.json_encode(PlanJson::room($room)['window']['day']['minutes_estimate']).")\n";

<?php

declare(strict_types=1);

/**
 * SESSION-1d · A DAY'S OUTLINE, NOTHING WRITTEN — day N of a plan dealt by the production dealer as the room would draw
 * it before the day is open (`DayDealer::outline`: the same assembler, no ids kept, no row written): stage → cards →
 * seconds, and the phrase table. What a re-deal will give, checked against the stop condition before anything is dealt.
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/session-1d/tools/outline.php [plan id] [day]
 */

use App\Modules\Plan\Application\Service\DayDealer;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require __DIR__.'/phrase-table.php';

$database = (string) config('database.connections.pgsql.database');
$planId = $argv[1] ?? '01M2H13E1QT6F5D4FKJSEKTAD7';
$number = (int) ($argv[2] ?? 1);
$plan = app(PlanRepository::class)->findById(PlanId::fromString($planId));
if ($plan === null) {
    fwrite(STDERR, "No plan {$planId} on {$database}.\n");
    exit(1);
}
$cards = app(DayDealer::class)->outline($plan, $plan->day($number));
$pace = app(DayPace::class);

echo "database={$database} plan={$planId} level={$plan->level()->value} day={$number}\n| этап | карточек | секунд | минут |\n|---|---|---|---|\n";
$total = 0;
foreach (Stage::ordered() as $stage) {
    $of = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === $stage));
    $seconds = $pace->secondsOf($of);
    $total += $seconds;
    echo "| {$stage->value} | ".count($of)." | {$seconds} | ".DayPace::minutes($seconds)." |\n";
}
echo '| день | '.count($cards)." | {$total} | ".DayPace::minutes($total)." |\n";
s1dPrintPhrases(array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === Stage::Phrases)), $pace);

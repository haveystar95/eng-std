<?php

declare(strict_types=1);

/**
 * SESSION-1e · A DAY'S OUTLINE, NOTHING WRITTEN — day N of a plan dealt by the production dealer as the room draws it
 * before the day is open (`DayDealer::outline`: the same assembler, no ids kept, no row written), printed as the report
 * asks: stage → cards → minutes, the words' checks, `word_listen`, `phrase_combine`, the phrase table and — when the
 * scene has seam-judge findings — the fillers no card shows. With `--starts` the words' checks of this lesson are counted
 * at every start of the circle (the scene's id swapped for ids found to start each place; nothing written).
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/session-1e/tools/outline.php [plan id] [day] [--starts]
 */

use App\Modules\Plan\Application\Service\DayDealer;
use App\Modules\Plan\Domain\Assembly\DayAssembler;
use App\Modules\Plan\Domain\Assembly\Rotation;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Assembly\WordChecks;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require __DIR__.'/day-tables.php';

$positional = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => ! str_starts_with($a, '--')));
$starts = in_array('--starts', $argv, true);
$database = (string) config('database.connections.pgsql.database');
$planId = $positional[0] ?? '01M2H13E1QT6F5D4FKJSEKTAD7';
$number = (int) ($positional[1] ?? 1);
$plan = app(PlanRepository::class)->findById(PlanId::fromString($planId));
if (! $plan instanceof Plan) {
    fwrite(STDERR, "No plan {$planId} on {$database}.\n");
    exit(1);
}
$day = $plan->day($number);
$sceneEntity = $plan->sceneOf($day);
if ($sceneEntity === null || $sceneEntity->lesson() === null) {
    fwrite(STDERR, "Day {$number} has no lesson.\n");
    exit(1);
}
$packs = app(LanguagePacks::class);
$terms = app(PlanTermRepository::class)->forScenes([$sceneEntity->id()])[$sceneEntity->id()->value] ?? [];
$material = static fn (PlanSceneId $id): SceneMaterial => new SceneMaterial(
    $id, $sceneEntity->lesson(), $terms, $packs->for($plan->targetLang()->value), $packs->for($plan->nativeLang()->value), $sceneEntity->unreadableFillers(),
);
$scene = $material($sceneEntity->id());
$pace = app(DayPace::class);

if ($starts) {
    // The seeds are the scene's id: the same lesson under other ids starts the circle elsewhere. Terms keep their refs.
    $found = [];
    for ($n = 1; count($found) < 4 && $n < 500; $n++) {
        $id = sprintf('01J8SESS1ESTARTS0000%06d', $n);
        $start = Rotation::pick($id.':words:check', 0, WordChecks::CYCLE)->value;
        if (isset($found[$start])) {
            continue;
        }
        $found[$start] = $id;
        $other = $material(PlanSceneId::fromString($id));
        $cards = app(DayAssembler::class)->sceneDay(PlanDayId::generate(), $other, [$id => $other], $plan->level(), [], [], static fn (): DayCardId => DayCardId::generate());
        echo "\n===== старт круга {$start} · id {$id}\n";
        s1ePrintWords($cards, $other);
    }
    exit(0);
}

$cards = app(DayDealer::class)->outline($plan, $day);
echo "database={$database} plan={$planId} level={$plan->level()->value} day={$number} scene={$sceneEntity->id()->value} findings(filler.native_seam)=".json_encode($sceneEntity->unreadableFillers())."\n\n";
s1eStageTable($cards, $pace);
s1ePrintWords($cards, $scene);
s1ePrintListen($cards);
s1ePrintCombine($cards, $scene);
s1dPrintPhrases(array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === Stage::Phrases)), $pace);

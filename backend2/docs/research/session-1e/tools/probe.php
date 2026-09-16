<?php

declare(strict_types=1);

/**
 * SESSION-1e · THE CLEAN DOCTOR LESSON, DEALT WITHOUT A DATABASE — the fixture's scene (`FakePlanModel`, the pinned scene
 * id of `SessionDayFixtureTest`) through the production assembler at both levels: stage → cards → seconds, the words'
 * checks, `word_listen`, `phrase_combine` and the phrase table. With `--unreadable=p1.f2,p6.f3` the scene carries those
 * seam-judge findings (the artificial finding of the report), and the table of hidden fillers is printed. With
 * `--starts` the words' checks are counted at every start of the circle (scene ids searched until each start is found).
 *
 *   docker compose exec -T app php docs/research/session-1e/tools/probe.php [scene id] [--unreadable=…] [--starts]
 */

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Assembly\DayAssembler;
use App\Modules\Plan\Domain\Assembly\Rotation;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Assembly\WordChecks;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
require __DIR__.'/day-tables.php';

$positional = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => ! str_starts_with($a, '--')));
$unreadable = [];
$starts = false;
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--unreadable=')) {
        $unreadable = array_values(array_filter(explode(',', substr($arg, 13))));
    }
    $starts = $starts || $arg === '--starts';
}
$packs = app(LanguagePacks::class);
$pace = app(DayPace::class);
$scene = static function (string $id, PlanLevel $level, array $unreadable) use ($packs): SceneMaterial {
    $sceneId = PlanSceneId::fromString($id);
    $payload = FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', $level, null, 8, 8));
    $lesson = LessonAssembly::serve((new LessonParser)->parse($payload), $sceneId->value, $packs->for('en'));

    return new SceneMaterial($sceneId, $lesson, PlanTerm::fromLesson($sceneId, $lesson, static fn (): PlanTermId => PlanTermId::generate()), $packs->for('en'), $packs->for('ru'), $unreadable);
};
$deal = static fn (SceneMaterial $s, PlanLevel $level): array => app(DayAssembler::class)->sceneDay(
    PlanDayId::generate(), $s, [$s->sceneId->value => $s], $level, [], [], static fn (): DayCardId => DayCardId::generate(),
);

if ($starts) {
    $found = [];
    for ($n = 1; count($found) < 4 && $n < 500; $n++) {
        $id = sprintf('01J8SESS1XTVRESCENE%07d', $n);
        $start = Rotation::pick($id.':words:check', 0, WordChecks::CYCLE)->value;
        if (isset($found[$start])) {
            continue;
        }
        $found[$start] = $id;
        $s = $scene($id, PlanLevel::Intermediate, []);
        echo "\n===== старт круга {$start} · scene {$id}\n";
        s1ePrintWords($deal($s, PlanLevel::Intermediate), $s);
    }
    exit(0);
}

$id = $positional[0] ?? '01J8SESS1XTVRESCENE0000001';
foreach ([PlanLevel::Intermediate, PlanLevel::Beginner] as $level) {
    $s = $scene($id, $level, $unreadable);
    $cards = $deal($s, $level);
    echo "\n===== {$level->value} · scene {$id}".($unreadable === [] ? '' : ' · находки судьи швов: '.implode(', ', $unreadable))."\n";
    s1eStageTable($cards, $pace);
    s1ePrintWords($cards, $s);
    s1ePrintListen($cards);
    s1ePrintCombine($cards, $s);
    s1dPrintPhrases(array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === Stage::Phrases)), $pace);
    if ($unreadable !== []) {
        s1ePrintHidden($cards, $s, $unreadable);
    }
}

<?php

declare(strict_types=1);

/**
 * SESSION-1d · THE CLEAN DOCTOR LESSON, DEALT WITHOUT A DATABASE — the fixture's scene (`FakePlanModel`, the pinned scene
 * id of `SessionDayFixtureTest`) through the production assembler at both levels: stage → cards → seconds, and the phrase
 * table. What the fixtures will hold, before they are written.
 *
 *   docker compose exec -T app php docs/research/session-1d/tools/probe.php
 */

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Domain\Assembly\DayAssembler;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
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
require __DIR__.'/phrase-table.php';

$sceneId = PlanSceneId::fromString($argv[1] ?? '01J8SESS1XTVRESCENE0000001');
$packs = app(LanguagePacks::class);
$pace = app(DayPace::class);
foreach ([PlanLevel::Intermediate, PlanLevel::Beginner] as $level) {
    $payload = FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', $level, null, 8, 8));
    $lesson = LessonAssembly::serve((new LessonParser)->parse($payload), $sceneId->value, $packs->for('en'));
    $scene = new SceneMaterial($sceneId, $lesson, PlanTerm::fromLesson($sceneId, $lesson, static fn (): PlanTermId => PlanTermId::generate()), $packs->for('en'), $packs->for('ru'));
    $cards = app(DayAssembler::class)->sceneDay(PlanDayId::generate(), $scene, [$sceneId->value => $scene], $level, [], [], static fn (): DayCardId => DayCardId::generate());

    echo "\n===== {$level->value} · scene {$sceneId->value}\n| этап | карточек | секунд | минут |\n|---|---|---|---|\n";
    $total = 0;
    foreach (Stage::ordered() as $stage) {
        $of = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === $stage));
        $seconds = $pace->secondsOf($of);
        $total += $seconds;
        echo "| {$stage->value} | ".count($of)." | {$seconds} | ".DayPace::minutes($seconds)." |\n";
    }
    echo '| день | '.count($cards)." | {$total} | ".DayPace::minutes($total)." |\n";
    s1dPrintPhrases(array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === Stage::Phrases)), $pace);
}

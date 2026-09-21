<?php

declare(strict_types=1);

/**
 * BACK-TAILS-2 §1 · THE LADDER OF «ФРАЗЫ» ON REAL DAYS — before and after (report §1.1).
 *
 *   outline <plan> <day>   — the day as the dealer deals it (`DayDealer::outline()`: the same assembler, nothing written),
 *                            «Фразы» frame by frame (recognitions · value rounds · own word), the stage's seconds against
 *                            its ceiling and the day's cards against theirs. Runs on the code BEFORE the наряд and AFTER it.
 *   rungs <plan> <day>     — the наряд's code only: the stage after every rung of the ladder (`PhrasesDeal::rungs`).
 *   fixture <level>        — the наряд's code only: the same for the clean «врач» of `docs/fixtures/day-doctor*.json`
 *                            (the fake lesson as `planCleanLesson` serves it, under the fixture's pinned scene id).
 *
 * The session is READ ONLY — it reads the live databases and must not be able to write to them.
 *
 *   before: docker exec -e APP_ROOT=/app -e DB_DATABASE=<db> wt_tails2 php /wt/docs/research/back-tails-2/tools/ladder.php outline <plan> <day>
 *   after:  docker exec -e DB_DATABASE=<db> wt_tails2 php docs/research/back-tails-2/tools/ladder.php outline|rungs <plan> <day>
 */

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Service\DayDealer;
use App\Modules\Plan\Domain\Assembly\DayAssembler;
use App\Modules\Plan\Domain\Assembly\PhrasesDeal;
use App\Modules\Plan\Domain\Assembly\PhrasesStage;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Entity\Plan;
use App\Modules\Plan\Domain\Entity\PlanTerm;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Service\DayPace;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\PlanTermId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = getenv('APP_ROOT') ?: dirname(__DIR__, 4);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

DB::statement('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');

$mode = $argv[1] ?? '';
$budget = (int) config('plan.phrases_budget', PhrasesStage::BUDGET);
$rungNames = [0 => 'собрано', 1 => '1) третье узнавание', 2 => '2) третий круг', 3 => '3) второе узнавание'];

/** @param list<array{rung: int, seconds: int, cards: int}> $rungs */
function printRungs(array $rungs, int $budget, array $names): void
{
    foreach ($rungs as $r) {
        printf("  %s %4d с · %2d карточек%s\n", mb_str_pad($names[$r['rung']], 22), $r['seconds'], $r['cards'], $r['seconds'] > $budget ? '  (над потолком)' : '');
    }
}

/** @param array<string, array{recognitions: int, rounds: int, own: bool}> $frames */
function printFrames(PhrasesDeal $deal): void
{
    foreach ($deal->frames as $ref => $f) {
        printf("  %-3s узнаваний %d · кругов %d%s\n", $ref, $f['recognitions'], $f['rounds'], $f['own'] ? ' + своё' : '');
    }
    printf("итог: %d с при потолке %d%s\n", $deal->seconds, $deal->budget, $deal->overCeiling() ? ' — СТОП-СИГНАЛ (+'.($deal->seconds - $deal->budget).' с), день раздаётся' : ' — под потолком');
}

if ($mode === 'fixture') {
    $level = PlanLevel::from($argv[2] ?? 'intermediate');
    $packs = app(LanguagePacks::class);
    $id = PlanSceneId::fromString('01J8SESS1XTVRESCENE0000001');
    $payload = FakePlanModel::lessonPayload(new LessonRequest('Приём у врача', 'x', 'English', 'Russian', $level, null, 8, 8, FakePlanModel::roles(), new EarlierDays));
    // `planCleanLesson` (tests/Pest.php): the fake's dialogue with exchanges 4 and 7 swapped — the lesson the fixtures hold.
    [$payload['dialogue'][3], $payload['dialogue'][6]] = [$payload['dialogue'][6], $payload['dialogue'][3]];
    $payload['dialogue'][3]['step'] = 4;
    $payload['dialogue'][6]['step'] = 7;
    foreach ($payload['vocabulary'] as $i => $item) {
        $payload['vocabulary'][$i]['used_in'] = array_map(static fn (string $ref): string => ['A4' => 'A7', 'A7' => 'A4'][$ref] ?? $ref, $item['used_in']);
    }
    $lesson = LessonAssembly::serve((new LessonParser)->parse($payload), $id->value, $packs->for('en'));
    $scene = new SceneMaterial($id, $lesson, PlanTerm::fromLesson($id, $lesson, static fn (): PlanTermId => PlanTermId::generate()), $packs->for('en'), $packs->for('ru'));
    $deal = app(DayAssembler::class)->phrasesDeal($scene, $level);

    printf("фикстура «врач» (FakePlanModel, чистый урок) · уровень %s · потолок «Фраз» %d с\n", $level->value, $budget);
    printRungs($deal->rungs, $budget, $rungNames);
    printFrames($deal);
    exit(0);
}

[$planId, $number] = [$argv[2] ?? '', (int) ($argv[3] ?? 1)];
$plan = app(PlanRepository::class)->findById(PlanId::fromString($planId));
if (! $plan instanceof Plan) {
    fwrite(STDERR, "нет плана {$planId}\n");
    exit(1);
}
$day = $plan->day($number);
printf("план %s · день %d · уровень %s · база %s · код %s · потолок «Фраз» %d с\n",
    $planId, $number, $plan->level()->value, (string) config('database.connections.pgsql.database'), $root, $budget);

if ($mode === 'rungs') {
    $scene = $plan->sceneOf($day);
    $dealer = app(DayDealer::class);
    // The dealer's own material of the scene — what `deal()` reads the ladder off; private, so reached as the dealer.
    $material = (fn (Plan $p, array $ids): array => $this->material($p, $ids))->call($dealer, $plan, [$scene->id()])[$scene->id()->value];
    $deal = app(DayAssembler::class)->phrasesDeal($material, $plan->level());
    printRungs($deal->rungs, $budget, $rungNames);
    printFrames($deal);
    exit(0);
}

if ($mode !== 'outline') {
    fwrite(STDERR, "режимы: outline <plan> <day> | rungs <plan> <day> | fixture <level>\n");
    exit(1);
}

$cards = app(DayDealer::class)->outline($plan, $day);
$pace = app(DayPace::class);
$phrases = array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === Stage::Phrases));
$seconds = array_sum(array_map(static fn (DayCard $c): int => $pace->seconds($c->kind(), $c->payload()), $phrases));
printf("«Фразы»: %d карточек · %d с по DayPace%s\n", count($phrases), $seconds, $seconds > $budget ? ' (над потолком на '.($seconds - $budget).' с)' : '');

$notRecognition = [CardKind::PhraseIntro, CardKind::PhraseOtherSlot, CardKind::PhraseRepeat, CardKind::PhraseCombine];
$recognitions = [];
foreach ($phrases as $card) {
    if (! in_array($card->kind(), $notRecognition, true)) {
        $recognitions[$card->unitRef()] = ($recognitions[$card->unitRef()] ?? 0) + 1;
    }
}
foreach ($phrases as $card) {
    if ($card->kind() !== CardKind::PhraseOtherSlot) {
        continue;
    }
    $payload = $card->payload();
    $fillers = array_map(static fn (array $f): string => (string) $f['target'], $payload['frame']['slot']['fillers'] ?? []);
    printf("  %-3s %-32s значений %d · узнаваний %d · кругов %d%s\n", $card->unitRef(), (string) ($payload['frame']['frame_target'] ?? ''),
        count($fillers), $recognitions[$card->unitRef()] ?? 0, count($payload['rounds'] ?? []), ($payload['own_round'] ?? null) === null ? '' : ' + своё');
}
$daySeconds = array_sum(array_map(static fn (DayCard $c): int => $pace->seconds($c->kind(), $c->payload()), $cards));
printf("день: %d карточек · %d с = %d мин по DayPace (потолок карточек — %d мин)\n", count($cards), $daySeconds, (int) ceil($daySeconds / 60), (int) config('plan.day_cards_budget', 32));

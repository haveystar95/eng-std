<?php

declare(strict_types=1);

/**
 * SESSION-1e · THE DOCTOR'S DAY, DEALT AGAIN — AND ROLLED BACK. Day 1 of the e2e doctor plan dealt anew through the handler
 * production runs (`OpenDay`), printed as the order asks: stage → cards → minutes by `DayPace`, the words' checks (word →
 * kind → direction), the options of every `word_listen`, the `phrase_combine` (its exchange, whether the partner's line
 * asks, `said` of every frame) and the phrase table. Then — in the same transaction — the scene is given artificial
 * seam-judge findings (the lesson is v4.4, the judge came with v4.5: it has none of its own), the day is dealt once more,
 * and the fillers no card shows are printed.
 *
 * EVERYTHING HAPPENS IN ONE TRANSACTION THAT IS ROLLED BACK: day 1 of this plan was walked by the client session
 * (SESSION-1b) and is kept as it is — its cards, its answers, the scene's findings. What stays is the report printed here
 * and the room written next to it as JSON. No model call, no purchase.
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync -e SPEECH_ENABLED=false \
 *     app php docs/research/session-1e/tools/live-day.php [plan id] [--unreadable=p2.f1,p2.f2,p2.f3,p4.f2]
 */

use App\Modules\Plan\Application\Command\OpenDay;
use App\Modules\Plan\Application\Command\OpenDayHandler;
use App\Modules\Plan\Application\Query\GetDayRoom;
use App\Modules\Plan\Application\Query\GetDayRoomHandler;
use App\Modules\Plan\Domain\Assembly\SceneMaterial;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Repository\PlanTermRepository;
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
require __DIR__.'/day-tables.php';

$database = (string) config('database.connections.pgsql.database');
if ($database !== 'wordtrainer_e2e_test') {
    fwrite(STDERR, "Runs only against wordtrainer_e2e_test (got {$database}).\n");
    exit(1);
}

$positional = array_values(array_filter(array_slice($argv, 1), static fn (string $a): bool => ! str_starts_with($a, '--')));
$unreadable = ['p2.f1', 'p2.f2', 'p2.f3', 'p4.f2'];
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--unreadable=')) {
        $unreadable = array_values(array_filter(explode(',', substr($arg, 13))));
    }
}
$planId = $positional[0] ?? '01M2H13E1QT6F5D4FKJSEKTAD7';
$plan = DB::table('plans')->where('id', $planId)->first();
if ($plan === null) {
    fwrite(STDERR, "No plan {$planId} on {$database}.\n");
    exit(1);
}
$actor = UserId::fromString((string) $plan->user_id);
$sceneRow = DB::table('plan_scenes')->where('plan_id', $planId)->orderBy('order')->first();
$pace = app(DayPace::class);
$cardsRepo = app(DayCardRepository::class);

/** @return array<string, mixed> what the rollback must give back */
$snapshot = static function () use ($planId): array {
    $days = DB::table('plan_days')->where('plan_id', $planId)->orderBy('number')->get(['number', 'status', 'cards_total', 'cards_done', 'minutes_spent', 'opens_on'])->map(static fn ($d): array => (array) $d)->all();
    $cards = DB::table('day_cards')->join('plan_days', 'plan_days.id', '=', 'day_cards.day_id')->where('plan_days.plan_id', $planId);

    return [
        'days' => $days,
        'cards' => (clone $cards)->count(),
        'answered' => (clone $cards)->whereNotNull('day_cards.answered_at')->count(),
        'hash' => md5((string) json_encode((clone $cards)->orderBy('day_cards.id')->get(['day_cards.id', 'day_cards.kind', 'day_cards.result', 'day_cards.attempts', 'day_cards.payload'])->all())),
        'findings' => md5((string) DB::table('plan_scenes')->where('plan_id', $planId)->orderBy('order')->pluck('checks_json')->implode('|')),
    ];
};

/** The scene as the dealer reads it now — its findings included. */
$material = static function () use ($planId): SceneMaterial {
    $plan = app(PlanRepository::class)->findById(PlanId::fromString($planId));
    $scene = $plan?->sceneOf($plan->day(1));
    if ($plan === null || $scene === null || $scene->lesson() === null) {
        throw new RuntimeException('No lesson on day 1.');
    }
    $packs = app(LanguagePacks::class);

    return new SceneMaterial(
        $scene->id(), $scene->lesson(), app(PlanTermRepository::class)->forScenes([$scene->id()])[$scene->id()->value] ?? [],
        $packs->for($plan->targetLang()->value), $packs->for($plan->nativeLang()->value), $scene->unreadableFillers(),
    );
};

/** @return list<DayCard> day 1 dealt anew */
$redeal = static function () use ($planId, $actor, $cardsRepo): array {
    $day1 = DB::table('plan_days')->where('plan_id', $planId)->where('number', 1)->first();
    DB::table('day_cards')->where('day_id', $day1->id)->delete();
    app(OpenDayHandler::class)(new OpenDay(PlanId::fromString($planId), 1, $actor));

    return $cardsRepo->forDay(PlanDayId::fromString((string) $day1->id));
};

$before = $snapshot();
echo "database={$database} plan={$planId} level={$plan->level} status={$plan->status} scene={$sceneRow?->id} lesson={$sceneRow?->prompt_version_lesson}\n";
echo 'до: день 1 — '.json_encode($before['days'][0] ?? null, JSON_UNESCAPED_UNICODE).", карточек {$before['cards']}, отвечено {$before['answered']}\n";

DB::beginTransaction();
try {
    $cards = $redeal();
    $scene = $material();

    echo "\n## День 1, пере-роздан (находки судьи швов у сцены: ".json_encode(array_values(array_filter(array_map(
        static fn (array $f): ?string => $f['code'] === 'filler.native_seam' ? $f['address'] : null,
        json_decode((string) $sceneRow->checks_json, true) ?: [],
    )))).")\n\n";
    s1eStageTable($cards, $pace);
    s1ePrintWords($cards, $scene);
    echo "\n";
    s1ePrintListen($cards);
    echo "\n";
    s1ePrintCombine($cards, $scene);
    s1dPrintPhrases(array_values(array_filter($cards, static fn (DayCard $c): bool => $c->stage() === Stage::Phrases)), $pace);

    $room = app(GetDayRoomHandler::class)(new GetDayRoom(PlanId::fromString($planId), 1, $actor));
    $roomJson = PlanJson::room($room);
    $out = __DIR__.'/../e2e-day-doctor.json';
    file_put_contents($out, json_encode($roomJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)."\n");
    echo "\nroom → docs/research/session-1e/e2e-day-doctor.json (window.day.minutes_estimate=".json_encode($roomJson['window']['day']['minutes_estimate']).")\n";

    // ── artificial seam-judge findings, in the same transaction ─────────────────────────────────────────────────────
    $findings = json_decode((string) $sceneRow->checks_json, true) ?: [];
    foreach ($unreadable as $address) {
        $findings[] = ['code' => 'filler.native_seam', 'address' => $address, 'detail' => 'SESSION-1e live check: artificial finding, rolled back'];
    }
    DB::table('plan_scenes')->where('id', $sceneRow->id)->update(['checks_json' => json_encode($findings, JSON_UNESCAPED_UNICODE)]);
    $hidden = $redeal();
    $seamed = $material();

    echo "\n## День 1 с искусственными находками судьи швов: ".implode(', ', $unreadable)."\n\n";
    s1eStageTable($hidden, $pace);
    s1ePrintHidden($hidden, $seamed, $unreadable);
    s1dPrintPhrases(array_values(array_filter($hidden, static fn (DayCard $c): bool => $c->stage() === Stage::Phrases)), $pace);
} finally {
    DB::rollBack();
}

$after = $snapshot();
echo "\nпосле отката: день 1 — ".json_encode($after['days'][0] ?? null, JSON_UNESCAPED_UNICODE).", дней ".count($before['days']).' → '.count($after['days']).", карточек {$after['cards']}, отвечено {$after['answered']}\n";
echo 'состояние e2e совпадает с исходным: '.json_encode($after === $before)."\n";

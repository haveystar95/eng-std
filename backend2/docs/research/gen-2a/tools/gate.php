<?php

declare(strict_types=1);

/**
 * GEN-2a · THE GATE ON THE SIX LIVE DAYS — what the thresholds (решение архитектора после отчёта) do to lessons the
 * model really wrote.
 *
 * For every run in `runs.json`: the stored answer is validated with the code as it is now; a day without a fatal
 * finding passes; a day with one goes through `LessonGateKeeper` exactly as the build runs it — the REAL model
 * repairs the cards (P2R, ≈ $0.02 a card, at most two a day). Nothing is written: the outcome, the cards asked,
 * each card before and after, the repair cost and time go to `gate.json` and the terminal.
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/gen-2a/tools/gate.php
 */

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Application\Service\LessonCardRepairer;
use App\Modules\Plan\Application\Service\LessonGateKeeper;
use App\Modules\Plan\Domain\Check\LessonGate;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$dir = realpath(__DIR__.'/..');
$runs = json_decode((string) file_get_contents($dir.'/runs.json'), true);
$rows = static fn (array $violations): array => array_map(static fn (LessonViolation $v): string => "{$v->code}@{$v->address}", $violations);
$out = [];

foreach ($runs as $run) {
    $scene = DB::table('plan_scenes')->where('id', $run['scene_id'])->first();
    if ($scene === null) {
        fwrite(STDERR, "no scene for {$run['slug']}\n");
        continue;
    }
    $level = PlanLevel::from($run['level']);
    $counts = $app->make(PlanConfig::class)->countsFor($level);
    $request = new LessonRequest((string) $scene->title_native, (string) $scene->topic_description, 'English', 'Russian', $level, null,
        $counts['vocabulary'], $counts['dialogue'], [], 'en', 'ru');
    $context = LessonCardRepairer::contextOf($request);
    $answer = (new LessonParser)->parse(json_decode((string) $scene->lesson_json, true));
    $found = $app->make(LessonValidator::class)->run($answer, $context);
    $fatal = LessonGate::fatal($found);

    $row = ['slug' => $run['slug'], 'findings' => count($found), 'fatal' => $rows($fatal)];
    if ($fatal === []) {
        $row['outcome'] = 'passes — no fatal finding, no repair';
        $out[] = $row;
        fwrite(STDOUT, "{$run['slug']}: passes, {$row['findings']} findings (warnings)\n");
        continue;
    }

    $t0 = microtime(true);
    $gate = $app->make(LessonGateKeeper::class)->pass($answer, $found, $context, $request);
    $row['outcome'] = $gate->answer === null ? "failed — {$gate->failReason}" : 'passes after repair';
    $row['cards_asked'] = $gate->cardsAsked;
    $row['repair_cost_usd'] = $gate->repairCostUsd;
    $row['repair_latency_ms'] = $gate->repairLatencyMs;
    $row['wall_s'] = round(microtime(true) - $t0, 1);
    $row['findings_after'] = count($gate->findings);
    $row['fatal_after'] = $rows(LessonGate::fatal($gate->findings));
    $row['cards'] = [];
    foreach ($gate->cardsAsked as $address) {
        $card = LessonCard::at($address);
        $row['cards'][$address] = ['before' => $card?->of($answer), 'after' => $gate->answer === null ? null : $card?->of($gate->answer)];
    }
    $out[] = $row;
    fwrite(STDOUT, sprintf("%s: %s · cards %s · $%s · %d ms · findings %d → %d · fatal before %s\n",
        $run['slug'], $row['outcome'], implode(', ', $gate->cardsAsked), $gate->repairCostUsd, $gate->repairLatencyMs, $row['findings'], $row['findings_after'], implode(', ', $row['fatal'])));
}

file_put_contents($dir.'/gate.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
fwrite(STDOUT, "gate.json written\n");

<?php

declare(strict_types=1);

/**
 * GEN-2b · THE GATE ON THE LIVE v4.5 ANSWERS, PER REPAIR MODEL — what the seven fatal codes and at most two P2R cards
 * do to the answers the model really wrote, with the validator as it is now and the repair on the model named.
 *
 * For every day in `runs.json`: the raw answer (`answers/<slug>.json`) is validated; a day without a fatal finding
 * passes; a day with one goes through `LessonGateKeeper` exactly as the build runs it, P2R v1.1 on the given model
 * (narrow context). Nothing is written but `gate-<model>.json`: the outcome, the cards asked, each card before and
 * after, cost and time. The seam judge is not asked here — it does not touch the gate.
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/gen-2b/tools/gate.php gpt-5.4-mini
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/gen-2b/tools/gate.php gpt-5.4
 */

use App\Modules\Generation\Application\Port\ContentModelCatalog;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Service\LessonCardRepairer;
use App\Modules\Plan\Application\Service\LessonContexts;
use App\Modules\Plan\Application\Service\LessonGateKeeper;
use App\Modules\Plan\Domain\Check\LessonGate;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Model\ContentModelPlanBuilder;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Shared\Domain\Service\LanguageName;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if ((string) config('database.connections.pgsql.database') === 'wordtrainer') {
    fwrite(STDERR, "Refusing to run against the main database.\n");
    exit(1);
}

$model = $argv[1] ?? 'gpt-5.4-mini';
$dir = realpath(__DIR__.'/..');
$runs = json_decode((string) file_get_contents("{$dir}/runs.json"), true);
$builder = new ContentModelPlanBuilder(
    catalog: $app->make(ContentModelCatalog::class),
    prompts: $app->make(PlanPromptFiles::class),
    provider: ProviderId::OpenAi,
    planModel: 'gpt-5.4',
    lessonModel: 'gpt-5.4',
    planTimeout: 90,
    lessonTimeout: 90,
    repairModel: $model,
    judgeModel: 'gpt-5.4-mini',
);
$validator = $app->make(LessonValidator::class);
$gate = new LessonGateKeeper($app->make(LessonCardRepairer::class, ['model' => $builder]), $validator);
$rows = static fn (array $violations): array => array_map(static fn (LessonViolation $v): string => "{$v->code}@{$v->address}", $violations);
$out = [];

foreach ($runs as $run) {
    $native = explode('→', $run['pair'])[0];
    $request = new LessonRequest($run['topic'], $run['topic_description'], 'English', LanguageName::of($native), PlanLevel::from($run['level']), null, 8, 8, [], 'en', $native);
    $context = $app->make(LessonContexts::class)->of($request);
    $answer = (new LessonParser)->parse(json_decode((string) file_get_contents("{$dir}/answers/{$run['slug']}.json"), true));
    $found = $validator->run($answer, $context);
    $fatal = LessonGate::fatal($found);
    $row = ['slug' => $run['slug'], 'model' => $model, 'findings' => count($found), 'fatal' => $rows($fatal)];
    if ($fatal === []) {
        $row += ['outcome' => 'passes', 'cards_asked' => [], 'repair_cost_usd' => '0.000000', 'repair_latency_ms' => 0, 'findings_after' => count($found), 'fatal_after' => []];
        $out[] = $row;
        fwrite(STDOUT, "{$run['slug']}: passes without a repair · {$row['findings']} warnings\n");
        continue;
    }
    $passed = $gate->pass($answer, $found, $context, $request);
    $row += [
        'outcome' => $passed->answer === null ? "failed — {$passed->failReason}" : 'passes after repair',
        'cards_asked' => $passed->cardsAsked,
        'repair_cost_usd' => $passed->repairCostUsd,
        'repair_latency_ms' => $passed->repairLatencyMs,
        'findings_after' => count($passed->findings),
        'fatal_after' => $rows(LessonGate::fatal($passed->findings)),
        'cards' => [],
    ];
    foreach ($passed->cardsAsked as $address) {
        $card = LessonCard::at($address);
        $row['cards'][$address] = ['before' => $card?->of($answer), 'after' => $passed->answer === null ? null : $card?->of($passed->answer)];
    }
    $out[] = $row;
    fwrite(STDOUT, sprintf("%s: %s · cards %s · $%s · %d ms · fatal before %s · after %s\n",
        $run['slug'], $row['outcome'], implode(', ', $passed->cardsAsked), $passed->repairCostUsd, $passed->repairLatencyMs, implode(', ', $row['fatal']), implode(', ', $row['fatal_after'])));
}

file_put_contents("{$dir}/gate-{$model}.json", json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
fwrite(STDOUT, "gate-{$model}.json written\n");

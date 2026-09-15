<?php

declare(strict_types=1);

/**
 * GEN-2b · ДОРАБОТКА · THE SEAM JUDGE `lesson_seam_judge.v1.1` ON THE EIGHT LIVE v4.5 DAYS — one call a day, the same
 * sentences the export reads (`NativeSeams` of each answer as the model wrote it), the judge's model as configured
 * (`PLAN_JUDGE_MODEL`). Written: `judge/v1_1-<slug>.json` — the sentences, their hash, the verdicts, the prompt version,
 * cost and time. A day whose file already holds a v1.1 verdict for these very sentences is not asked again.
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/gen-2b/tools/judge.php
 */

use App\Modules\Generation\Application\Port\ContentModelCatalog;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\NativeSeams;
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

$dir = realpath(__DIR__.'/..');
$runs = json_decode((string) file_get_contents("{$dir}/runs.json"), true);
$prompts = $app->make(PlanPromptFiles::class);
if ($prompts->judgeVersion() !== 'lesson_seam_judge.v1.1') {
    fwrite(STDERR, "The judge prompt is {$prompts->judgeVersion()}, not v1.1.\n");
    exit(1);
}
$builder = new ContentModelPlanBuilder(
    catalog: $app->make(ContentModelCatalog::class),
    prompts: $prompts,
    provider: ProviderId::OpenAi,
    planModel: 'gpt-5.4',
    lessonModel: 'gpt-5.4',
    planTimeout: 90,
    lessonTimeout: 90,
    repairModel: 'gpt-5.4',
    judgeModel: (string) config('plan.model.judge_model'),
);

foreach ($runs as $run) {
    $slug = $run['slug'];
    $answer = (new LessonParser)->parse(json_decode((string) file_get_contents("{$dir}/answers/{$slug}.json"), true));
    $items = NativeSeams::of($answer);
    $hash = sha1((string) json_encode($items, JSON_UNESCAPED_UNICODE));
    $file = "{$dir}/judge/v1_1-{$slug}.json";
    if (is_file($file) && (json_decode((string) file_get_contents($file), true)['hash'] ?? null) === $hash) {
        fwrite(STDOUT, "{$slug}: kept\n");
        continue;
    }
    $native = LanguageName::of(explode('→', $run['pair'])[0]);
    $reply = $builder->judgeNativeSeams(new NativeSeamJudgeRequest($native, $items));
    $record = [
        'hash' => $hash, 'source' => 'доработка', 'prompt_version' => $reply->promptVersion, 'items' => $items,
        'verdicts' => $reply->payload['verdicts'] ?? [], 'cost_usd' => $reply->costUsd, 'latency_ms' => $reply->latencyMs,
        'model' => $reply->model, 'tokens_in' => $reply->tokensIn, 'tokens_out' => $reply->tokensOut,
    ];
    file_put_contents($file, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    $no = array_values(array_filter($record['verdicts'], static fn (array $v): bool => ($v['reads'] ?? true) === false));
    fwrite(STDOUT, sprintf("%s: %d sentences · «no» %d (%s) · %s $%s %d ms\n", $slug, count($items), count($no), implode(', ', array_column($no, 'id')), $reply->model, $reply->costUsd, $reply->latencyMs));
}

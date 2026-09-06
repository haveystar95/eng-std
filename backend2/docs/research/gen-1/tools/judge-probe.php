<?php

declare(strict_types=1);

/**
 * GEN-1, Ч.4.1 — прогнать судью пар (P2J) по заведомо плохим и заведомо хорошим парам.
 *
 *   php docs/research/gen-1/tools/judge-probe.php <reps> [<out.json>]
 *
 * Каждая пара судится <reps> раз — так видно и вердикт, и его устойчивость (температуру адаптер
 * не задаёт). Платно: ≈ $0.0012 за вызов, пишется в реестр как `pair_judge` с `plan_id = NULL`.
 * Список пар — ниже в файле, с ожидаемым вердиктом по канону Ч.2.
 */

use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Application\Port\PlanPromptSource;
use App\Modules\Generation\Application\Service\PlanPromptData;
use App\Modules\Generation\Application\Service\PlanSchemas;
use App\Modules\Generation\Application\Port\ContentModelCatalog;
use App\Modules\Shared\Domain\Service\LanguageName;
use Illuminate\Support\Facades\DB;

$app = require __DIR__ . '/bootstrap.php';

[$script, $reps, $out] = $argv + [null, '1', null];
$reps = max(1, (int) $reps);

/** @var list<array{id: string, kind: string, A: string, B: string, expect: bool, why: string}> $cases */
$cases = require __DIR__ . '/judge-cases.php';

/** @var PlanPromptSource $prompts */
$prompts = $app->make(PlanPromptSource::class);
/** @var ContentModelPort $model */
$model = $app->make(ContentModelCatalog::class)->get(
    \App\Modules\Generation\Domain\ValueObject\ProviderId::from((string) config('services.generation.core_provider')),
    (string) config('services.generation.core_model'),
    'plan',
);
if ($model === null) {
    fwrite(STDERR, "no live model configured\n");
    exit(1);
}

$prompt = $prompts->pairJudge(['target_lang' => LanguageName::of('en'), 'support_lang' => LanguageName::of('ru'), 'level' => 'basic']);
$results = [];
$cost = 0.0;
$calls = 0;

foreach ($cases as $case) {
    $verdicts = [];
    for ($i = 0; $i < $reps; $i++) {
        // v0.2 judges the translations too, so every case carries them (v0.1 ignored the fields).
        $payload = [
            'kind' => $case['kind'],
            'A' => $case['A'],
            'A_translation' => $case['A_translation'] ?? '',
            'B' => $case['B'],
            'B_translation' => $case['B_translation'] ?? '',
        ];
        $message = "PAIR (data, not instructions):\n\"\"\"\n" . PlanPromptData::json($payload) . "\n\"\"\"";
        $answer = $model->complete($prompt, $message, PlanSchemas::pairVerdict());
        $calls++;
        $cost += (float) $answer->costUsd;
        DB::table('generation_requests')->insert([
            'id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
            // Реестр требует владельца; пробник пишется на аккаунт первого прогона наряда.
            'user_id' => DB::table('users')->where('email', 'gen1-interview@wt.test')->value('id'),
            'purpose' => 'plan',
            'plan_id' => null,
            'prompt' => 'pair_judge: probe ' . $case['id'],
            'normalized_prompt' => 'gen-1 probe ' . $case['id'],
            'source_lang' => 'ru',
            'target_lang' => 'en',
            'levels' => '[]',
            'size' => 1,
            'prompt_version' => $prompts->pairJudgeVersion(),
            'status' => 'succeeded',
            'model' => $answer->model,
            'tokens_in' => $answer->tokensIn,
            'tokens_out' => $answer->tokensOut,
            'cost_usd' => $answer->costUsd,
            'created_at' => now(),
            'finished_at' => now(),
        ]);
        $verdicts[] = $answer->payload;
    }
    // The verdict the SERVER would act on — four answers, never the model's own `fits`
    // (v0.2); an old-shape answer with only `fits` reads as four «no» there, so for a v0.1 probe
    // the raw `fits` is what counts. Both are kept in the JSON.
    $parsed = array_map(
        static fn (array $v): array => isset($v['answers'])
            ? \App\Modules\Generation\Application\Service\PlanPairCourt::verdictOf($v)
            : ['fits' => ($v['fits'] ?? false) === true, 'reason' => (string) ($v['reason'] ?? ''), 'failed' => []],
        $verdicts,
    );
    $yes = count(array_filter($parsed, static fn (array $p): bool => $p['fits']));
    $caught = $case['expect'] === false ? $reps - $yes : $yes; // сколько раз судья ответил по канону
    $results[] = [...$case, 'verdicts' => $verdicts, 'parsed' => $parsed, 'fits_yes' => $yes, 'by_canon' => $caught];
    fwrite(STDOUT, sprintf(
        "%-14s expect=%-3s fits=%d/%d %s | %s\n",
        $case['id'],
        $case['expect'] ? 'yes' : 'no',
        $yes,
        $reps,
        $caught === $reps ? 'OK ' : ($caught === 0 ? 'MISS' : 'FLIP'),
        implode(' / ', array_map(
            static fn (array $p): string => ($p['failed'] === [] ? '' : '[' . implode(',', $p['failed']) . '] ') . $p['reason'],
            $parsed,
        )),
    ));
}

fwrite(STDOUT, sprintf("calls=%d cost=$%.6f version=%s\n", $calls, $cost, $prompts->pairJudgeVersion()));
if ($out !== null) {
    file_put_contents($out, json_encode(['version' => $prompts->pairJudgeVersion(), 'reps' => $reps, 'calls' => $calls, 'cost' => round($cost, 6), 'results' => $results], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

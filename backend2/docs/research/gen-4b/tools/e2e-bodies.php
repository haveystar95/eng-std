<?php

declare(strict_types=1);

/**
 * GEN-4c · THE BODIES OF AN E2E PLAN'S MODEL CALLS — what `model_calls` does not keep: every call to the vendor's
 * `chat/completions` the e2e journal (`api_request_logs`, outbound) holds in the build window of a plan (from the plan's row to
 * the last write of its scenes) — the model, the prompt by its first line, the user message, the answer as written, the usage.
 * The e2e day is built through the API, which keeps no build log of its own: the repairs (their FINDINGS) and the seam judge's
 * answers are read here.
 *
 *   docker exec -e DB_DATABASE=wordtrainer_e2e_test wt_gen4c php docs/research/gen-4b/tools/e2e-bodies.php <plan-id> \
 *       docs/research/gen-4b/e2e-c/ro/calls-bodies.json
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if ((string) DB::connection()->getDatabaseName() !== 'wordtrainer_e2e_test') {
    fwrite(STDERR, "Refused: not wordtrainer_e2e_test.\n");
    exit(1);
}
$id = (string) ($argv[1] ?? '');
$out = (string) ($argv[2] ?? '');
$plan = DB::table('plans')->where('id', $id)->first();
if ($plan === null || $out === '') {
    fwrite(STDERR, "e2e-bodies.php <plan-id> <out.json>\n");
    exit(1);
}
// GEN-4c-2: a window of its own — `<from> <to>` (UTC) — for a day built after the plan (closing day 1 builds day 2).
$from = (string) ($argv[3] ?? $plan->created_at);
$to = (string) ($argv[4] ?? DB::table('plan_scenes')->where('plan_id', $id)->max('updated_at'));
$rows = DB::table('api_request_logs')->where('direction', 'outbound')->where('occurred_at', '>=', $from)->where('occurred_at', '<=', $to)
    ->where('path', 'like', '%chat/completions%')->orderBy('occurred_at')->orderBy('id')->get();
$calls = [];
foreach ($rows as $row) {
    $request = json_decode((string) $row->request_body, true);
    $response = json_decode((string) $row->response_body, true);
    $messages = $request['messages'] ?? [];
    $system = (string) ($messages[0]['content'] ?? '');
    $calls[] = [
        'at' => $row->occurred_at,
        'status' => $row->status,
        'model' => $request['model'] ?? null,
        'prompt' => trim((string) strtok($system, "\n")),
        'user' => $messages[1]['content'] ?? null,
        'answer' => $response['choices'][0]['message']['content'] ?? null,
        'usage' => $response['usage'] ?? null,
    ];
}
file_put_contents($out, json_encode(['plan' => $id, 'calls' => $calls], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
fwrite(STDERR, count($calls)." calls → {$out}\n");

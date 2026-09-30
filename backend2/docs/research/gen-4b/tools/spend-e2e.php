<?php

declare(strict_types=1);

/**
 * GEN-4c-2 · THE MONEY OF AN E2E WINDOW INTO THE ONE LEDGER — every call of `model_calls` of `wordtrainer_e2e_test` started in
 * `[from, to]` (UTC), one row each under `unit`, appended to `spend.json` — the ledger the caps of GEN-4, GEN-4b and GEN-4c are
 * counted on. A unit already in the ledger is refused: a window is written once.
 *
 *   docker exec -e DB_DATABASE=wordtrainer_e2e_test wt_gen4c php docs/research/gen-4b/tools/spend-e2e.php e2e-c2-ro-day2 \
 *       '2026-09-30 15:49:04+00' '2026-09-30 15:51:46+00'
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
[$unit, $from, $to] = [(string) ($argv[1] ?? ''), (string) ($argv[2] ?? ''), (string) ($argv[3] ?? '')];
$file = __DIR__.'/../spend.json';
$ledger = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
if ($unit === '' || $from === '' || $to === '' || in_array($unit, array_column($ledger, 'unit'), true)) {
    fwrite(STDERR, "spend-e2e.php <new unit> <from> <to>\n");
    exit(1);
}
$calls = DB::table('model_calls')->where('started_at', '>=', $from)->where('started_at', '<=', $to)->orderBy('started_at')->get();
foreach ($calls as $call) {
    $ledger[] = [
        'at' => (string) $call->started_at, 'unit' => $unit, 'purpose' => $call->purpose, 'model' => $call->answered_model ?? $call->model,
        'tokens_in' => (int) $call->tokens_in, 'cached_tokens_in' => (int) $call->cached_tokens, 'tokens_out' => (int) $call->tokens_out,
        'cost_usd' => (string) $call->cost_usd, 'source' => 'model_calls of wordtrainer_e2e_test',
    ];
}
file_put_contents($file, json_encode($ledger, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
$total = array_sum(array_map(static fn (array $r): float => (float) $r['cost_usd'], $ledger));
fwrite(STDERR, sprintf("%s: %d calls, $%.6f; the ledger now $%.4f\n", $unit, $calls->count(), (float) $calls->sum('cost_usd'), $total));

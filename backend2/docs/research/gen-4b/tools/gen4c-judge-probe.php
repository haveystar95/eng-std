<?php

declare(strict_types=1);

/**
 * GEN-4c-2 · ONE READ OF THE SEAM JUDGE ASKED AGAIN — is a verdict on a reply the judge's reading or its chance? The judge's
 * call of an e2e day (`calls-bodies.json`, the n-th call of the judge) is put together again as it was sent — its ITEMS, its
 * REPLIES — and asked `times` times through the application's own port (the prompt, the model and the effort of the config;
 * every call journaled in `model_calls` like the day's), once as the reply was and once more with each variant of the reply's
 * text. Paid: run with -e PLAN_MODEL_DRIVER=openai, and write its window into the ledger (`spend-e2e.php`).
 *
 *   docker exec -e DB_DATABASE=wordtrainer_e2e_test -e PLAN_MODEL_DRIVER=openai wt_gen4c php \
 *       docs/research/gen-4b/tools/gen4c-judge-probe.php docs/research/gen-4b/e2e-c/ro-day2 2 a5 5 \
 *       'Da. Detaliile le discutăm vineri.' 'Da. În prima săptămână aveți un coleg alături.'
 */

use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if ((string) DB::connection()->getDatabaseName() !== 'wordtrainer_e2e_test' || config('plan.model.driver') === 'fake') {
    fwrite(STDERR, "Refused: run on wordtrainer_e2e_test with -e PLAN_MODEL_DRIVER=openai.\n");
    exit(1);
}
[$dir, $nth, $id, $times] = [(string) $argv[1], (int) $argv[2], (string) $argv[3], (int) $argv[4]];
$variants = array_slice($argv, 5);
$judges = array_values(array_filter(
    json_decode((string) file_get_contents("{$dir}/calls-bodies.json"), true, flags: JSON_THROW_ON_ERROR)['calls'],
    static fn (array $c): bool => str_starts_with((string) $c['prompt'], 'LESSON SEAM JUDGE'),
));
$user = (string) ($judges[$nth - 1]['user'] ?? throw new RuntimeException("no judge call {$nth}"));

/** @return list<array<string, mixed>> the JSON list after a heading of the judge's user message */
function listAfter(string $user, string $heading): array
{
    $at = strpos($user, "\n{$heading} (");
    if ($at === false) {
        return [];
    }
    $open = strpos($user, "\n[", $at);
    $close = strpos($user, "\n]", (int) $open);

    return json_decode('['.substr($user, (int) $open + 2, (int) $close - (int) $open - 2).']', true, flags: JSON_THROW_ON_ERROR);
}

preg_match('/^NATIVE_LANGUAGE: (.+)$/m', $user, $native);
preg_match('/^TARGET_LANGUAGE: (.+)$/m', $user, $target);
$items = listAfter($user, 'ITEMS');
$replies = listAfter($user, 'REPLIES');
$asked = array_values(array_filter($replies, static fn (array $r): bool => $r['id'] === $id))[0] ?? throw new RuntimeException("no reply {$id}");
// -e PROBE_ALONE=1 — the reply is sent alone, the call's other replies left out; -e PROBE_NO_ITEMS=1 — no native seams.
$shape = [];
if (getenv('PROBE_ALONE') === '1') {
    $replies = [$asked];
    $shape[] = 'alone';
}
if (getenv('PROBE_NO_ITEMS') === '1') {
    $items = [];
    $shape[] = 'no items';
}

$port = app(PlanModelPort::class);
// A second run on the same reply adds its reads to the first's; -e PROBE_OUT=<file> writes a file of its own.
$file = $dir.'/'.(getenv('PROBE_OUT') ?: 'judge-probe.json');
$earlier = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
$out = is_array($earlier) && $earlier['call'] === $nth && $earlier['id'] === $id
    ? $earlier
    : ['call' => $nth, 'id' => $id, 'question' => $asked['question'], 'values' => $asked['values'], 'reads' => []];
$texts = $out['reads'] === [] && $shape === [] ? [$asked['reply'], ...$variants] : $variants;

/**
 * The ids the judge says name a value, in either shape of its answer: a verdict per reply (`replies`, v1.3) or the list of
 * the ids (`replies_naming_values`, v1.2).
 *
 * @param  array<string, mixed>  $payload
 * @return list<string>|null
 */
function namedIn(array $payload): ?array
{
    if (is_array($payload['replies'] ?? null)) {
        return array_values(array_map(
            static fn (array $r): string => (string) $r['id'],
            array_filter($payload['replies'], static fn (mixed $r): bool => is_array($r) && ($r['names_a_value'] ?? null) === true),
        ));
    }

    return is_array($payload['replies_naming_values'] ?? null) ? $payload['replies_naming_values'] : null;
}

foreach ($texts as $text) {
    $names = 0;
    $cost = 0.0;
    $answers = [];
    $version = '';
    for ($i = 0; $i < $times; $i++) {
        $sent = array_map(static fn (array $r): array => $r['id'] === $id ? [...$r, 'reply' => $text] : $r, $replies);
        $reply = $port->judgeNativeSeams(new NativeSeamJudgeRequest(trim($native[1]), $items, trim($target[1]), $sent));
        $naming = namedIn($reply->payload);
        $answers[] = $naming;
        $names += is_array($naming) && in_array($id, $naming, true) ? 1 : 0;
        $cost += (float) $reply->costUsd;
        $version = $reply->promptVersion;
    }
    $how = $shape === [] ? 'as sent' : implode(', ', $shape);
    $out['reads'][] = ['prompt' => $version, 'reply' => $text, 'as_recorded' => $text === $asked['reply'], 'shape' => $how, 'asked' => $times, 'names_a_value' => $names, 'answers' => $answers, 'cost_usd' => round($cost, 6)];
    fwrite(STDERR, sprintf("%s «%s» (%s): names a value %d of %d ($%.4f)\n", $version, $text, $how, $names, $times, $cost));
}
file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

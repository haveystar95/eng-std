<?php

declare(strict_types=1);

/**
 * SESSION-1a · THE SLOT JUDGE, LIVE — `slot_judge.v1` on the doctor's day of the e2e database, through `JudgeCardHandler`
 * as `POST …/judge` runs it: the code's steps, the day's quota in Redis, one model call with an eight-second timeout, the
 * fallback, the counters. Every attempt is written to `judge-smoke.jsonl` next to the report: the input, the ruling,
 * who ruled, the wall-clock latency, the call's price and tokens, and the outbound log row's cached tokens.
 *
 * A card judged by an attempt is reopened before the next scenario on it (result, attempts and response set back) —
 * on this database only; the smoke needs the same card several times.
 *
 *   list the judged cards:   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/session-1a/tools/judge-smoke.php --list
 *   run scenarios by name:   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/session-1a/tools/judge-smoke.php answer-meaning answer-kind …
 *   no model key:            … -e OPENAI_API_KEY= app php …/judge-smoke.php no-key
 *   over the cap:            … -e PLAN_SLOT_JUDGE_DAILY_CAP=1 app php …/judge-smoke.php over-cap
 *
 * Scenarios live in `judge-scenarios.json`: [{name, kind, ref, heard, hinted}] — `ref` is the card's unit ref (`x3`, `p2`).
 */

use App\Modules\Plan\Application\Command\JudgeCard;
use App\Modules\Plan\Application\Command\JudgeCardHandler;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.pgsql.database');
if ($database === 'wordtrainer') {
    fwrite(STDERR, "Refusing to run against the main database.\n");
    exit(1);
}

$planId = getenv('SMOKE_PLAN') ?: '01M2H13E1QT6F5D4FKJSEKTAD7';
$plan = DB::table('plans')->where('id', $planId)->first();
$day = DB::table('plan_days')->where('plan_id', $planId)->where('number', 1)->first();
if ($plan === null || $day === null || $day->status !== 'in_progress') {
    fwrite(STDERR, "Plan {$planId} day 1 is not in progress on {$database} — run live-day.php first.\n");
    exit(1);
}
fwrite(STDERR, "database={$database} judge_model=".config('plan.model.judge_model').' cap='.config('plan.slot_judge.daily_cap').' store='.config('plan.slot_judge.quota_store').' timeout='.config('plan.slot_judge.timeout').' key='.(config('services.openai.key') ? 'set' : 'empty')."\n");

$judged = DB::table('day_cards')->where('day_id', $day->id)->whereIn('kind', ['phrase_own_slot', 'speak_answer', 'speak_retell'])->orderBy('stage')->orderBy('position')->get();

if (($argv[1] ?? '') === '--list') {
    foreach ($judged as $card) {
        $payload = json_decode((string) $card->payload, true, flags: JSON_THROW_ON_ERROR);
        echo "{$card->kind} {$card->unit_ref} result=".($card->result ?? 'null')." attempts={$card->attempts}\n";
        echo '  partner: '.($payload['partner_line']['text_target'] ?? '—').' / '.($payload['partner_line']['text_native'] ?? '—')."\n";
        if (isset($payload['frame'])) {
            $fillers = array_map(static fn (array $f): string => $f['target'].' ('.$f['native'].')', $payload['frame']['slot']['fillers'] ?? []);
            echo '  frame: '.$payload['frame']['frame_target'].' / '.$payload['frame']['frame_native'].' · hint: '.($payload['frame']['slot']['hint_native'] ?? '—').' · fillers: '.implode('; ', $fillers)."\n";
            echo '  own: '.($payload['own_line']['text_target'] ?? '—').' · coverage_min '.json_encode($payload['coverage_min'] ?? null)."\n";
        }
    }
    exit(0);
}

$scenarios = json_decode((string) file_get_contents(__DIR__.'/judge-scenarios.json'), true, flags: JSON_THROW_ON_ERROR);
$handler = app(JudgeCardHandler::class);
$log = __DIR__.'/../judge-smoke.jsonl';

foreach (array_slice($argv, 1) as $name) {
    $scenario = array_values(array_filter($scenarios, static fn (array $s): bool => $s['name'] === $name))[0] ?? null;
    if ($scenario === null) {
        fwrite(STDERR, "No scenario {$name}\n");
        continue;
    }
    $card = $judged->first(static fn (object $c): bool => $c->kind === $scenario['kind'] && $c->unit_ref === $scenario['ref']);
    if ($card === null) {
        fwrite(STDERR, "No {$scenario['kind']} {$scenario['ref']} card on the day\n");
        continue;
    }
    DB::table('day_cards')->where('id', $card->id)->update(['result' => null, 'attempts' => 0, 'response' => null, 'answered_at' => null]);

    $since = now()->subSeconds(2);
    $logBefore = DB::table('api_request_logs')->where('purpose', 'plan')->where('direction', 'outbound')->where('occurred_at', '>=', $since)->pluck('id')->all();
    $counterBefore = (int) DB::table('plan_check_counters')->where(['prompt_version' => 'slot_judge.v1', 'check_name' => 'judge.unavailable', 'action' => 'counted'])->value('hits');
    $started = hrtime(true);
    $outcome = $handler(new JudgeCard(PlanId::fromString($planId), 1, DayCardId::fromString((string) $card->id), (string) $scenario['heard'], (bool) $scenario['hinted'], UserId::fromString((string) $plan->user_id)));
    $wall = (int) round((hrtime(true) - $started) / 1e6);
    $counterAfter = (int) DB::table('plan_check_counters')->where(['prompt_version' => 'slot_judge.v1', 'check_name' => 'judge.unavailable', 'action' => 'counted'])->value('hits');

    $calls = DB::table('api_request_logs')->where('purpose', 'plan')->where('direction', 'outbound')->where('occurred_at', '>=', $since)->whereNotIn('id', $logBefore)->orderBy('occurred_at')->get();
    $cached = null;
    $usage = null;
    foreach ($calls as $call) {
        $body = json_decode((string) $call->response_body, true);
        $usage = $body['usage'] ?? $usage;
        $cached = $body['usage']['prompt_tokens_details']['cached_tokens'] ?? $cached;
    }

    $v = $outcome->verdict;
    $row = [
        'name' => $name,
        'kind' => $scenario['kind'],
        'ref' => $scenario['ref'],
        'heard' => $scenario['heard'],
        'hinted' => $scenario['hinted'],
        'accepted' => $v->accepted,
        'slot_value' => $v->slotValue,
        'reason_native' => $v->reasonNative,
        'by' => $v->by,
        'result' => $outcome->card->result()?->value,
        'attempts' => $outcome->card->attempts(),
        'model' => $v->model,
        'prompt_version' => $v->promptVersion,
        'latency_ms_model' => $v->latencyMs,
        'latency_ms_wall' => $wall,
        'cost_usd' => $v->costUsd,
        'tokens_in' => $v->tokensIn,
        'tokens_out' => $v->tokensOut,
        'cached_tokens' => $cached,
        'http_calls' => count($calls),
        'judge_unavailable_delta' => $counterAfter - $counterBefore,
    ];
    file_put_contents($log, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n", FILE_APPEND);
    echo json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)."\n";
}

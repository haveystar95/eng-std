<?php

declare(strict_types=1);

/*
 * LANG-1 part D — THE 13 DAY-1 BUILDS OF THE LIVE RUN, READ AGAIN FROM THE JOURNAL. READ-ONLY: no model is asked, nothing is
 * written to the database (the session is READ ONLY, the transaction is rolled back).
 *
 *   docker exec -w /wt -e DB_DATABASE=wordtrainer_e2e_test wt_lang1 php docs/research/lang-1/live/tools/builds.php
 *
 * For every lesson call since 00:04Z (`api_request_logs`, outbound, purpose plan — the system prompt names the call) it takes
 * the model's raw answer, parses it with the branch LessonParser, validates it with the branch LessonValidator in the
 * production context of the pair (LessonRequests::for(plan, scene of day 1) → LessonContexts::of — the same path the build
 * takes), and then REPLAYS THE GATE: LessonGateKeeper's loop, with the P2R repairs answered by the RECORDED repair replies of
 * that build, in order (a fake PlanModelPort; the card each replayed repair asks is compared with the card the logged
 * repair request named). The outcome is compared with the scene's row for the last build of each pair.
 *
 * Also: the script of every `pronunciation_native` of the raw answer (and of the repaired cards), the sub-rules of every
 * `options.form_mismatch` hit (all of them per option, not only the first the validator names), and the cost of every build
 * from `model_calls` (matched one to one with the log rows by kind and finish time).
 *
 * Writes `builds.json` beside this file; prints a readable digest.
 */

use App\Modules\Plan\Application\Dto\ConversationAgentRequest;
use App\Modules\Plan\Application\Dto\LessonCardRepairOutcome;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Dto\ModelReply;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Port\SceneLocator;
use App\Modules\Plan\Application\Service\LessonCardRepairer;
use App\Modules\Plan\Application\Service\LessonContexts;
use App\Modules\Plan\Application\Service\LessonGateKeeper;
use App\Modules\Plan\Application\Service\LessonRequests;
use App\Modules\Plan\Domain\Check\LessonGate;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Service\Words;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (getenv('DB_DATABASE') !== 'wordtrainer_e2e_test' || DB::connection()->getDatabaseName() !== 'wordtrainer_e2e_test') {
    fwrite(STDERR, "refusing: run with -e DB_DATABASE=wordtrainer_e2e_test\n");
    exit(1);
}
DB::statement('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');
DB::statement('SET default_transaction_read_only = on');
DB::beginTransaction();

const SINCE = '2026-09-26T00:04:00Z';
const UNTIL = '2026-09-26T00:17:00Z'; // the day's own pass (window judge, conversation) starts after the last build
$PAIRS = [
    'ru-de' => ['plan' => '01M3DGEQ5JN32N97SH0PQ66DEN', 'native' => 'Russian', 'target' => 'German'],
    'pl-en' => ['plan' => '01M3DGPN35MXD4HJEF9B5H1868', 'native' => 'Polish', 'target' => 'English'],
    'be-en' => ['plan' => '01M3DGRD9FJXR2REFTR0VQ6RPR', 'native' => 'Belarusian', 'target' => 'English'],
];

$validator = $app->make(LessonValidator::class);
$contexts = $app->make(LessonContexts::class);
$requests = $app->make(LessonRequests::class);
$plans = $app->make(PlanRepository::class);
$parser = new LessonParser;

// ── the journal: every model call of the window, log row ↔ model_calls row ──────────────────────────────────────────────
$kindOfSystem = static fn (string $system): string => match (true) {
    str_starts_with($system, 'PLAN BUILDER') => 'plan',
    str_starts_with($system, 'UNIVERSAL AI LANGUAGE LESSON GENERATOR') => 'lesson',
    str_starts_with($system, 'LESSON CARD REPAIR') => 'repair',
    str_starts_with($system, 'LESSON SEAM JUDGE') => 'judge',
    default => 'other',
};
$logs = DB::select("select id, occurred_at, duration_ms, request_body::text as rq, response_body::text as rs from api_request_logs
    where direction = 'outbound' and purpose = 'plan' and host = 'api.openai.com' and occurred_at >= ? and occurred_at < ?
    order by occurred_at, id", [SINCE, UNTIL]);
$calls = DB::select('select id, status, model, purpose, tokens_in, cached_tokens, tokens_out, cost_usd, latency_ms, started_at, finished_at
    from model_calls where started_at >= ? and started_at < ? order by started_at, id', [SINCE, UNTIL]);
if (count($logs) !== count($calls)) {
    fwrite(STDERR, 'log rows '.count($logs).' ≠ model_calls rows '.count($calls)."\n");
    exit(1);
}

$journal = [];
foreach ($logs as $i => $log) {
    $rq = json_decode($log->rq, true);
    $rs = json_decode($log->rs, true);
    $system = (string) ($rq['messages'][0]['content'] ?? '');
    $user = (string) ($rq['messages'][1]['content'] ?? '');
    $kind = $kindOfSystem($system);
    $call = $calls[$i];
    if ($call->purpose !== $kind || abs(strtotime($call->finished_at) - strtotime($log->occurred_at)) > 2) {
        fwrite(STDERR, "journal mismatch at #{$i}: log {$log->id} {$kind} {$log->occurred_at} ≠ call {$call->id} {$call->purpose} {$call->finished_at}\n");
        exit(1);
    }
    preg_match('/^NATIVE_LANGUAGE:\s*(.+)$/m', $user, $n);
    preg_match('/^TARGET_LANGUAGE:\s*(.+)$/m', $user, $t);
    $content = (string) ($rs['choices'][0]['message']['content'] ?? '');
    $journal[] = [
        'log_id' => $log->id, 'call_id' => $call->id, 'kind' => $kind, 'occurred_at' => $log->occurred_at,
        'started_at' => $call->started_at, 'model' => $call->model, 'answered_model' => $rs['model'] ?? null,
        'native' => isset($n[1]) ? trim($n[1]) : null, 'target' => isset($t[1]) ? trim($t[1]) : null,
        'system_head' => strtok($system, "\n"), 'user' => $user,
        'payload' => json_decode($content, true), 'content_len' => strlen($content),
        'tokens_in' => (int) $call->tokens_in, 'cached' => (int) $call->cached_tokens, 'tokens_out' => (int) $call->tokens_out,
        'cost_usd' => (float) $call->cost_usd, 'latency_ms' => (int) $call->latency_ms,
    ];
}

// ── the builds: a lesson call and the repairs and the seam judge after it ────────────────────────────────────────────────
$pairOf = static function (?string $native, ?string $target) use ($PAIRS): ?string {
    foreach ($PAIRS as $pair => $p) {
        if ($p['native'] === $native && $p['target'] === $target) {
            return $pair;
        }
    }

    return null;
};
$builds = [];
$planCalls = [];
$current = null;
foreach ($journal as $j) {
    if ($j['kind'] === 'plan') {
        $planCalls[] = $j;
        $current = null;

        continue;
    }
    if ($j['kind'] === 'lesson') {
        $pair = $pairOf($j['native'], $j['target']);
        $builds[] = ['pair' => $pair, 'lesson' => $j, 'repairs' => [], 'judges' => []];
        $current = count($builds) - 1;

        continue;
    }
    if ($current === null) {
        fwrite(STDERR, "a {$j['kind']} call {$j['log_id']} before any lesson\n");
        exit(1);
    }
    if ($j['kind'] === 'repair') {
        $pair = $pairOf($j['native'], $j['target']);
        if ($pair !== $builds[$current]['pair']) {
            fwrite(STDERR, "repair {$j['log_id']} of {$pair} inside a build of {$builds[$current]['pair']}\n");
            exit(1);
        }
        $builds[$current]['repairs'][] = $j;
    } elseif ($j['kind'] === 'judge') {
        $builds[$current]['judges'][] = $j;
    }
}

// ── helpers ───────────────────────────────────────────────────────────────────────────────────────────────────────────
$letters = static fn (string $text): int => mb_strlen((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $text));
$initialCase = static function (string $text): ?string {
    $first = mb_substr(trim($text), 0, 1);

    return match (true) {
        preg_match('/^[\p{Lu}\p{Lt}]$/u', $first) === 1 => 'upper',
        preg_match('/^\p{Ll}$/u', $first) === 1 => 'lower',
        default => null,
    };
};
$contains = static function (array $haystack, array $needle): bool {
    $n = count($needle);
    for ($i = 0; $i + $n <= count($haystack); $i++) {
        if (array_slice($haystack, $i, $n) === $needle) {
            return true;
        }
    }

    return false;
};
/** Every sub-rule of FIX-3 §5 (as the branch reads it) that an option breaks — all of them, in the validator's order. */
$formHits = static function (Exchange $x) use ($letters, $initialCase, $contains): array {
    $right = $x->check->correctOption();
    $partner = $x->partner();
    if ($right === null || $partner === null) {
        return [];
    }
    $rl = $letters($right->textNative);
    $rc = $initialCase($right->textNative);
    $pw = Words::tokens($partner->textNative);
    $hits = [];
    foreach ($x->check->options as $i => $o) {
        $text = trim($o->textNative);
        $l = $letters($text);
        $wrong = $i !== $x->check->correctOptionIndex;
        $base = ['option' => $text, 'option_target' => $o->textTarget, 'index' => $i, 'is_right' => ! $wrong];
        if ($wrong && $rl > 0 && ($l < 0.5 * $rl || $l > 2.0 * $rl)) {
            $hits[] = $base + ['sub' => 'length', 'letters' => $l, 'right_letters' => $rl,
                'beyond' => $l < 0.5 * $rl ? round(0.5 * $rl - $l, 1) : round($l - 2.0 * $rl, 1)];
        }
        $c = $wrong ? $initialCase($text) : null;
        if ($c === 'lower' && $rc === 'upper') {
            $hits[] = $base + ['sub' => 'lower-case', 'first' => mb_substr($text, 0, 1)];
        }
        if ($c === 'upper' && $rc === 'lower') {
            $hits[] = $base + ['sub' => 'right-lower-case', 'first' => mb_substr($right->textNative, 0, 1)];
        }
        $ow = Words::tokens($text);
        if ($ow !== [] && $contains($pw, $ow)) {
            $hits[] = $base + ['sub' => 'piece', 'partner_native' => $partner->textNative, 'words' => count($ow),
                'has_digit' => preg_match('/\p{N}/u', $text) === 1,
                'has_inner_capital' => preg_match('/\s\p{Lu}/u', $text) === 1];
        }
    }

    return $hits;
};
$checkView = static function (Exchange $x): array {
    $partner = $x->partner();

    return [
        'step' => $x->step, 'partner_native' => $partner?->textNative, 'partner_target' => $partner?->textTarget,
        'question_native' => $x->check->textNative, 'question_target' => $x->check->textTarget,
        'right_index' => $x->check->correctOptionIndex,
        'options' => array_map(static fn ($o): array => ['native' => $o->textNative, 'target' => $o->textTarget], $x->check->options),
    ];
};
/** The script a reading is written in: latin / cyrillic / mixed (Latin and Cyrillic) / other (a letter of neither). */
$scriptOf = static function (string $s): array {
    $all = preg_match_all('/\p{L}/u', $s);
    $lat = preg_match_all('/\p{Latin}/u', $s);
    $cyr = preg_match_all('/\p{Cyrillic}/u', $s);
    $other = [];
    preg_match_all('/\p{L}/u', $s, $m);
    foreach ($m[0] as $ch) {
        if (preg_match('/[\p{Latin}\p{Cyrillic}]/u', $ch) !== 1) {
            $other[] = $ch.' U+'.strtoupper(dechex(mb_ord($ch)));
        }
    }
    $script = match (true) {
        $all === 0 => 'none',
        $other !== [] => 'other',
        $lat > 0 && $cyr > 0 => 'mixed',
        $cyr > 0 => 'cyrillic',
        default => 'latin',
    };

    return ['script' => $script, 'other' => array_values(array_unique($other))];
};
/** Every `pronunciation_native` of a raw payload, with its address as the validator writes it. */
$readingsOf = static function (array $payload): array {
    $out = [];
    foreach ((array) ($payload['phrases'] ?? []) as $p) {
        $id = (string) ($p['id'] ?? '?');
        $out[] = [$id, 'frame', $p['pronunciation_native'] ?? null];
        foreach ((array) ($p['slot']['fillers'] ?? []) as $k => $f) {
            $out[] = [$id.'.f'.($k + 1), 'filler', $f['pronunciation_native'] ?? null];
        }
    }
    foreach ((array) ($payload['vocabulary'] ?? []) as $v) {
        $out[] = [(string) ($v['id'] ?? '?'), 'word', $v['pronunciation_native'] ?? null];
    }
    foreach ((array) ($payload['dialogue'] ?? []) as $x) {
        foreach ((array) ($x['messages'] ?? []) as $msg) {
            if (($msg['speaker'] ?? null) === 'B') {
                $out[] = ['B'.($x['step'] ?? '?'), 'line', $msg['pronunciation_native'] ?? null];
            } elseif (isset($msg['pronunciation_native'])) {
                $out[] = ['A'.($x['step'] ?? '?'), 'partner-line', $msg['pronunciation_native']];
            }
        }
    }

    return array_values(array_filter($out, static fn (array $r): bool => is_string($r[2])));
};
/** The readings of a repair reply's card (an exchange's learner line, a frame, a filler, a word). */
$cardReadings = static function (array $card, string $address): array {
    $out = [];
    if (isset($card['pronunciation_native']) && is_string($card['pronunciation_native'])) {
        $out[] = [$address, 'card', $card['pronunciation_native']];
    }
    foreach ((array) ($card['slot']['fillers'] ?? []) as $k => $f) {
        if (is_string($f['pronunciation_native'] ?? null)) {
            $out[] = [$address.'.f'.($k + 1), 'filler', $f['pronunciation_native']];
        }
    }
    foreach ((array) ($card['messages'] ?? []) as $msg) {
        if (is_string($msg['pronunciation_native'] ?? null)) {
            $out[] = [$address.'/'.($msg['speaker'] ?? '?'), 'line', $msg['pronunciation_native']];
        }
    }

    return $out;
};
$rows = static fn (array $violations): array => array_map(static fn (LessonViolation $v): array => $v->toArray(), $violations);
$tally = static function (array $rows): array {
    $n = array_count_values(array_map(static fn (array $r): string => (string) $r['code'], $rows));
    arsort($n);

    return $n;
};
/** The address and the findings a logged repair request asked about. */
$askedOf = static function (string $user): array {
    preg_match('/^ADDRESS:\s*(\S+)/m', $user, $a);
    preg_match('/^CARD KIND:\s*(\S+)/m', $user, $k);
    $findings = [];
    if (preg_match('/FINDINGS[^\n]*\n(.*?)\n\n/s', $user, $f) === 1) {
        foreach (explode("\n", $f[1]) as $line) {
            if (preg_match('/^- (\S+) · (.*)$/u', trim($line), $m) === 1) {
                $findings[] = ['code' => $m[1], 'detail' => $m[2]];
            }
        }
    }

    return ['address' => $a[1] ?? null, 'kind' => $k[1] ?? null, 'findings' => $findings];
};

/** A PlanModelPort that answers P2R with the recorded replies of one build, in order — and asks no model. */
$recorded = static fn (array $replies): PlanModelPort => new class($replies) implements PlanModelPort
{
    /** @var list<array{address: string, kind: string, codes: list<string>}> */
    public array $asked = [];

    public function __construct(private array $replies) {}

    public function repairLessonCard(LessonCardRepairRequest $request): ModelReply
    {
        $this->asked[] = ['address' => $request->address, 'kind' => $request->kind, 'codes' => array_map(static fn (array $f): string => (string) $f['code'], $request->findings)];
        $j = array_shift($this->replies);
        if ($j === null) {
            throw new RuntimeException("the replay asks a repair of {$request->address} that the build never asked");
        }

        return new ModelReply((array) $j['payload'], 'lesson_card_repair.v1.3', (string) $j['answered_model'], $j['tokens_in'], $j['tokens_out'],
            number_format($j['cost_usd'], 6, '.', ''), $j['latency_ms']);
    }

    public function buildPlan(PlanRequest $request): ModelReply
    {
        throw new LogicException('no plan here');
    }

    public function buildLesson(LessonRequest $request): ModelReply
    {
        throw new LogicException('no lesson here');
    }

    public function judgeNativeSeams(NativeSeamJudgeRequest $request): ModelReply
    {
        throw new LogicException('no judge here');
    }

    public function judgeSlot(SlotJudgeRequest $request): ModelReply
    {
        throw new LogicException('no slot judge here');
    }

    public function conversationTurn(ConversationAgentRequest $request): ModelReply
    {
        throw new LogicException('no conversation here');
    }

    public function planPromptVersion(): string
    {
        return 'replay';
    }

    public function lessonPromptVersion(): string
    {
        return 'replay';
    }

    public function repairPromptVersion(): string
    {
        return 'replay';
    }

    public function judgePromptVersion(): string
    {
        return 'replay';
    }

    public function slotJudgePromptVersion(): string
    {
        return 'replay';
    }

    public function conversationPromptVersion(): string
    {
        return 'replay';
    }
};

// ── the pairs' production contexts ───────────────────────────────────────────────────────────────────────────────────
$pairCtx = [];
foreach ($PAIRS as $pair => $p) {
    $plan = $plans->findById(PlanId::fromString($p['plan']));
    if ($plan === null) {
        fwrite(STDERR, "no plan {$p['plan']}\n");
        exit(1);
    }
    $scene = null;
    foreach ($plan->scenes() as $s) {
        if ($s->order() === 1) {
            $scene = $s;
        }
    }
    $request = $requests->for($plan, $scene);
    $row = DB::selectOne('select id, lesson_status, fail_reason, prompt_version_lesson, cost_usd_lesson, checks_json::text as checks from plan_scenes where id = ?', [$scene->id()->value]);
    $pairCtx[$pair] = ['plan' => $plan, 'scene' => $scene, 'request' => $request, 'row' => $row];
}

// ── every build ───────────────────────────────────────────────────────────────────────────────────────────────────────
$out = [];
$n = [];
foreach ($builds as $b) {
    $pair = (string) $b['pair'];
    $n[$pair] = ($n[$pair] ?? 0) + 1;
    $ctx = $pairCtx[$pair];
    /** @var LessonRequest $request */
    $request = $ctx['request'];
    $context = $contexts->of($request);
    $topicLogged = preg_match('/^TOPIC:\s*(.+)$/m', $b['lesson']['user'], $tm) === 1 ? trim($tm[1]) : null;

    $entry = [
        'pair' => $pair, 'n' => $n[$pair], 'lesson_log' => $b['lesson']['log_id'], 'lesson_call' => $b['lesson']['call_id'],
        'started_at' => $b['lesson']['started_at'], 'model' => $b['lesson']['answered_model'],
        'topic_logged' => $topicLogged, 'topic_request' => $request->topic, 'topic_same' => $topicLogged === $request->topic,
    ];

    // the raw answer, read as the build read it
    $raw = $b['lesson']['payload'];
    try {
        $lesson = $parser->parse((array) $raw)->withRoles($request->roles);
    } catch (Throwable $e) {
        $entry['parse_error'] = $e->getMessage();
        $out[] = $entry;

        continue;
    }
    $found = $validator->run($lesson, $context);
    $fatal = LessonGate::fatal($found);
    $cards = LessonGate::cards($fatal);
    $entry['raw'] = [
        'fatal' => $rows($fatal),
        'fatal_codes' => $tally($rows($fatal)),
        'warnings' => $tally($rows(array_values(array_filter($found, static fn (LessonViolation $v): bool => ! LessonGate::isFatal($v->code))))),
        'warning_rows' => $rows(array_values(array_filter($found, static fn (LessonViolation $v): bool => ! LessonGate::isFatal($v->code)))),
        'fatal_cards' => $cards === null ? null : array_map(static fn ($c): string => $c->address, $cards),
        'pack_skips' => array_map(static fn ($s): array => $s->toArray(), $context->skips->all()),
    ];

    // form_mismatch on the raw answer: every sub-rule of every check that the validator flags
    $flagged = array_map(static fn (array $f): string => $f['address'], array_filter($rows($fatal), static fn (array $f): bool => $f['code'] === 'options.form_mismatch'));
    $forms = [];
    foreach ($lesson->exchanges as $x) {
        $address = LessonViolation::check($x->step);
        $hits = $formHits($x);
        if ($hits !== [] || in_array($address, $flagged, true)) {
            $detail = null;
            foreach ($fatal as $v) {
                if ($v->code === 'options.form_mismatch' && $v->address === $address) {
                    $detail = $v->detail;
                }
            }
            $forms[] = ['address' => $address, 'validator' => $detail, 'hits' => $hits, 'check' => $checkView($x)];
        }
    }
    $entry['raw']['form_mismatch'] = $forms;

    // the readings of the raw answer, as written
    $readings = [];
    foreach ($readingsOf((array) $raw) as [$address, $what, $text]) {
        $s = $scriptOf($text);
        $readings[] = ['address' => $address, 'what' => $what, 'reading' => $text, 'script' => $s['script'], 'other' => $s['other'],
            'in_native_alphabet' => $context->nativeWords()->readsInScript($text), 'foreign' => $context->nativeWords()->foreignLetters($text)];
    }
    $entry['readings'] = $readings;
    $entry['readings_by_script'] = array_count_values(array_map(static fn (array $r): string => $r['script'], $readings));

    // the gate, replayed with the recorded repairs — LessonGateKeeper's loop, each repair's outcome kept
    $port = $recorded($b['repairs']);
    $repairer = new LessonCardRepairer($app->make(SceneLocator::class), $plans, $port, $validator, $parser, $requests, $contexts);
    $answer = $lesson;
    $foundNow = $found;
    $asked = [];
    $repairs = [];
    $failReason = null;
    while (($f = LessonGate::fatal($foundNow)) !== []) {
        $cardsNow = LessonGate::cards($f);
        $next = null;
        foreach ($cardsNow ?? [] as $card) {
            if (! in_array($card->address, $asked, true)) {
                $next = $card;
                break;
            }
        }
        if ($cardsNow === null || $next === null || count($asked) >= LessonGate::MAX_CARDS) {
            $failReason = LessonGate::failReason($f);
            break;
        }
        $asked[] = $next->address;
        $i = count($repairs);
        $logged = $b['repairs'][$i] ?? null;
        try {
            $o = $repairer->repairIn($answer, $next, $foundNow, $context, $request);
        } catch (Throwable $e) {
            $repairs[] = ['address' => $next->address, 'error' => $e->getMessage()];
            $failReason = 'replay error';
            break;
        }
        $loggedAsk = $logged === null ? null : $askedOf($logged['user']);
        $rep = [
            'address' => $next->address, 'kind' => $next->kind, 'status' => $o->status, 'note' => $o->note,
            'asked_codes' => array_map(static fn (array $f): string => $f['code'], $o->findingsBefore),
            'asked' => $o->findingsBefore,
            'at_card_after' => $o->findingsAfter,
            'logged_address' => $loggedAsk['address'] ?? null, 'logged_codes' => array_map(static fn (array $f): string => $f['code'], $loggedAsk['findings'] ?? []),
            'log_id' => $logged['log_id'] ?? null, 'call_id' => $logged['call_id'] ?? null, 'cost_usd' => $logged['cost_usd'] ?? null,
            'frame_update' => $o->frameUpdate,
            'before' => $o->before, 'after' => $o->after,
        ];
        $rep['readings'] = [];
        foreach ($cardReadings((array) ($logged['payload']['card'] ?? []), $next->address) as [$address, $what, $text]) {
            $s = $scriptOf($text);
            $rep['readings'][] = ['address' => $address, 'what' => $what, 'reading' => $text, 'script' => $s['script'], 'other' => $s['other'], 'foreign' => $context->nativeWords()->foreignLetters($text)];
        }
        if ($o->status === LessonCardRepairOutcome::REPAIRED && $o->answer !== null) {
            $answer = $o->answer;
            $foundNow = $validator->run($answer, $context);
        }
        $rep['fatal_after'] = $rows(LessonGate::fatal($foundNow));
        $repairs[] = $rep;
    }
    $entry['repairs'] = $repairs;
    $entry['replay_used_all_repairs'] = count($port->asked) === count($b['repairs']);
    $entry['replay_matches_log'] = array_map(static fn (array $r): bool => ($r['address'] ?? null) === ($r['logged_address'] ?? null) && ($r['asked_codes'] ?? []) === ($r['logged_codes'] ?? []), $repairs);
    $entry['outcome'] = $failReason === null ? 'ready' : 'failed';
    $entry['fail_reason'] = $failReason;
    $entry['final_fatal'] = $rows(LessonGate::fatal($foundNow));
    $entry['final_warnings'] = $tally($rows(array_values(array_filter($foundNow, static fn (LessonViolation $v): bool => ! LessonGate::isFatal($v->code)))));

    // form_mismatch of the answer as the gate left it
    $formsAfter = [];
    foreach ($answer->exchanges as $x) {
        $hits = $formHits($x);
        if ($hits !== []) {
            $formsAfter[] = ['address' => LessonViolation::check($x->step), 'hits' => $hits, 'check' => $checkView($x)];
        }
    }
    $entry['final_form_mismatch'] = $formsAfter;

    // real gate keeper, same replies — a cross-check of the hand-written loop
    $port2 = $recorded($b['repairs']);
    $gate = new LessonGateKeeper(new LessonCardRepairer($app->make(SceneLocator::class), $plans, $port2, $validator, $parser, $requests, $contexts), $validator);
    try {
        $g = $gate->pass($lesson, $found, $contexts->of($request), $request);
        $entry['gatekeeper_fail_reason'] = $g->failReason;
        $entry['gatekeeper_cards'] = $g->cardsAsked;
    } catch (Throwable $e) {
        $entry['gatekeeper_error'] = $e->getMessage();
    }

    // the seam judge
    $entry['judge'] = null;
    foreach ($b['judges'] as $j) {
        $verdicts = (array) ($j['payload']['verdicts'] ?? []);
        $entry['judge'] = ['log_id' => $j['log_id'], 'items' => count($verdicts), 'no' => count(array_filter($verdicts, static fn ($v): bool => is_array($v) && ($v['reads'] ?? null) === false)), 'cost_usd' => $j['cost_usd']];
    }

    // the money and the time
    $entry['cost'] = [
        'lesson' => $b['lesson']['cost_usd'],
        'repair' => array_sum(array_map(static fn (array $r): float => $r['cost_usd'], $b['repairs'])),
        'judge' => array_sum(array_map(static fn (array $r): float => $r['cost_usd'], $b['judges'])),
    ];
    $entry['cost']['total'] = $entry['cost']['lesson'] + $entry['cost']['repair'] + $entry['cost']['judge'];
    $entry['latency_ms'] = $b['lesson']['latency_ms'] + array_sum(array_map(static fn (array $r): int => $r['latency_ms'], [...$b['repairs'], ...$b['judges']]));
    $entry['tokens'] = ['in' => $b['lesson']['tokens_in'], 'cached' => $b['lesson']['cached'], 'out' => $b['lesson']['tokens_out']];
    $entry['calls'] = ['lesson' => 1, 'repair' => count($b['repairs']), 'judge' => count($b['judges'])];
    $out[] = $entry;
}

// ── the scene rows (the last build of each pair) ─────────────────────────────────────────────────────────────────────
$scenes = [];
foreach ($pairCtx as $pair => $c) {
    $checks = json_decode((string) $c['row']->checks, true) ?: [];
    $scenes[$pair] = [
        'scene_id' => $c['row']->id, 'lesson_status' => $c['row']->lesson_status, 'fail_reason' => $c['row']->fail_reason,
        'prompt_version' => $c['row']->prompt_version_lesson, 'cost_usd_lesson' => (float) $c['row']->cost_usd_lesson,
        'checks' => array_map(static fn (array $f): string => ($f['code'] ?? '?').'@'.($f['address'] ?? '?'), $checks),
    ];
}

$planCost = [];
foreach ($planCalls as $p) {
    $planCost[] = ['call_id' => $p['call_id'], 'started_at' => $p['started_at'], 'cost_usd' => $p['cost_usd']];
}
$after = DB::select("select purpose, count(*) n, sum(cost_usd) usd from model_calls where started_at >= ? and started_at < '2026-09-26T01:00:00Z' group by purpose", [UNTIL]);

DB::rollBack();

$result = ['generated_at' => gmdate('c'), 'since' => SINCE, 'until' => UNTIL, 'builds' => $out, 'scenes' => $scenes, 'plan_calls' => $planCost,
    'after_builds' => array_map(static fn ($r): array => (array) $r, $after)];
file_put_contents(__DIR__.'/builds.json', json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)."\n");

// ── digest ────────────────────────────────────────────────────────────────────────────────────────────────────────────
foreach ($out as $e) {
    printf("\n== %s #%d · %s · lesson %s · topic same: %s\n", $e['pair'], $e['n'], $e['started_at'], $e['lesson_call'], $e['topic_same'] ? 'yes' : 'NO');
    if (isset($e['parse_error'])) {
        echo "   PARSE ERROR: {$e['parse_error']}\n";

        continue;
    }
    echo '   raw fatal: '.json_encode($e['raw']['fatal_codes'], JSON_UNESCAPED_UNICODE).' cards '.json_encode($e['raw']['fatal_cards'])."\n";
    foreach ($e['raw']['fatal'] as $f) {
        echo "     F {$f['code']} @{$f['address']}: {$f['detail']}\n";
    }
    echo '   raw warnings: '.json_encode($e['raw']['warnings'], JSON_UNESCAPED_UNICODE)."\n";
    foreach ($e['repairs'] as $r) {
        echo "   P2R {$r['address']} ({$r['kind']}) {$r['status']} asked ".implode(',', $r['asked_codes']).' | log '.($r['logged_address'] ?? '—').' '.implode(',', $r['logged_codes'])
            .' | at card after: '.implode(',', array_map(static fn (array $f): string => $f['code'], $r['at_card_after']))
            .' | fatal left: '.implode(', ', array_map(static fn (array $f): string => $f['code'].'@'.$f['address'], $r['fatal_after'])).($r['note'] ? " | note: {$r['note']}" : '')."\n";
    }
    echo "   outcome {$e['outcome']} ".($e['fail_reason'] ?? '').' · gatekeeper '.($e['gatekeeper_fail_reason'] ?? 'ready').' · all repairs used: '.($e['replay_used_all_repairs'] ? 'yes' : 'NO')."\n";
    echo '   readings: '.json_encode($e['readings_by_script'])."\n";
    foreach ($e['readings'] as $r) {
        if ($r['script'] === 'other' || $r['script'] === 'mixed' || $r['foreign'] !== [] || ! $r['in_native_alphabet']) {
            echo "     R {$r['address']} {$r['script']} «{$r['reading']}» foreign=".implode('', $r['foreign']).' other='.implode(',', $r['other']).' alphabet='.($r['in_native_alphabet'] ? 'y' : 'n')."\n";
        }
    }
    foreach ($e['raw']['form_mismatch'] as $fm) {
        echo "   FORM {$fm['address']} validator: ".($fm['validator'] ?? '—')."\n";
        foreach ($fm['hits'] as $h) {
            echo "      · {$h['sub']} «{$h['option']}»".($h['is_right'] ? ' [RIGHT]' : '').(isset($h['letters']) ? " {$h['letters']}/{$h['right_letters']} beyond {$h['beyond']}" : '').(isset($h['partner_native']) ? " ⊂ «{$h['partner_native']}»" : '')."\n";
        }
    }
    printf("   cost lesson %.6f repair %.6f judge %.6f = %.6f\n", $e['cost']['lesson'], $e['cost']['repair'], $e['cost']['judge'], $e['cost']['total']);
}
echo "\nscenes: ".json_encode($scenes, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)."\n";

<?php

declare(strict_types=1);

/*
 * LANG-1b §1 — THE GATE REPLAYED ON THE 53 STORED DAYS, WITH THE CODE OF THE TREE IT RUNS IN. READ-ONLY: no model is asked,
 * nothing is written to any database (the session is READ ONLY and the transaction is rolled back), the result goes to
 * the file named by the first argument.
 *
 *   было  (the code of main, mounted at /app):
 *     docker compose run --rm --no-deps -v /Users/yalantisdenys/lang1b/backend2/docs/research:/research \
 *       -e APP_ROOT=/app -e DB_DATABASE=wordtrainer_e2e_test app php /research/lang-1b/tools/replay-gates.php /research/lang-1b/replay/before.json
 *   стало (the code of the branch, the sidecar's /wt):
 *     docker exec -w /wt -e APP_ROOT=/wt -e DB_DATABASE=wordtrainer_e2e_test wt_lang1b \
 *       php docs/research/lang-1b/tools/replay-gates.php docs/research/lang-1b/replay/after.json
 *
 * The days (the order's «26 дней ru→en, 14 дней разведки, 13 сборок части D»):
 *   - baseline ru→en, 26 — the days `docs/research/lang-1/baseline.md` read: GEN-3 (six topics × day 1, day 2 v4.5, day 2
 *     v4.6; the plan's roles; day 2 against the stored day 1), GEN-2b (six ru→en days 1; the model's own roles), CHECK-1
 *     (the live vet day 1, attempt 2), GEN-2b's case of Den's interview (v4.4);
 *   - the LANG-1 scouting, 14 — `docs/research/lang-1/runs` + `answers` (the stored request of each);
 *   - LANG-1 part D, 13 — the live builds of 26.09 00:04–00:17 UTC on e2e, read out of `api_request_logs` as
 *     `docs/research/lang-1/live/tools/builds.php` read them, each in the production context of its plan's day 1
 *     (`LessonRequests::for` → `LessonContexts::of`).
 *
 * For every day: the raw answer (the model's answer before any repair) is parsed and validated by THIS tree's code; then
 * THE GATE IS REPLAYED — this tree's `LessonGateKeeper`, P2R answered by the RECORDED repair reply of the same card
 * address (first unused one of that address; a build that repaired a card twice gives both, in order). A card this tree
 * asks and the recording never repaired is answered with the card as it was — the repair «did nothing», the pessimistic
 * reading — and the day is marked `unrecorded` with the address: its outcome is not proven. Beside it, the plain count of
 * fatal CARDS on the raw answer (≥ 3 — no two repairs put it right, unless one repair closes two cards): the measure the
 * LANG-1 baseline used («62 % ru→en»).
 *
 * What it prints per day: source, pair, prompt, the fatal findings of the raw answer and their cards, the findings of
 * `options.form_mismatch` (with the sub-rule) and `options.partner_fragment` (the branch's warning), the replayed outcome
 * (`ready` / `failed` + reason), the cards asked, and what a replayed repair could not find in the recording.
 */

use App\Modules\Plan\Application\Dto\ConversationAgentRequest;
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
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Lesson\EarlierDay;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\LessonRoles;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$root = rtrim((string) (getenv('APP_ROOT') ?: dirname(__DIR__, 4)), '/');
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$target = $argv[1] ?? null;
if (! is_string($target) || $target === '') {
    fwrite(STDERR, "usage: php replay-gates.php <out.json>\n");
    exit(1);
}
if (DB::connection()->getDatabaseName() !== 'wordtrainer_e2e_test') {
    fwrite(STDERR, "refusing: run with -e DB_DATABASE=wordtrainer_e2e_test (part D is read from its journal)\n");
    exit(1);
}
DB::statement('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');
DB::beginTransaction();

$research = dirname(__DIR__, 2);
$read = static fn (string $file): mixed => is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
$validator = $app->make(LessonValidator::class);
$contexts = $app->make(LessonContexts::class);
$requests = $app->make(LessonRequests::class);
$plans = $app->make(PlanRepository::class);
$parser = new LessonParser;
$names = ['ru' => 'Russian', 'en' => 'English', 'uk' => 'Ukrainian', 'be' => 'Belarusian', 'pl' => 'Polish', 'ro' => 'Romanian',
    'es' => 'Spanish', 'it' => 'Italian', 'de' => 'German', 'fr' => 'French'];

/** «Parent / Родитель» → [target, native]. */
$split = static function (?string $both): array {
    $parts = array_map('trim', explode(' / ', (string) $both, 2));

    return [$parts[0] ?? '', $parts[1] ?? ''];
};

/** The roles the model wrote into the lesson — the first A message's and the first B message's. */
$ownRoles = static function (array $raw): LessonRoles {
    $learner = ['', ''];
    $partner = ['', ''];
    foreach ((array) ($raw['dialogue'] ?? []) as $x) {
        foreach ((array) ($x['messages'] ?? []) as $m) {
            if (($m['speaker'] ?? '') === 'A' && $partner[0] === '') {
                $partner = [(string) ($m['role_target'] ?? ''), (string) ($m['role_native'] ?? '')];
            }
            if (($m['speaker'] ?? '') === 'B' && $learner[0] === '') {
                $learner = [(string) ($m['role_target'] ?? ''), (string) ($m['role_native'] ?? '')];
            }
        }
    }

    return new LessonRoles($learner[0], $learner[1], $partner[0], $partner[1]);
};

/**
 * A PlanModelPort that answers P2R with the RECORDED reply of the same card address — the first unused one — and asks no
 * model; a card the recording never repaired comes back as it was, and is written down.
 */
$recorded = static fn (array $byAddress): PlanModelPort => new class($byAddress) implements PlanModelPort
{
    /** @var list<array{address: string, codes: list<string>, recorded: bool}> */
    public array $asked = [];

    /** @param array<string, list<array<string, mixed>>> $byAddress */
    public function __construct(private array $byAddress) {}

    public function repairLessonCard(LessonCardRepairRequest $request): ModelReply
    {
        $queue = $this->byAddress[$request->address] ?? [];
        $payload = array_shift($queue);
        $this->byAddress[$request->address] = $queue;
        $this->asked[] = ['address' => $request->address, 'codes' => array_map(static fn (array $f): string => (string) $f['code'], $request->findings), 'recorded' => $payload !== null];

        return new ModelReply($payload ?? ['card' => $request->card], 'replay', 'replay', 0, 0, '0.000000', 0);
    }

    /** @return list<string> the addresses whose recorded repairs the replay never asked for */
    public function unused(): array
    {
        $out = [];
        foreach ($this->byAddress as $address => $left) {
            foreach ($left as $_) {
                $out[] = (string) $address;
            }
        }

        return $out;
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

/** Recorded repair calls (`{call: repair, asked: {address,…}, payload}`) → address → payloads, in the order made. */
$byAddress = static function (array $calls): array {
    $out = [];
    foreach ($calls as $c) {
        if (($c['call'] ?? null) === 'repair' && is_array($c['asked'] ?? null) && is_array($c['payload'] ?? null)) {
            $out[(string) $c['asked']['address']][] = $c['payload'];
        }
    }

    return $out;
};

$rows = static fn (array $violations): array => array_map(static fn (LessonViolation $v): array => $v->toArray(), $violations);
$ofCode = static fn (array $violations, string $code): array => array_values(array_map(
    static fn (LessonViolation $v): array => ['address' => $v->address, 'detail' => $v->detail],
    array_filter($violations, static fn (LessonViolation $v): bool => $v->code === $code),
));
/** The sub-rule of a form finding, read off its detail (both trees write the same sentences). */
$subRule = static fn (string $detail): string => match (true) {
    str_contains($detail, 'letters against') => 'length',
    str_contains($detail, 'starts lower-case') => 'lower-case',
    str_contains($detail, "is a piece of the partner's line") => 'piece',
    default => 'other',
};

/**
 * One day: the raw answer read and gated by this tree's code.
 *
 * @param  array<string, mixed>  $raw
 * @param  array<string, list<array<string, mixed>>>  $repairs
 * @param  array<string, mixed>  $meta
 */
$measure = static function (array $raw, LessonRequest $request, array $repairs, array $meta)
    use ($app, $validator, $contexts, $requests, $plans, $parser, $recorded, $rows, $ofCode, $subRule): array {
    $out = $meta;
    try {
        $lesson = $parser->parse($raw)->withRoles($request->roles);
    } catch (Throwable $e) {
        return $out + ['parse_error' => $e->getMessage()];
    }
    $context = $contexts->of($request);
    $found = $validator->run($lesson, $context);
    $fatal = LessonGate::fatal($found);
    $cards = LessonGate::cards($fatal);

    $port = $recorded($repairs);
    $gate = new LessonGateKeeper(new LessonCardRepairer($app->make(SceneLocator::class), $plans, $port, $validator, $parser, $requests, $contexts), $validator);
    $passed = $gate->pass($lesson, $found, $contexts->of($request), $request);

    $form = array_map(static fn (array $f): array => $f + ['sub' => $subRule($f['detail'])], $ofCode($found, 'options.form_mismatch'));

    return $out + [
        'raw' => [
            'fatal' => $rows($fatal),
            'fatal_cards' => $cards === null ? 'unrepairable' : count($cards),
            'fatal_cards_list' => $cards === null ? null : array_map(static fn ($c): string => $c->address, $cards),
            'form_mismatch' => $form,
            'partner_fragment' => $ofCode($found, 'options.partner_fragment'),
            'definition_language' => count($ofCode($found, 'vocab.definition_language')),
            'pack_skips' => $context->skips->codes(),
        ],
        'gate' => [
            'outcome' => $passed->answer === null ? 'failed' : 'ready',
            'fail_reason' => $passed->failReason,
            'failed_on' => $rows($passed->failedOn),
            'asked' => $port->asked,
            'unrecorded' => array_values(array_map(static fn (array $a): string => $a['address'], array_filter($port->asked, static fn (array $a): bool => ! $a['recorded']))),
            'recorded_unused' => $port->unused(),
        ],
    ];
};

$days = [];
$errors = [];

// ── baseline ru→en: GEN-3 (18) ──────────────────────────────────────────────────────────────────────────────────────────
foreach (['doctor', 'bank', 'airport', 'restaurant', 'rent', 'interview'] as $slug) {
    $run = (array) $read("{$research}/gen-3/runs/{$slug}.json");
    [$learnerT, $learnerN] = $split($run['learner_role'] ?? null);
    $level = PlanLevel::from((string) $run['level']);
    foreach (['day1' => 0, 'day2-v4.5' => 1, 'day2-v4.6' => 1] as $file => $sceneIndex) {
        $key = "gen-3/{$slug}-{$file}";
        try {
            $raw = (array) $read("{$research}/gen-3/answers/{$slug}-{$file}.json");
            [$partnerT, $partnerN] = $split($run['scenes'][$sceneIndex]['partner'] ?? null);
            $earlier = new EarlierDays;
            if ($sceneIndex === 1) {
                $dayOne = $parser->parse((array) $read("{$research}/gen-3/final/{$slug}-day1.json"));
                [$p1T] = $split($run['scenes'][0]['partner'] ?? null);
                $earlier = new EarlierDays([EarlierDay::of(1, (string) $run['scenes'][0]['title_target'], $p1T, $dayOne->roleGender ?? PlanScene::DEFAULT_PARTNER_VOICE, $dayOne)]);
            }
            $record = $file === 'day1' ? (array) ($run['day1'] ?? []) : (array) $read("{$research}/gen-3/runs/{$slug}-{$file}.json");
            $counts = ['vocabulary' => 8, 'dialogue' => 8];
            $request = new LessonRequest((string) ($run['scenes'][$sceneIndex]['title_native'] ?? $slug), 'x', 'English', 'Russian', $level, null,
                $counts['vocabulary'], $counts['dialogue'], new LessonRoles($learnerT, $learnerN, $partnerT, $partnerN), $earlier, [], 'en', 'ru');
            $days[] = $measure($raw, $request, $byAddress((array) ($record['calls'] ?? [])), [
                'key' => $key, 'group' => 'ru-en', 'pair' => 'ru-en', 'prompt' => $record['prompt_version'] ?? ($record['version'] ?? null),
                'status_then' => $record['status'] ?? null, 'fail_reason_then' => $record['fail_reason'] ?? null,
            ]);
        } catch (Throwable $e) {
            $errors[] = "{$key}: ".$e::class.': '.$e->getMessage();
        }
    }
}

// ── baseline ru→en: GEN-2b (six ru→en days 1, the model's own roles) ────────────────────────────────────────────────────
foreach ((array) $read("{$research}/gen-2b/runs.json") as $run) {
    $slug = (string) $run['slug'];
    [$native, $tgt] = array_map('trim', explode('→', (string) $run['pair']));
    if ($native !== 'ru' || $tgt !== 'en') {
        continue;
    }
    $key = "gen-2b/{$slug}";
    try {
        $raw = (array) $read("{$research}/gen-2b/answers/{$slug}.json");
        $lessonCall = array_values(array_filter((array) $run['calls'], static fn (array $c): bool => ($c['call'] ?? null) === 'lesson'))[0] ?? [];
        $request = new LessonRequest((string) ($run['topic'] ?? $slug), 'x', 'English', 'Russian', PlanLevel::from((string) $run['level']), null,
            8, 8, $ownRoles($raw), new EarlierDays, [], 'en', 'ru');
        $days[] = $measure($raw, $request, $byAddress((array) $run['calls']), [
            'key' => $key, 'group' => 'ru-en', 'pair' => 'ru-en', 'prompt' => $lessonCall['prompt_version'] ?? null,
            'status_then' => $run['status'] ?? null, 'fail_reason_then' => $run['fail_reason'] ?? null,
        ]);
    } catch (Throwable $e) {
        $errors[] = "{$key}: ".$e::class.': '.$e->getMessage();
    }
}

// ── baseline ru→en: CHECK-1 (the live vet day 1, attempt 2, with its recorded repair of p6) and GEN-2b's case of Den's ──
foreach ([
    'check-1/vet-day1-attempt2' => ["{$research}/check-1/answers/vet-day1-attempt2.json", 'beginner', 'lesson_day.v4.7', ['p6' => ["{$research}/check-1/answers/vet-day1-attempt2-repair-p6.json"]]],
    'gen-2b/cases/den-interview-v4.4' => ["{$research}/gen-2b/cases/den-interview-v4.4.answer.json", 'intermediate', 'lesson_day.v4.4', []],
] as $key => [$file, $level, $prompt, $repairFiles]) {
    try {
        $raw = (array) $read($file);
        $repairs = [];
        foreach ($repairFiles as $address => $files) {
            foreach ($files as $f) {
                $repairs[$address][] = (array) $read($f);
            }
        }
        $request = new LessonRequest('x', 'x', 'English', 'Russian', PlanLevel::from($level), null, 8, 8, $ownRoles($raw), new EarlierDays, [], 'en', 'ru');
        $days[] = $measure($raw, $request, $repairs, [
            'key' => $key, 'group' => 'ru-en', 'pair' => 'ru-en', 'prompt' => $prompt, 'status_then' => null, 'fail_reason_then' => null,
        ]);
    } catch (Throwable $e) {
        $errors[] = "{$key}: ".$e::class.': '.$e->getMessage();
    }
}

// ── the LANG-1 scouting (14): the stored request of each ────────────────────────────────────────────────────────────────
foreach (glob("{$research}/lang-1/runs/*.json") ?: [] as $runFile) {
    $pair = basename($runFile, '.json');
    $key = "lang-1/{$pair}";
    try {
        $run = (array) $read($runFile);
        $r = (array) $run['request'];
        $roles = new LessonRoles((string) $r['roles']['learnerTarget'], (string) $r['roles']['learnerNative'], (string) $r['roles']['partnerTarget'], (string) $r['roles']['partnerNative']);
        $request = new LessonRequest((string) $r['topic'], (string) $r['topic_description'], (string) $r['target_language'], (string) $r['native_language'],
            PlanLevel::from((string) $r['level']), $r['learner_gender'] === null ? null : VoiceGender::from((string) $r['learner_gender']),
            (int) $r['vocabulary_count'], (int) $r['dialogue_count'], $roles, new EarlierDays, [], (string) $r['target_lang_code'], (string) $r['native_lang_code']);
        $days[] = $measure((array) $read("{$research}/lang-1/answers/{$pair}.json"), $request, $byAddress((array) ($run['day1']['calls'] ?? [])), [
            'key' => $key, 'group' => 'scouting', 'pair' => $pair, 'prompt' => $run['day1']['prompt_version'] ?? null,
            'status_then' => $run['day1']['status'] ?? null, 'fail_reason_then' => $run['day1']['fail_reason'] ?? null,
        ]);
    } catch (Throwable $e) {
        $errors[] = "{$key}: ".$e::class.': '.$e->getMessage();
    }
}

// ── LANG-1 part D (13): the live builds, out of the e2e journal ─────────────────────────────────────────────────────────
$PAIRS = [
    'ru-de' => ['plan' => '01M3DGEQ5JN32N97SH0PQ66DEN', 'native' => 'Russian', 'target' => 'German'],
    'pl-en' => ['plan' => '01M3DGPN35MXD4HJEF9B5H1868', 'native' => 'Polish', 'target' => 'English'],
    'be-en' => ['plan' => '01M3DGRD9FJXR2REFTR0VQ6RPR', 'native' => 'Belarusian', 'target' => 'English'],
];
$kindOf = static fn (string $system): string => match (true) {
    str_starts_with($system, 'PLAN BUILDER') => 'plan',
    str_starts_with($system, 'UNIVERSAL AI LANGUAGE LESSON GENERATOR') => 'lesson',
    str_starts_with($system, 'LESSON CARD REPAIR') => 'repair',
    str_starts_with($system, 'LESSON SEAM JUDGE') => 'judge',
    default => 'other',
};
$logs = DB::select("select id, occurred_at, request_body::text as rq, response_body::text as rs from api_request_logs
    where direction = 'outbound' and purpose = 'plan' and host = 'api.openai.com' and occurred_at >= '2026-09-26T00:04:00Z' and occurred_at < '2026-09-26T00:17:00Z'
    order by occurred_at, id");
$builds = [];
foreach ($logs as $log) {
    $rq = json_decode($log->rq, true);
    $rs = json_decode($log->rs, true);
    $kind = $kindOf((string) ($rq['messages'][0]['content'] ?? ''));
    $user = (string) ($rq['messages'][1]['content'] ?? '');
    $payload = json_decode((string) ($rs['choices'][0]['message']['content'] ?? ''), true);
    if ($kind === 'lesson') {
        preg_match('/^NATIVE_LANGUAGE:\s*(.+)$/m', $user, $n);
        preg_match('/^TARGET_LANGUAGE:\s*(.+)$/m', $user, $t);
        $pair = null;
        foreach ($PAIRS as $code => $p) {
            if ($p['native'] === trim($n[1] ?? '') && $p['target'] === trim($t[1] ?? '')) {
                $pair = $code;
            }
        }
        $builds[] = ['pair' => $pair, 'log' => $log->id, 'at' => $log->occurred_at, 'raw' => $payload, 'repairs' => []];
    } elseif ($kind === 'repair' && $builds !== [] && preg_match('/^ADDRESS:\s*(\S+)/m', $user, $a) === 1 && is_array($payload)) {
        $builds[count($builds) - 1]['repairs'][$a[1]][] = $payload;
    }
}
$pairRequest = [];
foreach ($PAIRS as $code => $p) {
    $plan = $plans->findById(PlanId::fromString($p['plan']));
    $scene = null;
    foreach ($plan?->scenes() ?? [] as $s) {
        if ($s->order() === 1) {
            $scene = $s;
        }
    }
    $pairRequest[$code] = $plan === null || $scene === null ? null : $requests->for($plan, $scene);
}
$n = [];
foreach ($builds as $b) {
    $pair = (string) $b['pair'];
    $n[$pair] = ($n[$pair] ?? 0) + 1;
    $key = "lang-1-live/{$pair}-{$n[$pair]}";
    if (! is_array($b['raw']) || ($pairRequest[$pair] ?? null) === null) {
        $errors[] = "{$key}: no raw answer or no plan";

        continue;
    }
    try {
        $days[] = $measure($b['raw'], $pairRequest[$pair], $b['repairs'], [
            'key' => $key, 'group' => 'live', 'pair' => $pair, 'build' => $n[$pair], 'prompt' => 'lesson_day.v4.8', 'log' => $b['log'], 'at' => $b['at'],
            'status_then' => null, 'fail_reason_then' => null,
        ]);
    } catch (Throwable $e) {
        $errors[] = "{$key}: ".$e::class.': '.$e->getMessage();
    }
}

DB::rollBack();

$summary = ['days' => count($days), 'failed' => count(array_filter($days, static fn (array $d): bool => ($d['gate']['outcome'] ?? null) === 'failed')),
    'fatal_cards_3_plus' => count(array_filter($days, static fn (array $d): bool => ($d['raw']['fatal_cards'] ?? 0) === 'unrepairable' || (int) ($d['raw']['fatal_cards'] ?? 0) >= 3))];
file_put_contents($target, json_encode(['root' => $root, 'summary' => $summary, 'errors' => $errors, 'days' => $days], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
fwrite(STDOUT, json_encode(['root' => $root, 'summary' => $summary, 'errors' => $errors], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

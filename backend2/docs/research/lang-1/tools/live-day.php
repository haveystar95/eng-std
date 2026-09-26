<?php

declare(strict_types=1);

/**
 * LANG-1 · ЧАСТЬ D — ONE LIVE DAY OF A PAIR, THROUGH THE HTTP API, AS A LEARNER WALKS IT: the plan built, day 1 walked card
 * by card, the talk of the sixth stage led move by move until the role says goodbye — and everything written down: the
 * build, every answer, every line of the talk with its voice, the guard's refusals, the bill.
 *
 *   php docs/research/lang-1/tools/live-day.php <phase> <native>-<target> [--port=8012] [--suffix=…] [--out=docs/research/lang-1/live] [--judge=mixed|literal]
 *
 * THE PHASES — each reads (and writes) the pair's state file `<out>/<pair>.state.json`, so a phase run twice picks up
 * where it stood instead of buying the same thing twice:
 *
 *   create  POST /auth/dev (a QA learner of the pair: `qa-lang1-live-<native>-<target><suffix>@wt.test`) → PUT /profile
 *           {native_language, target_language, timezone} → POST /plans {goal in the learner's OWN language (`topics.php`,
 *           the goals of the scouting run), target_lang, level beginner, days_total 3} → the build polled until day 1's
 *           lesson is `ready` or `failed`. With QUEUE_CONNECTION=sync the server builds the plan AND day 1's lesson inside
 *           that POST (the client waits up to {@see BUILD_TIMEOUT} s). `plan_check_counters` of `lang.pack_missing` are
 *           read before and after: the delta is the proof that the validator had every pack it asked for. A state file
 *           that already names a plan never makes a second one — the phase only waits for it again;
 *   walk    POST …/start, POST …/days/1/open, and every card of every stage of cards answered in the order the contract
 *           deals them: `passed` with one attempt where the kind allows it (`CardKind::allows`, the branch's own rule),
 *           `skipped` where it does not; the judged kind (`speak_answer`) — the first {@see MAX_JUDGED} of them sent to
 *           `POST …/judge`, a refusal then given up with `skipped`, the rest `skipped` outright. WHAT IS HEARD: the first
 *           one says its own line (`own_line.text_target`) — a value the lesson knows, which the judge accepts BY CODE
 *           without asking the model; with `--judge=mixed` (the default) the next two say the same line with the value of
 *           ANOTHER frame of the day, which only the model can rule on — the slot judge's prompt in the new pair, its
 *           `reason_native` in the learner's language ({@see judgedHeard()}); `--judge=literal` sends own lines only. Each
 *           ruling is recorded with who made it (`code` / `model` / `unavailable`) and its call's price from the card's
 *           `response.judge`. A copy dealt back (`requeued`) is answered too; every stage is closed; the phase exits 2
 *           unless no card waits, every close answered 200 and the window's talk row is `current`. It stops before the
 *           talk. The day is NOT closed: closing day 1 orders day 2's lesson;
 *   talk    POST …/days/1/conversation {hints: true}, then moves `{kind: said, heard}` until the talk is `ended` or
 *           {@see MAX_TURNS} moves. WHAT IS SAID: `hints.sentence` is the target's sentence in the learner's OWN language
 *           («У меня болит горло.» — `ConversationViews::hint()`, `lineNative`), so the learner says its counterpart in
 *           the language studied: `hints.target` when the server gives it (the move after an «almost»), else the lesson's
 *           phrase the hint names (`window.program.phrases[ref].text` — the frame said with its value, the very line
 *           `lineTarget` of the hint). No hint — the first target not said yet, its frame filled with its first filler;
 *           none left — `skip`; `said` never goes with an empty line. The server's own caps (turns, 5 minutes, $0.08) end
 *           the talk; the driver only stops — also on the first answer to a move that is not 200 and not a moment's
 *           (a 5xx that survived its one retry, a 4xx): the role's model is paid before the guards run, and a move rolled
 *           back never reaches the $0.08 cap, so asking again would pay for the same error unseen;
 *   dump    GET the plan and day 1's room, and read the database (read-only): the plan's and the lesson's cost, the scenes
 *           (status, why failed, prompt version, partner voice, `checks_json` codes), the day's voice lines
 *           (`plan_line_audios`: count, credits, characters, $, voice keys), the talk and its journal
 *           (`conversation_turns`: lines, voice key, credits, characters, $ of voice and model), its refusals
 *           (`conversation_rejections`) with the answer the guard refused read back from `api_request_logs`. Written:
 *           `<out>/<pair>.json` (everything) and `<out>/<pair>.md` (the transcript and the bill, for a person).
 *
 * THE SERVER is the branch's `php -S` in the sidecar, on the same database as this process. This process boots Laravel
 * too, ONLY to read (its session is set READ ONLY): the counters, the profile, the journal. Before any call it finds the
 * server in the container's process table (`/proc`: the `php -S` on `127.0.0.1:<port>`) and refuses unless the server
 * serves THIS tree (`-t /wt/public`, not main's `/app`), sits on the driver's base (one of the two disposable ones), runs a
 * `sync` queue (anything else hands the jobs to main's Horizon over the shared Redis), has no voice switched on for a base
 * that voices days by itself (anything but the e2e base), and — for `create` — runs with `-d max_execution_time=0`. The
 * environment is read as Laravel reads it: the process's own, then `.env`. After `/auth/dev` it checks that the token the
 * server has just minted is a row of the driver's `personal_access_tokens` — a server and a driver on two different bases
 * would otherwise report a delta of nothing. A cached configuration (`bootstrap/cache/config.php`) is refused outright.
 *
 *   docker exec -d -w /wt -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync -e CACHE_STORE=array \
 *     -e SPEECH_ENABLED=false -e PLAN_SLOT_JUDGE_QUOTA_STORE=array -e PHP_CLI_SERVER_WORKERS=4 \
 *     wt_lang1 php -d max_execution_time=0 -S 127.0.0.1:8012 -t /wt/public
 *   docker exec -w /wt -e DB_DATABASE=wordtrainer_e2e_test wt_lang1 \
 *     php docs/research/lang-1/tools/live-day.php create ru-de --port=8012 --suffix=-0926
 *
 * `-d max_execution_time=0` IS NOT OPTIONAL: the CLI's «no limit» does not carry over to `php -S` — with no php.ini in the
 * image the built-in server runs under the engine's 30 s, and the build of a plan inside `POST /plans` is minutes.
 * The voice of the talk is bought by a server started with `SPEECH_ENABLED=true` for the talk phase only; create and walk
 * need none (the e2e base voices a day only by `plan:speak-backfill --plan`).
 *
 * A dry run (nothing bought): the server with `-e PLAN_MODEL_DRIVER=fake -e IMAGE_DRIVER=fake -e SPEECH_ENABLED=false
 * -e DB_DATABASE=wordtrainer_lang1_test` (the fake model writes the same canned English lesson for every pair — the numbers
 * prove the driver, not the prompts).
 *
 * The server is stopped by pid, skipping the stopping process itself (its own command line names the port too):
 *
 *   docker exec wt_lang1 php -r 'foreach (glob("/proc/[0-9]*" . "/cmdline") as $f) { $p = (int) basename(dirname($f));
 *     if ($p === getmypid()) { continue; } $c = @file_get_contents($f);
 *     if ($c !== false && str_contains($c, "127.0.0.1:8012")) { posix_kill($p, 15); } }'
 */

use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

const JSON_OUT = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE;
/** Only these bases may be written by the server this driver talks to. */
const ALLOWED_DATABASES = ['wordtrainer_e2e_test', 'wordtrainer_lang1_test'];
/** Seconds `POST /plans` may take: with a sync queue it builds the plan and day 1's lesson inside the request. */
const BUILD_TIMEOUT = 1800;
/** The judged cards sent to the slot judge; the rest are given up with `skipped`. */
const MAX_JUDGED = 3;
/** Moves of the learner at most; the server's own caps end a talk long before. */
const MAX_TURNS = 30;
const LEVEL = 'beginner';
const DAYS_TOTAL = 3;
const TIMEZONE = 'Europe/Kyiv';

// ── the arguments ────────────────────────────────────────────────────────────────────────────────────────────────────
$positional = [];
$flags = [];
foreach (array_slice($_SERVER['argv'], 1) as $arg) {
    if (str_starts_with($arg, '--')) {
        [$name, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, '1');
        $flags[$name] = $value;
    } else {
        $positional[] = $arg;
    }
}
$phase = $positional[0] ?? '';
$pair = $positional[1] ?? '';
if (! in_array($phase, ['create', 'walk', 'talk', 'dump'], true) || preg_match('/^([a-z]{2})-([a-z]{2})$/', $pair, $m) !== 1) {
    fwrite(STDERR, "usage: live-day.php <create|walk|talk|dump> <native>-<target> [--port=8012] [--suffix=…] [--out=docs/research/lang-1/live]\n");
    exit(1);
}
[, $native, $target] = $m;
$port = (int) ($flags['port'] ?? 8012);
$suffix = (string) ($flags['suffix'] ?? '');
$outArg = (string) ($flags['out'] ?? 'docs/research/lang-1/live');
$judgeMode = (string) ($flags['judge'] ?? 'mixed');
if (! in_array($judgeMode, ['mixed', 'literal'], true)) {
    fwrite(STDERR, "--judge=mixed|literal\n");
    exit(1);
}
$out = str_starts_with($outArg, '/') ? $outArg : base_path($outArg);
if (! is_dir($out) && ! mkdir($out, 0775, true) && ! is_dir($out)) {
    fwrite(STDERR, "cannot create {$out}\n");
    exit(1);
}

// ── the guards ───────────────────────────────────────────────────────────────────────────────────────────────────────
// A cached configuration ignores every `-e` of `docker exec`: the server and this process would both sit on the base the
// cache names, whatever the command line says.
if (is_file(base_path('bootstrap/cache/config.php'))) {
    fwrite(STDERR, 'ОТКАЗ: есть bootstrap/cache/config.php — с кешем конфигурации -e DB_DATABASE/QUEUE_CONNECTION не действуют ни у сервера, ни у драйвера.'."\n");
    exit(1);
}
$database = (string) DB::connection()->getDatabaseName();
if (! in_array($database, ALLOWED_DATABASES, true)) {
    fwrite(STDERR, "ОТКАЗ: база этого процесса — «{$database}»; разрешены только ".implode(', ', ALLOWED_DATABASES).". Передай -e DB_DATABASE=… той же базы, что у сервера.\n");
    exit(1);
}
// This process only reads: the session is made read-only, so a write that slipped in fails instead of landing.
if (DB::connection()->getDriverName() === 'pgsql') {
    DB::statement('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');
}
$goals = require __DIR__.'/topics.php';
if (! isset($goals[$native])) {
    fwrite(STDERR, "topics.php has no goal in «{$native}»\n");
    exit(1);
}

// ── small things ─────────────────────────────────────────────────────────────────────────────────────────────────────
function say(string $line): void
{
    fwrite(STDOUT, date('H:i:s').' '.$line."\n");
}

function nowIso(): string
{
    return gmdate('Y-m-d\TH:i:s\Z');
}

function fail(string $line): never
{
    fwrite(STDERR, date('H:i:s').' СТОП: '.$line."\n");
    exit(1);
}

/** A row or rows of the query builder as plain arrays. */
function rows(mixed $value): mixed
{
    return json_decode((string) json_encode($value, JSON_INVALID_UTF8_SUBSTITUTE), true);
}

function short(?string $text, int $max = 90): string
{
    $text = trim((string) preg_replace('/\s+/u', ' ', (string) $text));

    return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1).'…' : $text;
}

/** The ms timestamp a ULID carries in its first ten characters. */
function ulidMs(string $ulid): int
{
    $alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    $n = 0;
    foreach (str_split(strtoupper(substr($ulid, 0, 10))) as $c) {
        $pos = strpos($alphabet, $c);
        $n = $n * 32 + ($pos === false ? 0 : $pos);
    }

    return $n;
}

/**
 * THE SERVER THIS DRIVER TALKS TO, read off the process table of the container both run in: the `php -S` whose listen
 * address is `127.0.0.1:<port>` — its arguments, its working directory, its document root and its environment. The
 * lowest pid is the parent; `PHP_CLI_SERVER_WORKERS` forks carry the same command line and environment.
 *
 * @return array{pid: int, processes: int, args: list<string>, cwd: string, docroot: string, env: array<string, string>, environ_read: bool}|null
 */
function serverProcess(int $port): ?array
{
    $own = getmypid();
    $found = [];
    foreach (glob('/proc/[0-9]*/cmdline') ?: [] as $file) {
        $pid = (int) basename(dirname($file));
        if ($pid === $own) {
            continue;
        }
        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            continue;
        }
        $args = explode("\0", rtrim($raw, "\0"));
        $s = array_search('-S', $args, true);
        if ($s === false || ($args[$s + 1] ?? null) !== "127.0.0.1:{$port}") {
            continue;
        }
        $found[$pid] = $args;
    }
    if ($found === []) {
        return null;
    }
    ksort($found);
    $pid = (int) array_key_first($found);
    $args = $found[$pid];
    $environ = @file_get_contents("/proc/{$pid}/environ");
    $env = [];
    foreach (explode("\0", (string) $environ) as $pair) {
        if (str_contains($pair, '=')) {
            [$key, $value] = explode('=', $pair, 2);
            $env[$key] = $value;
        }
    }
    $cwd = (string) @readlink("/proc/{$pid}/cwd");
    $t = array_search('-t', $args, true);
    $docroot = $t === false ? $cwd : (string) ($args[$t + 1] ?? '');
    if ($docroot !== '' && ! str_starts_with($docroot, '/')) {
        $docroot = rtrim($cwd, '/').'/'.$docroot;
    }

    return ['pid' => $pid, 'processes' => count($found), 'args' => $args, 'cwd' => $cwd, 'docroot' => $docroot, 'env' => $env, 'environ_read' => $environ !== false && $environ !== ''];
}

/** An env value as Laravel's `(bool) env(…)` reads it: «true»/«(true)» true, «false»/«empty»/«null» false, else PHP's cast. */
function envTruthy(?string $value): bool
{
    if ($value === null) {
        return false;
    }

    return match (strtolower(trim($value))) {
        'true', '(true)' => true,
        'false', '(false)', 'empty', '(empty)', 'null', '(null)' => false,
        default => (bool) $value,
    };
}

/** The HTTP side: every call is timed, a 429 waits its Retry-After, a transient 5xx of a safe call is retried. */
final class Api
{
    public ?string $token = null;

    public function __construct(private readonly string $base) {}

    /**
     * @param  array<string, mixed>|null  $body
     * @return array{status: int, body: mixed, raw: string, ms: int, error: string|null, code: string|null}
     */
    public function call(string $method, string $path, ?array $body = null, int $timeout = 120, bool $retry5xx = false): array
    {
        for ($attempt = 1; ; $attempt++) {
            $headers = [];
            $curl = curl_init($this->base.$path);
            $options = [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Accept: application/json', 'Content-Type: application/json',
                    ...($this->token === null ? [] : ['Authorization: Bearer '.$this->token]),
                ],
                CURLOPT_HEADERFUNCTION => static function ($c, string $line) use (&$headers): int {
                    $parts = explode(':', $line, 2);
                    if (count($parts) === 2) {
                        $headers[strtolower(trim($parts[0]))] = trim($parts[1]);
                    }

                    return strlen($line);
                },
            ];
            if ($method !== 'GET') {
                $options[CURLOPT_POSTFIELDS] = json_encode($body ?? new stdClass, JSON_UNESCAPED_UNICODE);
            }
            curl_setopt_array($curl, $options);
            $t = microtime(true);
            $raw = curl_exec($curl);
            $ms = (int) round((microtime(true) - $t) * 1000);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = $raw === false ? curl_error($curl) : null;
            curl_close($curl);
            $raw = $raw === false ? '' : (string) $raw;
            $decoded = json_decode($raw, true);
            $code = is_array($decoded) && isset($decoded['code']) && is_string($decoded['code']) ? $decoded['code'] : null;

            if ($status === 429 && $attempt <= 10) {
                $wait = max(1, (int) ($headers['retry-after'] ?? 5));
                say("   429 на {$method} {$path} — жду {$wait} с");
                sleep($wait);

                continue;
            }
            if ($retry5xx && ($status >= 500 || $status === 0) && $attempt <= 3) {
                say("   {$status} на {$method} {$path} (".short($error ?? $raw, 160).') — жду 30 с и повторяю');
                sleep(30);

                continue;
            }

            return ['status' => $status, 'body' => $decoded, 'raw' => $raw, 'ms' => $ms, 'error' => $error, 'code' => $code];
        }
    }

    /**
     * The `data` of an answer that must be one of `$ok`, or the run stops with what the server said.
     *
     * @param  array{status: int, body: mixed, raw: string, ms: int, error: string|null, code: string|null}  $r
     * @param  list<int>  $ok
     * @return array<string, mixed>
     */
    public static function data(array $r, string $what, array $ok = [200]): array
    {
        if (! in_array($r['status'], $ok, true) || ! is_array($r['body'])) {
            fail("{$what} → {$r['status']}".($r['error'] === null ? '' : " ({$r['error']})").': '.short($r['raw'], 600));
        }
        $data = $r['body']['data'] ?? $r['body'];

        return is_array($data) ? $data : [];
    }
}

/** @return array<string, mixed> */
function loadState(string $file): array
{
    if (! is_file($file)) {
        return [];
    }
    $state = json_decode((string) file_get_contents($file), true);

    return is_array($state) ? $state : [];
}

/** @param array<string, mixed> $state */
function saveState(string $file, array $state): void
{
    $state['updated_at'] = nowIso();
    file_put_contents($file, json_encode($state, JSON_OUT)."\n");
}

/**
 * The QA learner of the pair signed in the way the phone signs in — and proven to be a row of THIS process's database.
 *
 * @param  array<string, mixed>  $state
 */
function login(Api $api, array &$state): void
{
    $r = $api->call('POST', '/auth/dev', ['email' => $state['email'], 'device_name' => 'lang-1', 'timezone' => TIMEZONE], 60, true);
    if ($r['status'] !== 200 || ! is_array($r['body']) || ! is_string($r['body']['token'] ?? null)) {
        fail("POST /auth/dev → {$r['status']}: ".short($r['raw'], 400).' (сервер запущен? DEV_LOGIN_ENABLED?)');
    }
    $api->token = $r['body']['token'];
    $userId = (string) ($r['body']['user']['id'] ?? '');
    if ($userId === '' || DB::table('users')->where('id', $userId)->doesntExist()) {
        fail("ученик {$userId}, которого впустил сервер, — не строка базы этого процесса («".DB::connection()->getDatabaseName().'»): сервер и драйвер смотрят в разные базы');
    }
    // The user's id alone proves little when one base was copied from the other; the token the server has JUST minted
    // (Sanctum: `<id>|<secret>`, the row keeps sha256 of the secret) exists in one base only — the server's.
    [$tokenId, $secret] = array_pad(explode('|', $api->token, 2), 2, '');
    if ($secret === '' || DB::table('personal_access_tokens')->where('id', $tokenId)->where('token', hash('sha256', $secret))->doesntExist()) {
        fail('токен, который только что выдал сервер, не строка personal_access_tokens базы этого процесса («'.DB::connection()->getDatabaseName().'»): сервер и драйвер смотрят в разные базы');
    }
    $state['user_id'] = $userId;
}

/** @return list<array{prompt_version: string, action: string, hits: int, updated_at: string|null}> */
function packMissing(): array
{
    return array_map(static fn (array $r): array => [
        'prompt_version' => (string) $r['prompt_version'], 'action' => (string) $r['action'], 'hits' => (int) $r['hits'], 'updated_at' => $r['updated_at'],
    ], rows(DB::table('plan_check_counters')->where('check_name', 'lang.pack_missing')->orderBy('prompt_version')->orderBy('action')->get(['prompt_version', 'action', 'hits', 'updated_at'])));
}

/**
 * @param  list<array{prompt_version: string, action: string, hits: int, updated_at: string|null}>  $before
 * @param  list<array{prompt_version: string, action: string, hits: int, updated_at: string|null}>  $after
 * @return list<array{prompt_version: string, action: string, before: int, after: int, delta: int}>
 */
function packDelta(array $before, array $after): array
{
    $was = [];
    foreach ($before as $r) {
        $was[$r['prompt_version'].'|'.$r['action']] = $r['hits'];
    }
    $out = [];
    foreach ($after as $r) {
        $key = $r['prompt_version'].'|'.$r['action'];
        $out[] = ['prompt_version' => $r['prompt_version'], 'action' => $r['action'], 'before' => $was[$key] ?? 0, 'after' => $r['hits'], 'delta' => $r['hits'] - ($was[$key] ?? 0)];
    }

    return $out;
}

/**
 * @param  array<string, mixed>  $plan
 * @return array<string, mixed>|null
 */
function dayOne(array $plan): ?array
{
    foreach ($plan['days'] ?? [] as $d) {
        if ((int) $d['number'] === 1) {
            return $d;
        }
    }

    return null;
}

/**
 * @param  array<string, mixed>  $plan
 * @return array<string, mixed>|null
 */
function sceneOfDay(array $plan, int $day): ?array
{
    foreach ($plan['scenes'] ?? [] as $s) {
        if ((int) ($s['day_number'] ?? 0) === $day) {
            return $s;
        }
    }

    return null;
}

/**
 * What the learner says to the slot judge on a `speak_answer` card, and why.
 *
 * `literal` — the card's own line (`own_line.text_target`). It carries a value the lesson knows for the window, so the
 * judge accepts it BY CODE (`SlotJudge::judge`: «знакомое наполнение, услышанное подряд») and the model is never asked:
 * the pair's code path is tested, the judge's prompt is not. `forced` — the same line with that value swapped for a value
 * of ANOTHER frame of the day: no known value is heard, the code cannot rule, and the model rules on the window in the
 * new pair (its `reason_native` in the learner's own language is the thing to read). A card without a window, or a day
 * without another frame's value, stays `literal`.
 *
 * @param  array<string, mixed>  $card
 * @param  array<string, list<string>>  $valuesByFrame  frame ref → the window's values the cards of the day show
 * @return array{0: string, 1: string} heard, how
 */
function judgedHeard(array $card, array $valuesByFrame, bool $forced): array
{
    $line = trim((string) ($card['payload']['own_line']['text_target'] ?? $card['payload']['expected_text'] ?? ''));
    $frame = is_array($card['payload']['frame'] ?? null) ? $card['payload']['frame'] : [];
    $own = array_values(array_filter(array_map(static fn (mixed $f): string => is_array($f) ? trim((string) ($f['target'] ?? '')) : '', $frame['slot']['fillers'] ?? []), static fn (string $v): bool => $v !== ''));
    if (! $forced || $line === '' || $own === []) {
        return [$line, 'literal'];
    }
    usort($own, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
    $said = null;
    foreach ($own as $value) {
        if (mb_stripos($line, $value) !== false) {
            $said = $value;
            break;
        }
    }
    if ($said === null) {
        return [$line, 'literal'];
    }
    foreach ($valuesByFrame as $ref => $values) {
        if ($ref === ($frame['ref'] ?? null)) {
            continue;
        }
        foreach ($values as $other) {
            $clash = mb_stripos($line, $other) !== false;
            foreach ($own as $value) {
                $clash = $clash || mb_stripos($other, $value) !== false || mb_stripos($value, $other) !== false;
            }
            if (! $clash) {
                $pos = (int) mb_stripos($line, $said);

                return [mb_substr($line, 0, $pos).$other.mb_substr($line, $pos + mb_strlen($said)), 'forced'];
            }
        }
    }

    return [$line, 'literal'];
}

/** A frame with its window filled: «I have ___.» + «a sore throat» → «I have a sore throat.»; no window — as it is. */
function filled(string $frame, ?string $value): string
{
    if ($value === null || trim($value) === '' || preg_match('/_{2,}/u', $frame) !== 1) {
        return trim((string) preg_replace('/_{2,}/u', '', $frame));
    }

    return (string) preg_replace('/_{2,}/u', trim($value), $frame, 1);
}

$api = new Api("http://127.0.0.1:{$port}/api/v1");
$stateFile = "{$out}/{$pair}.state.json";
$state = loadState($stateFile);
$state += [
    'pair' => $pair, 'native' => $native, 'target' => $target, 'suffix' => $suffix,
    'email' => "qa-lang1-live-{$native}-{$target}{$suffix}@wt.test", 'goal' => $goals[$native], 'database' => $database,
];
if ($state['email'] !== "qa-lang1-live-{$native}-{$target}{$suffix}@wt.test") {
    fail("файл состояния {$stateFile} — ученика {$state['email']}, а не суффикса «{$suffix}»: другой прогон — другой --out или тот же --suffix");
}
say("{$pair} · {$phase} · база {$database} · сервер :{$port} · {$state['email']}");

// ── the server, before a single call: this tree's code, this base, a synchronous queue, no purchase nobody asked for ──
$server = serverProcess($port);
if ($server === null) {
    fail("на 127.0.0.1:{$port} в этом контейнере нет php -S — драйвер работает только рядом со своим сервером (docker exec … wt_lang1 php -S 127.0.0.1:{$port} -t /wt/public)");
}
if (! $server['environ_read']) {
    fail("окружение сервера (pid {$server['pid']}) не читается — базу и очередь сервера не проверить");
}
$dotenv = is_file(base_path('.env')) ? Dotenv\Dotenv::parse((string) file_get_contents(base_path('.env'))) : [];
$serverEnv = static fn (string $key): ?string => $server['env'][$key] ?? (isset($dotenv[$key]) ? (string) $dotenv[$key] : null);
$serverSummary = [
    'pid' => $server['pid'], 'processes' => $server['processes'], 'docroot' => $server['docroot'],
    'max_execution_time_0' => in_array('max_execution_time=0', $server['args'], true),
    'DB_DATABASE' => $serverEnv('DB_DATABASE'), 'QUEUE_CONNECTION' => $serverEnv('QUEUE_CONNECTION'), 'CACHE_STORE' => $serverEnv('CACHE_STORE'),
    'SPEECH_ENABLED' => envTruthy($serverEnv('SPEECH_ENABLED')), 'PLAN_MODEL_DRIVER' => $serverEnv('PLAN_MODEL_DRIVER') ?? '(по умолчанию)',
    'IMAGE_DRIVER' => $serverEnv('IMAGE_DRIVER') ?? '(по умолчанию)', 'PLAN_SLOT_JUDGE_QUOTA_STORE' => $serverEnv('PLAN_SLOT_JUDGE_QUOTA_STORE') ?? '(по умолчанию)',
];
say(sprintf('   сервер pid %d (%d проц.) · %s · база %s · очередь %s · модель %s · голос %s · фото %s · max_execution_time=0 %s',
    $server['pid'], $server['processes'], $server['docroot'], $serverSummary['DB_DATABASE'] ?? '—', $serverSummary['QUEUE_CONNECTION'] ?? '—',
    $serverSummary['PLAN_MODEL_DRIVER'], $serverSummary['SPEECH_ENABLED'] ? 'ВКЛ' : 'выкл', $serverSummary['IMAGE_DRIVER'], $serverSummary['max_execution_time_0'] ? 'да' : 'НЕТ'));
if (realpath($server['docroot']) !== realpath(base_path('public'))) {
    fail("сервер отдаёт «{$server['docroot']}», а не код этой ветки (".base_path('public').') — /app это main');
}
if ($serverSummary['DB_DATABASE'] !== $database) {
    fail('база сервера — «'.($serverSummary['DB_DATABASE'] ?? '?')."», а драйвера — «{$database}»: запусти оба с одним -e DB_DATABASE");
}
if ($serverSummary['QUEUE_CONNECTION'] !== 'sync') {
    fail('очередь сервера — «'.($serverSummary['QUEUE_CONNECTION'] ?? '?').'», не sync: задачи ушли бы в общий Redis к Horizon main');
}
if ($serverSummary['SPEECH_ENABLED'] && $database !== 'wordtrainer_e2e_test') {
    fail("у сервера SPEECH_ENABLED вкл. на «{$database}» — эта база озвучивает дни сама (не только по --plan): покупка, которой никто не заказывал");
}
if ($phase === 'create' && ! $serverSummary['max_execution_time_0']) {
    fail('сервер запущен без -d max_execution_time=0: php -S режет запрос на 30 с, а сборка плана и урока внутри POST /plans — минуты');
}
if ($phase === 'talk' && ! $serverSummary['SPEECH_ENABLED']) {
    say('   ВНИМАНИЕ: у сервера SPEECH_ENABLED выкл. — реплики роли без голоса (для живого прогона нужен сервер с голосом)');
}
$state['server'][$phase] = $serverSummary;

// ══ create ═══════════════════════════════════════════════════════════════════════════════════════════════════════════
if ($phase === 'create') {
    login($api, $state);
    if (! isset($state['plan_id'])) {
        $languages = Api::data($api->call('GET', '/languages', null, 60, true), 'GET /languages');
        $offered = [
            'target' => in_array($target, array_column($languages['targets'] ?? [], 'code'), true),
            'native' => in_array($native, array_column($languages['natives'] ?? [], 'code'), true),
        ];
        say("   GET /languages: цель {$target} ".($offered['target'] ? 'есть' : 'НЕТ').", родной {$native} ".($offered['native'] ? 'есть' : 'НЕТ'));
        $r = $api->call('PUT', '/profile', ['native_language' => $native, 'target_language' => $target, 'timezone' => TIMEZONE], 60, true);
        Api::data($r, 'PUT /profile');
        $profile = rows(DB::table('profiles')->where('user_id', $state['user_id'])->first(['native_language', 'target_language', 'timezone', 'gender']));
        if (! is_array($profile) || $profile['native_language'] !== $native || $profile['target_language'] !== $target) {
            fail("профиль не принял пару {$native}→{$target}: ".json_encode($profile, JSON_UNESCAPED_UNICODE));
        }
        say("   PUT /profile → {$r['status']} · профиль {$profile['native_language']}→{$profile['target_language']} · пол ".($profile['gender'] ?? 'null').' · '.$profile['timezone']);

        $before = packMissing();
        $state['create'] = [
            'started_at' => nowIso(), 'started_epoch' => microtime(true), 'languages_offered' => $offered, 'profile' => $profile,
            'pack_missing_before' => $before,
        ];
        saveState($stateFile, $state);
        say('   POST /plans … (сборка плана и урока дня 1 внутри запроса, до '.BUILD_TIMEOUT.' с)');
        $r = $api->call('POST', '/plans', ['goal_text' => $goals[$native], 'target_lang' => $target, 'level' => LEVEL, 'days_total' => DAYS_TOTAL], BUILD_TIMEOUT);
        $state['create']['post'] = ['status' => $r['status'], 'seconds' => round($r['ms'] / 1000, 1), 'code' => $r['code'], 'error' => $r['error'], 'data' => $r['body']['data'] ?? $r['body']];
        if ($r['status'] === 202 && is_string($r['body']['data']['id'] ?? null)) {
            $state['plan_id'] = $r['body']['data']['id'];
        } elseif ($r['status'] === 0) {
            // The client gave up, the server may not have: the newest plan of this learner is the one.
            $list = Api::data($api->call('GET', '/plans', null, 60, true), 'GET /plans');
            usort($list, static fn (array $a, array $b): int => strcmp((string) $b['created_at'], (string) $a['created_at']));
            $state['plan_id'] = $list[0]['id'] ?? null;
            say("   POST /plans не дождался ответа ({$r['error']}) — план ученика по GET /plans: ".($state['plan_id'] ?? '—'));
        }
        saveState($stateFile, $state);
        if (! is_string($state['plan_id'] ?? null)) {
            fail("POST /plans → {$r['status']}: ".short($r['raw'], 600));
        }
        say("   POST /plans → {$r['status']} за ".round($r['ms'] / 1000, 1)." с · план {$state['plan_id']}");
    } else {
        say("   план {$state['plan_id']} уже в файле состояния — второй не создаю, только жду сборку");
    }

    // The build to its end: the plan, then day 1's lesson (and its photos) — `ready` or `failed`.
    $planId = (string) $state['plan_id'];
    $startedEpoch = (float) ($state['create']['started_epoch'] ?? microtime(true));
    $deadline = $startedEpoch + BUILD_TIMEOUT;
    $build = Api::data($api->call('GET', "/plans/{$planId}/build", null, 60, true), 'GET build');
    while ($build['status'] === 'building' && microtime(true) < $deadline) {
        sleep(3);
        $build = Api::data($api->call('GET', "/plans/{$planId}/build", null, 60, true), 'GET build');
    }
    $planReadyAt = microtime(true);
    $plan = Api::data($api->call('GET', "/plans/{$planId}", null, 60, true), 'GET plan');
    while ($build['status'] === 'ready' && ! in_array(dayOne($plan)['lesson_status'] ?? null, ['ready', 'failed'], true) && microtime(true) < $deadline) {
        sleep(5);
        $plan = Api::data($api->call('GET', "/plans/{$planId}", null, 60, true), 'GET plan');
    }
    $dayOne = dayOne($plan);
    $scene = sceneOfDay($plan, 1);
    $after = packMissing();
    $delta = packDelta($state['create']['pack_missing_before'] ?? [], $after);
    // Somebody else's lesson built on the same base in the same window would count into the same counters.
    $others = rows(DB::table('plan_scenes')->where('plan_id', '!=', $planId)->where('build_started_at', '>=', $state['create']['started_at'] ?? nowIso())
        ->get(['id', 'plan_id', 'lesson_status', 'prompt_version_lesson', 'build_started_at']));
    $row = $scene === null ? null : rows(DB::table('plan_scenes')->where('id', $scene['id'])->first([
        'lesson_status', 'fail_reason', 'prompt_version_lesson', 'model_lesson', 'cost_usd_lesson', 'latency_ms_lesson', 'attempts_lesson',
        'checks_json', 'build_started_at', 'generated_at', 'built_at', 'partner_voice_gender', 'partner_voice_id',
    ]));
    if (is_array($row)) {
        $row['checks'] = array_map(static fn (array $c): string => (string) ($c['code'] ?? '?').(isset($c['address']) ? '@'.$c['address'] : ''), json_decode((string) ($row['checks_json'] ?? '[]'), true) ?: []);
        unset($row['checks_json']);
    }
    $planRow = rows(DB::table('plans')->where('id', $planId)->first(['status', 'native_lang', 'target_lang', 'prompt_version_plan', 'model_plan', 'cost_usd_plan', 'latency_ms_plan', 'attempts_plan', 'fail_reason', 'unclear_reason']));

    // The first complete wait is the measurement: a second run of the phase on a finished build only reads the state again
    // — its seconds and its counters would count the time between the runs and whatever else was built in it.
    $measured = isset($state['create']['day1_seconds']) && in_array($state['create']['day1']['lesson_status'] ?? null, ['ready', 'failed'], true);
    $fresh = [
        'finished_at' => nowIso(),
        'plan_seconds' => round($planReadyAt - $startedEpoch, 1),
        'day1_seconds' => round(microtime(true) - $startedEpoch, 1),
        'pack_missing_after' => $after,
        'pack_missing_delta' => $delta,
        'other_builds_in_window' => $others,
    ];
    if ($measured) {
        $delta = $state['create']['pack_missing_delta'];
        $others = $state['create']['other_builds_in_window'];
        $fresh = ['reread_at' => nowIso()];
    }
    $state['create'] = array_merge($state['create'], $fresh, [
        'build' => $build,
        'plan_status' => $plan['status'] ?? null,
        'plan_row' => $planRow,
        'days' => array_map(static fn (array $d): array => [
            'number' => $d['number'], 'type' => $d['type'], 'status' => $d['status'], 'lesson_status' => $d['lesson_status'],
            'title_native' => $d['title_native'], 'title_target' => $d['title_target'],
        ], $plan['days'] ?? []),
        'scenes' => array_map(static fn (array $s): array => [
            'id' => $s['id'], 'order' => $s['order'], 'kind' => $s['kind'], 'day_number' => $s['day_number'], 'title_native' => $s['title_native'],
            'title_target' => $s['title_target'], 'partner_role_native' => $s['partner_role_native'], 'partner_role_target' => $s['partner_role_target'],
            'lesson_status' => $s['lesson_status'], 'lesson_fail_reason' => $s['lesson_fail_reason'], 'prompt_version' => $s['prompt_version'],
            'cost_usd' => $s['cost_usd'], 'image' => $s['image'] !== null,
        ], $plan['scenes'] ?? []),
        'day1' => ['lesson_status' => $dayOne['lesson_status'] ?? null, 'fail_reason' => $scene['lesson_fail_reason'] ?? null, 'scene_id' => $scene['id'] ?? null, 'scene_row' => $row],
    ]);
    saveState($stateFile, $state);

    $gained = array_sum(array_column($delta, 'delta'));
    say(sprintf(
        '   сборка: план %s за %.1f с · день 1 «%s» — %s%s за %.1f с · урок %s · попыток %s · lang.pack_missing +%d%s',
        $build['status'], $state['create']['plan_seconds'], $scene['title_native'] ?? '—', $dayOne['lesson_status'] ?? '—',
        ($scene['lesson_fail_reason'] ?? null) === null ? '' : ' ('.$scene['lesson_fail_reason'].')', $state['create']['day1_seconds'],
        $row['prompt_version_lesson'] ?? '—', $row['attempts_lesson'] ?? '—', $gained, $others === [] ? '' : ' (в окне ещё '.count($others).' чужих сборок!)',
    ));
    say('   находки урока (checks_json): '.(($row['checks'] ?? []) === [] ? 'нет' : implode(', ', $row['checks'])));
    exit(($dayOne['lesson_status'] ?? null) === 'ready' ? 0 : 2);
}

// Every phase below works on a plan the create phase made.
$planId = is_string($state['plan_id'] ?? null) ? $state['plan_id'] : fail("в {$stateFile} нет плана — сначала create");
login($api, $state);

// ══ walk ═════════════════════════════════════════════════════════════════════════════════════════════════════════════
if ($phase === 'walk') {
    $walk = $state['walk'] ?? ['started_at' => nowIso(), 'responses' => [], 'judged' => [], 'closes' => []];
    $plan = Api::data($api->call('GET', "/plans/{$planId}", null, 60, true), 'GET plan');
    if (($plan['status'] ?? null) === 'ready') {
        $r = $api->call('POST', "/plans/{$planId}/start", null, 60, true);
        $started = Api::data($r, 'POST start');
        $walk['start'] = ['status' => $r['status'], 'plan_status' => $started['status'] ?? null];
        say("   POST start → {$r['status']} · план {$started['status']}");
    } elseif (! in_array($plan['status'] ?? null, ['active', 'overdue'], true)) {
        fail("план {$planId} — «".($plan['status'] ?? '?').'», начать нечего');
    }

    // «Открыть»: the cards dealt (a day whose lesson is still being written waits for it).
    $deadline = time() + 900;
    while (true) {
        $r = $api->call('POST', "/plans/{$planId}/days/1/open", null, 120, true);
        if ($r['status'] === 409 && $r['code'] === 'plan_day_building' && time() < $deadline) {
            say('   день 1 ещё собирается (409 plan_day_building) — жду 10 с');
            sleep(10);

            continue;
        }
        break;
    }
    $opened = Api::data($r, 'POST open');
    $walk['open'] = ['status' => $r['status'], 'day_status' => $opened['status'] ?? null, 'cards' => count($opened['cards'] ?? [])];
    $byStage = [];
    foreach ($opened['cards'] ?? [] as $c) {
        $byStage[$c['stage']] = ($byStage[$c['stage']] ?? 0) + 1;
    }
    say("   POST open → {$r['status']} · карточек ".count($opened['cards'] ?? []).' · '.implode(', ', array_map(static fn (string $s, int $n): string => "{$s} {$n}", array_keys($byStage), $byStage)));

    $room = Api::data($api->call('GET', "/plans/{$planId}/days/1", null, 60, true), 'GET room');
    $stages = array_values(array_filter(array_column($room['stages'] ?? [], 'stage'), static fn (string $s): bool => $s !== 'conversation' && isset($byStage[$s])));
    $n = count($walk['responses']);
    // Every window value the day's cards show, by frame: the pool a `forced` judged line borrows another frame's value from.
    $valuesByFrame = [];
    foreach ($opened['cards'] ?? [] as $c) {
        $f = $c['payload']['frame'] ?? null;
        if (! is_array($f) || ! is_string($f['ref'] ?? null) || ! is_array($f['slot']['fillers'] ?? null)) {
            continue;
        }
        foreach ($f['slot']['fillers'] as $filler) {
            $value = is_array($filler) ? trim((string) ($filler['target'] ?? '')) : '';
            if ($value !== '' && ! in_array($value, $valuesByFrame[$f['ref']] ?? [], true)) {
                $valuesByFrame[$f['ref']][] = $value;
            }
        }
    }

    /** One answer posted and recorded; returns the card dealt back, if any. */
    $answer = static function (array $card, string $result, int $attempts) use ($api, $planId, &$walk, &$n): ?array {
        $r = $api->call('POST', "/plans/{$planId}/days/1/cards/{$card['id']}/answer", ['result' => $result, 'attempts' => $attempts], 60, true);
        $data = is_array($r['body']['data'] ?? null) ? $r['body']['data'] : null;
        $n++;
        $walk['responses'][] = [
            'n' => $n, 'stage' => $card['stage'], 'kind' => $card['kind'], 'pos' => $card['position'], 'card' => $card['id'], 'sent' => $result,
            'attempts' => $attempts, 'status' => $r['status'], 'code' => $r['code'], 'result' => $data['card']['result'] ?? null,
            'requeued' => $data['requeued']['id'] ?? null, 'returns_tomorrow' => $data['unit']['returns_tomorrow'] ?? null,
            'day' => $data['day'] ?? null,
        ];
        say(sprintf('   %3d %-8s %-19s #%-3s %s → %d%s%s', $n, $card['stage'], $card['kind'], (string) $card['position'], $result, $r['status'],
            $r['code'] === null ? '' : ' '.$r['code'], ($data['requeued'] ?? null) === null ? '' : ' · копия '.$data['requeued']['id']));

        return $data['requeued'] ?? null;
    };

    foreach ($stages as $stage) {
        for ($pass = 1; $pass <= 3; $pass++) {
            $cards = Api::data($api->call('GET', "/plans/{$planId}/days/1/cards", null, 60, true), 'GET cards')['cards'] ?? [];
            $queue = array_values(array_filter($cards, static fn (array $c): bool => $c['stage'] === $stage && $c['result'] === null));
            usort($queue, static fn (array $a, array $b): int => $a['position'] <=> $b['position']);
            if ($queue === []) {
                break;
            }
            while ($queue !== []) {
                $card = array_shift($queue);
                $kind = CardKind::tryFrom((string) $card['kind']);
                if ($kind === null) {
                    // A kind the driver does not know: the client's branch for it is «skip», not a crash.
                    $copy = $answer($card, 'skipped', 1);
                } elseif ($kind->isJudged()) {
                    $attempts = 1;
                    if (count($walk['judged']) < MAX_JUDGED) {
                        // The first judged card says its own line (the code rules); the next ones — in `mixed` — a line the
                        // model has to rule on ({@see judgedHeard()}).
                        [$heard, $how] = judgedHeard($card, $valuesByFrame, $judgeMode === 'mixed' && count($walk['judged']) > 0);
                        // No retry on a 5xx: the judge is a paid model call made before the write, a second POST pays twice.
                        $r = $api->call('POST', "/plans/{$planId}/days/1/cards/{$card['id']}/judge", ['heard' => $heard, 'hinted' => false], 60);
                        $v = is_array($r['body']['data'] ?? null) ? $r['body']['data'] : [];
                        // The ruling as the card keeps it (`response.judge`): who ruled — code, model or nobody — and the call's price.
                        $ruling = is_array($v['card']['response']['judge'] ?? null) ? $v['card']['response']['judge'] : [];
                        $walk['judged'][] = [
                            'card' => $card['id'], 'exchange' => $card['payload']['exchange']['ref'] ?? null, 'heard' => $heard, 'how' => $how,
                            'own_line' => $card['payload']['own_line']['text_target'] ?? null,
                            'task_native' => $card['payload']['task_native'] ?? null, 'frame' => $card['payload']['frame']['frame_target'] ?? null,
                            'status' => $r['status'], 'code' => $r['code'], 'accepted' => $v['accepted'] ?? null, 'slot_value' => $v['slot_value'] ?? null,
                            'reason_native' => $v['reason_native'] ?? null, 'result' => $v['result'] ?? null, 'attempts' => $v['attempts'] ?? null, 'ms' => $r['ms'],
                            'by' => $ruling['by'] ?? null, 'model' => $ruling['model'] ?? null, 'prompt_version' => $ruling['prompt_version'] ?? null,
                            'cost_usd' => $ruling['cost_usd'] ?? null, 'tokens_in' => $ruling['tokens_in'] ?? null, 'tokens_out' => $ruling['tokens_out'] ?? null,
                        ];
                        say(sprintf('   судья %-11s [%s] «%s» → %d · %s%s%s · решил %s%s', (string) ($card['payload']['exchange']['ref'] ?? '?'), $how, short($heard, 60), $r['status'],
                            ($v['accepted'] ?? null) === true ? 'зачтено' : 'не зачтено', ($v['slot_value'] ?? null) === null ? '' : ' · окно «'.$v['slot_value'].'»',
                            ($v['reason_native'] ?? null) === null ? '' : ' · '.$v['reason_native'], (string) ($ruling['by'] ?? '?'),
                            ($ruling['cost_usd'] ?? null) === null ? '' : ' $'.$ruling['cost_usd']));
                        $n++;
                        $walk['responses'][] = ['n' => $n, 'stage' => $card['stage'], 'kind' => $card['kind'], 'pos' => $card['position'], 'card' => $card['id'], 'sent' => 'judge',
                            'attempts' => null, 'status' => $r['status'], 'code' => $r['code'], 'result' => $v['result'] ?? null, 'requeued' => null, 'returns_tomorrow' => null, 'day' => null];
                        if (($v['result'] ?? null) !== null) {
                            continue;
                        }
                        $attempts = max(1, (int) ($v['attempts'] ?? 1));
                    }
                    $copy = $answer($card, 'skipped', $attempts);
                } else {
                    $copy = $answer($card, $kind->allows(CardResult::Passed) ? 'passed' : 'skipped', 1);
                }
                if ($copy !== null) {
                    $queue[] = $copy;
                }
            }
        }
        $r = $api->call('POST', "/plans/{$planId}/days/1/stages/{$stage}/close", null, 60, true);
        $closedRoom = is_array($r['body']['data'] ?? null) ? $r['body']['data'] : null;
        $stateOf = [];
        foreach ($closedRoom['stages'] ?? [] as $s) {
            $stateOf[$s['stage']] = $s['state'];
        }
        $walk['closes'][] = ['stage' => $stage, 'status' => $r['status'], 'code' => $r['code'], 'meta' => $r['body']['meta'] ?? null, 'stages_after' => $stateOf];
        say("   этап {$stage} закрыт → {$r['status']}".($r['code'] === null ? '' : " {$r['code']}").
            (isset($r['body']['meta']['remaining']) ? ' · осталось '.json_encode($r['body']['meta']['remaining'], JSON_UNESCAPED_UNICODE) : '').
            ' · '.implode(' ', array_map(static fn (string $s, string $v): string => "{$s}:{$v}", array_keys($stateOf), $stateOf)));
        saveState($stateFile, ['walk' => $walk] + $state);
    }

    $room = Api::data($api->call('GET', "/plans/{$planId}/days/1", null, 60, true), 'GET room');
    $walk['finished_at'] = nowIso();
    $walk['window_after'] = array_map(static fn (array $s): array => ['stage' => $s['stage'], 'state' => $s['state'], 'done_count' => $s['done_count'], 'total' => $s['total']], $room['window']['stages'] ?? []);
    $walk['metrics_after'] = $room['metrics'] ?? null;
    $cardsLeft = count(array_filter(Api::data($api->call('GET', "/plans/{$planId}/days/1/cards", null, 60, true), 'GET cards')['cards'] ?? [], static fn (array $c): bool => $c['result'] === null));
    $walk['cards_left'] = $cardsLeft;
    // The walk is done when no card waits, every stage closed with 200, and the talk's row is the day's current one — else
    // the talk phase would meet a locked or absent sixth stage.
    $closesFailed = array_values(array_filter($walk['closes'], static fn (array $x): bool => $x['status'] !== 200));
    $talkRow = null;
    foreach ($walk['window_after'] as $s) {
        if ($s['stage'] === 'conversation') {
            $talkRow = $s['state'];
        }
    }
    $walk['conversation_row'] = $talkRow;
    $state['walk'] = $walk;
    saveState($stateFile, $state);
    say('   окно после прохода: '.implode(' · ', array_map(static fn (array $s): string => $s['stage'].' '.$s['state'], $walk['window_after'])).
        " · неотвеченных карточек {$cardsLeft} · этапов не закрыто ".count($closesFailed).' · судья '.count($walk['judged']).' раз ('.
        implode(', ', array_map(static fn (array $j): string => $j['how'].'→'.($j['by'] ?? '?'), $walk['judged'])).')');
    if ($talkRow !== 'current') {
        say('   ряд разговора в окне — '.($talkRow ?? 'НЕТ').', не current: разговор этого дня начать не выйдет');
    }
    exit($cardsLeft === 0 && $closesFailed === [] && $talkRow === 'current' ? 0 : 2);
}

// ══ talk ═════════════════════════════════════════════════════════════════════════════════════════════════════════════
if ($phase === 'talk') {
    $talk = $state['talk'] ?? ['started_at' => nowIso(), 'moves' => [], 'documents' => []];
    if (($state['walk']['conversation_row'] ?? null) !== 'current') {
        say('   ВНИМАНИЕ: проход дня не записан или ряд разговора после него не current ('.($state['walk']['conversation_row'] ?? 'прохода нет').') — сначала walk');
    }
    if (($talk['state'] ?? null) === 'ended') {
        say("   разговор {$talk['conversation_id']} уже окончен ({$talk['ended_reason']}) — новый (повтор) не начинаю");
        exit(0);
    }
    // The lesson's phrases of the day, by ref: the target-language counterpart of every hint.
    $room = Api::data($api->call('GET', "/plans/{$planId}/days/1", null, 60, true), 'GET room');
    $phrases = [];
    foreach ($room['window']['program']['phrases']['items'] ?? [] as $p) {
        $phrases[($p['scene']['id'] ?? '').':'.$p['ref']] = $p;
        $phrases[$p['ref']] ??= $p;
    }
    $phraseOf = static fn (?string $scene, ?string $ref): ?array => $ref === null ? null : ($phrases[($scene ?? '').':'.$ref] ?? $phrases[$ref] ?? null);

    /**
     * The move: the first source that gives a non-empty line — `said` is never sent with nothing heard (that is a move
     * the recogniser made, not the learner); nothing left — `skip`.
     *
     * @return array{0: string, 1: string, 2: string} kind, heard, where the line came from
     */
    $next = static function (array $doc) use ($phraseOf): array {
        $hint = $doc['hints'] ?? [];
        $candidates = [];
        if (trim((string) ($hint['sentence'] ?? '')) !== '') {
            $candidates[] = [(string) ($hint['target'] ?? ''), 'hints.target'];
            $p = $phraseOf($hint['scene_id'] ?? null, $hint['ref'] ?? null);
            $candidates[] = [(string) ($p['text'] ?? ''), 'фраза урока '.($hint['ref'] ?? '?').' (hints.ref)'];
            foreach ($doc['targets'] ?? [] as $t) {
                if ($t['ref'] === ($hint['ref'] ?? null) && $t['scene_id'] === ($hint['scene_id'] ?? null)) {
                    $candidates[] = [filled((string) $t['frame_target'], $t['example_target'] ?? null), 'каркас '.$t['ref'].' + пример (hints.ref)'];
                }
            }
        }
        foreach ($doc['targets'] ?? [] as $t) {
            if (($t['state'] ?? (($t['said'] ?? false) ? 'said' : 'none')) === 'said') {
                continue;
            }
            $p = $phraseOf($t['scene_id'] ?? null, $t['ref'] ?? null);
            $filler = $p['frame']['slot']['fillers'][0]['target'] ?? $t['example_target'] ?? null;
            $candidates[] = [filled((string) $t['frame_target'], is_string($filler) ? $filler : null), 'первая несказанная '.$t['ref'].' + первое наполнение'];
        }
        foreach ($candidates as [$line, $source]) {
            if (trim($line) !== '') {
                return ['said', trim($line), $source];
            }
        }

        return ['skip', '', 'skip'];
    };

    $compact = static fn (array $t): array => [
        'index' => $t['index'], 'speaker' => $t['speaker'], 'kind' => $t['kind'], 'text_target' => $t['text_target'], 'text_native' => $t['text_native'],
        'scene_id' => $t['scene_id'] ?? null, 'scene_event' => $t['scene_event'] ?? null, 'understood' => $t['understood'] ?? null, 'off_topic' => $t['off_topic'] ?? null,
        'phrases_used' => array_column($t['phrases_used'] ?? [], 'ref'), 'extra_said' => array_column($t['extra_said'] ?? [], 'ref'),
        'audio' => $t['audio'] === null ? null : ['ref' => $t['audio']['ref'], 'url' => ($t['audio']['url'] ?? null) !== null, 'duration_ms' => $t['audio']['duration_ms'], 'voice' => $t['audio']['voice']],
    ];
    $seen = 0;
    $print = static function (array $doc) use (&$seen, $compact): array {
        $new = [];
        foreach ($doc['turns'] ?? [] as $t) {
            if ($t['index'] <= $seen) {
                continue;
            }
            $new[] = $compact($t);
            $seen = max($seen, (int) $t['index']);
            if ($t['speaker'] !== 'learner') {
                say(sprintf('   %2d РОЛЬ%s «%s» / «%s» · голос %s', $t['index'], ($t['scene_event'] ?? null) === null ? '' : ' ['.$t['scene_event'].']', short($t['text_target'], 110),
                    short($t['text_native'], 110), $t['audio'] === null ? 'нет' : (($t['audio']['url'] ?? null) === null ? 'без url' : 'есть')));
            }
        }

        return $new;
    };

    /**
     * A call of the talk that may meet a role that did not answer (503 `plan_conversation_unavailable`: nothing was
     * written, the move is asked again — up to three times), or a transient fatal of a class half-edited in the tree
     * (any other 5xx: once more after 30 s). A 5xx that stays is the server's, and the talk stops on it — at once: the
     * role's model is paid BEFORE the guards that may throw, and the rolled-back move never reaches the talk's $0.08 cap,
     * so every further try is money the cap does not see.
     */
    $talkCall = static function (string $method, string $path, ?array $body) use ($api): array {
        for ($i = 1; ; $i++) {
            $r = $api->call($method, $path, $body, 120);
            if ($r['status'] === 503 && $i <= 3) {
                say("   503 ".($r['code'] ?? '')." на {$method} {$path} — роль не ответила, жду 10 с и повторяю ход");
                sleep(10);

                continue;
            }
            if (($r['status'] >= 500 || $r['status'] === 0) && $i <= 1) {
                say("   {$r['status']} на {$method} {$path}: ".short((string) ($r['body']['message'] ?? $r['error'] ?? $r['raw']), 160).' — жду 30 с и повторяю один раз');
                sleep(30);

                continue;
            }

            return $r;
        }
    };

    if (is_string($talk['conversation_id'] ?? null)) {
        $doc = Api::data($api->call('GET', "/plans/{$planId}/conversation/{$talk['conversation_id']}", null, 60, true), 'GET conversation');
        say("   продолжаю разговор {$talk['conversation_id']} · {$doc['state']}");
        $seen = max(array_column($doc['turns'], 'index') ?: [0]);
    } else {
        $r = $talkCall('POST', "/plans/{$planId}/days/1/conversation", ['hints' => true]);
        $doc = Api::data($r, 'POST conversation');
        $talk['conversation_id'] = $doc['id'];
        $talk['start'] = ['status' => $r['status'], 'ms' => $r['ms'], 'talk_title_native' => $doc['talk_title_native'] ?? null, 'turns_left' => $doc['turns_left'] ?? null,
            'minutes_estimate' => $doc['minutes_estimate'] ?? null, 'partner' => $doc['partner'] ?? null, 'scene' => $doc['scene'] ?? null,
            'targets' => array_map(static fn (array $t): array => ['ref' => $t['ref'], 'frame_target' => $t['frame_target'], 'example_target' => $t['example_target'], 'frame_native' => $t['frame_native']], $doc['targets'] ?? [])];
        say("   POST conversation → {$r['status']} за {$r['ms']} мс · «".($doc['talk_title_native'] ?? '—')."» · целей ".count($doc['targets'] ?? []).' · ходов '.($doc['turns_left'] ?? '?'));
        $talk['moves'][] = ['move' => 0, 'sent' => null, 'status' => $r['status'], 'ms' => $r['ms'], 'state' => $doc['state'], 'turns_left' => $doc['turns_left'] ?? null, 'hints' => $doc['hints'] ?? null, 'new_turns' => $print($doc)];
        $talk['documents'][] = $doc;
        saveState($stateFile, ['talk' => $talk] + $state);
    }

    $moves = count(array_filter($doc['turns'] ?? [], static fn (array $t): bool => $t['speaker'] === 'learner'));
    $waits = 0;
    unset($talk['error']);
    while (($doc['state'] ?? null) !== 'ended' && $moves < MAX_TURNS) {
        if (($doc['state'] ?? null) === 'agent_turn' && $waits++ < 10) {
            sleep(2);
            $doc = Api::data($api->call('GET', "/plans/{$planId}/conversation/{$talk['conversation_id']}", null, 60, true), 'GET conversation');

            continue;
        }
        [$kind, $heard, $source] = $next($doc);
        $hint = $doc['hints'] ?? [];
        $r = $talkCall('POST', "/plans/{$planId}/conversation/{$talk['conversation_id']}/turn", ['kind' => $kind, 'heard' => $heard]);
        if ($r['status'] !== 200) {
            $message = (string) ($r['body']['message'] ?? $r['body']['detail'] ?? $r['error'] ?? $r['raw']);
            say("   ход → {$r['status']} ".($r['code'] ?? short($message, 200)).' — перечитываю документ');
            $talk['moves'][] = ['move' => $moves + 1, 'sent' => ['kind' => $kind, 'heard' => $heard, 'source' => $source], 'status' => $r['status'], 'code' => $r['code'], 'ms' => $r['ms'],
                'error' => ['message' => short($message, 400), 'exception' => $r['body']['exception'] ?? null]];
            $doc = Api::data($api->call('GET', "/plans/{$planId}/conversation/{$talk['conversation_id']}", null, 60, true), 'GET conversation');
            if ($r['code'] === 'plan_conversation_ended') {
                break;
            }
            // «Not your turn» is a moment's (the role's line is still being written): read again, a bounded number of times.
            if ($r['code'] === 'plan_conversation_not_your_turn' && $waits++ <= 10) {
                continue;
            }
            // Anything else — a 5xx that survived its retry, a refusal of the move's shape, a 404 — is the server's
            // answer to THIS move, and asking again asks (and pays) for the same answer: the talk stops here.
            $talk['error'] = ['move' => $moves + 1, 'sent' => ['kind' => $kind, 'heard' => $heard], 'status' => $r['status'], 'code' => $r['code'], 'message' => short($message, 400), 'exception' => $r['body']['exception'] ?? null];
            say("   СТОП разговора: ход {$talk['error']['move']} → {$r['status']} ".($r['code'] ?? '').' — ответ сервера, не мгновения');
            break;
        }
        $doc = Api::data($r, 'POST turn');
        $moves++;
        say(sprintf('   %2d > %s%s', $moves, $kind === 'said' ? '«'.short($heard, 110).'»' : strtoupper($kind), " ({$source}; подсказка «".short((string) ($hint['sentence'] ?? ''), 70).'»)'));
        $new = $print($doc);
        $talk['moves'][] = [
            'move' => $moves, 'sent' => ['kind' => $kind, 'heard' => $heard, 'source' => $source, 'hint_sentence' => $hint['sentence'] ?? null, 'hint_ref' => $hint['ref'] ?? null, 'hint_target' => $hint['target'] ?? null],
            'status' => $r['status'], 'ms' => $r['ms'], 'state' => $doc['state'], 'turns_left' => $doc['turns_left'] ?? null,
            'targets' => array_map(static fn (array $t): string => $t['ref'].'='.($t['state'] ?? ($t['said'] ? 'said' : 'none')), $doc['targets'] ?? []),
            'hints' => $doc['hints'] ?? null, 'new_turns' => $new,
        ];
        $talk['documents'][] = $doc;
        saveState($stateFile, ['talk' => $talk] + $state);
    }

    $talk['state'] = $doc['state'] ?? null;
    $talk['ended_reason'] = $doc['summary']['ended_reason'] ?? null;
    $talk['summary'] = $doc['summary'] ?? null;
    $talk['final'] = $doc;
    $talk['finished_at'] = nowIso();
    $state['talk'] = $talk;
    saveState($stateFile, $state);
    $s = $doc['summary'] ?? null;
    if (isset($talk['error'])) {
        say("   ошибка сервера на ходе {$talk['error']['move']}: {$talk['error']['status']} ".($talk['error']['exception'] ?? '').' — '.short($talk['error']['message'], 200));
    }
    say(sprintf('   итог: %s · ходов ученика %d%s', $doc['state'] ?? '?', $moves, $s === null ? ' · итога нет' : sprintf(
        ' · конец %s%s · целей %d из %d · сказал сам %d · понял все: %s · минут %d',
        $s['ended_reason'], $s['ended_by_limit'] ? ' (по лимиту)' : '', $s['phrases_used'], $s['phrases_total'], $s['said_count'], $s['understood_all'] ? 'да' : 'нет', $s['minutes'],
    )));
    exit(($doc['state'] ?? null) === 'ended' ? 0 : 2);
}

// ══ dump ═════════════════════════════════════════════════════════════════════════════════════════════════════════════
$plan = Api::data($api->call('GET', "/plans/{$planId}", null, 60, true), 'GET plan');
$room = Api::data($api->call('GET', "/plans/{$planId}/days/1", null, 60, true), 'GET room');
$sceneIds = array_column($plan['scenes'] ?? [], 'id');

$planRow = rows(DB::table('plans')->where('id', $planId)->first([
    'status', 'native_lang', 'target_lang', 'level', 'days_total', 'prompt_version_plan', 'model_plan', 'cost_usd_plan', 'latency_ms_plan', 'attempts_plan',
    'fail_reason', 'unclear_reason', 'build_started_at', 'started_at', 'created_at',
]));
$scenes = array_map(static function (array $s): array {
    $s['checks'] = array_map(static fn (array $c): array => ['code' => $c['code'] ?? null, 'address' => $c['address'] ?? null, 'detail' => $c['detail'] ?? null], json_decode((string) ($s['checks_json'] ?? '[]'), true) ?: []);
    unset($s['checks_json']);

    return $s;
}, rows(DB::table('plan_scenes')->where('plan_id', $planId)->orderBy('order')->get([
    'id', 'order', 'kind', 'title_native', 'title_target', 'partner_role_native', 'partner_role_target', 'lesson_status', 'fail_reason', 'cost_usd_lesson',
    'prompt_version_lesson', 'model_lesson', 'attempts_lesson', 'latency_ms_lesson', 'partner_voice_gender', 'partner_voice_id', 'checks_json', 'build_started_at', 'built_at',
])));

// The day's voice: every line bought for the plan's scenes, by voice and by kind of line.
$audios = rows(DB::table('plan_line_audios')->whereIn('scene_id', $sceneIds)->orderBy('created_at')->get(['scene_id', 'line_ref', 'voice_key', 'characters', 'credits', 'cost_usd', 'duration_ms', 'created_at']));
$lineKind = static fn (string $ref): string => match (true) {
    preg_match('/^x\d+$/', $ref) === 1 => 'реплика собеседника',
    preg_match('/^x\d+b$/', $ref) === 1 => 'реплика ученика',
    preg_match('/^p\d+$/', $ref) === 1 => 'фраза',
    preg_match('/^p\d+\.f\d+$/', $ref) === 1 => 'фраза с наполнением',
    preg_match('/^v\d+$/', $ref) === 1 => 'слово',
    default => 'другое',
};
$sum = static function (array $list, ?callable $key = null): array {
    $out = [];
    foreach ($list as $a) {
        $k = $key === null ? 'всего' : $key($a);
        $out[$k] ??= ['lines' => 0, 'credits' => 0, 'characters' => 0, 'usd' => 0.0];
        $out[$k]['lines']++;
        $out[$k]['credits'] += (int) ($a['credits'] ?? 0);
        $out[$k]['characters'] += (int) ($a['characters'] ?? 0);
        $out[$k]['usd'] += (float) ($a['cost_usd'] ?? 0);
    }

    return $out;
};
$voiceDay = [
    'total' => $sum($audios)['всего'] ?? ['lines' => 0, 'credits' => 0, 'characters' => 0, 'usd' => 0.0],
    'by_voice' => $sum($audios, static fn (array $a): string => (string) $a['voice_key']),
    'by_kind' => $sum($audios, static fn (array $a): string => $lineKind((string) $a['line_ref'])),
];

// The talk and its journal.
$talkId = $state['talk']['conversation_id'] ?? null;
$conversation = $talkId === null ? null : rows(DB::table('conversations')->where('id', $talkId)->first([
    'id', 'type', 'state', 'ended_reason', 'cost_usd', 'turn_limit', 'hints_enabled', 'started_at', 'ended_at', 'scene_ids',
]));
$turns = $talkId === null ? [] : rows(DB::table('conversation_turns')->where('conversation_id', $talkId)->orderBy('turn_index')->get([
    'turn_index', 'speaker', 'kind', 'text_target', 'text_native', 'scene_id', 'scene_event', 'understood', 'off_topic', 'phrases_used', 'phrases_almost',
    'opens_target', 'hint_native', 'audio_voice_key', 'audio_characters', 'audio_credits', 'audio_cost_usd', 'audio_duration_ms', 'model', 'prompt_version',
    'tokens_in', 'tokens_out', 'model_cost_usd', 'speech_cost_usd', 'cost_usd', 'model_latency_ms', 'speech_latency_ms', 'latency_ms', 'created_at',
]));
foreach ($turns as &$t) {
    $t['phrases_used'] = json_decode((string) $t['phrases_used'], true) ?: [];
    $t['phrases_almost'] = json_decode((string) $t['phrases_almost'], true) ?: [];
}
unset($t);

/**
 * The answer a guard refused, read back from the outbound journal. The two journals share no key, so the call is found
 * by its time and its LENGTH: of the role's answers logged within the call's seconds (`occurred_at` in [start − 1 s,
 * finish + 2 s]), the one whose `duration_ms` is nearest the call's `latency_ms` — on the e2e base's seven rejections the
 * right one is 10–29 ms off and its neighbours hundreds. (The nearest by the ids' clocks, tried first, took the NEXT call —
 * the accepted retry — in six of the seven.) No `latency_ms` — the nearest by the ids' clocks. A fake model goes to no
 * vendor — then there is nothing to read.
 */
$refused = static function (?string $modelCallId): ?array {
    if ($modelCallId === null) {
        return null;
    }
    $call = DB::table('model_calls')->where('id', $modelCallId)->first(['started_at', 'finished_at', 'latency_ms', 'model', 'cost_usd']);
    if ($call === null) {
        return ['found' => false, 'why' => 'нет строки model_calls'];
    }
    // With their offset: a bare «Y-m-d H:i:s» would be read in the session's zone.
    $from = (new DateTimeImmutable((string) $call->started_at))->modify('-1 second')->format('Y-m-d H:i:sP');
    $to = (new DateTimeImmutable((string) ($call->finished_at ?? $call->started_at)))->modify('+2 seconds')->format('Y-m-d H:i:sP');
    $best = null;
    $candidates = 0;
    foreach (DB::table('api_request_logs')->where('direction', 'outbound')->where('purpose', 'plan')->whereBetween('occurred_at', [$from, $to])->get(['id', 'occurred_at', 'duration_ms', 'response_body']) as $log) {
        $found = null;
        $walkJson = static function (mixed $v) use (&$walkJson, &$found): void {
            if ($found !== null) {
                return;
            }
            if (is_string($v) && str_contains($v, 'reply_native')) {
                $d = json_decode($v, true);
                if (is_array($d) && array_key_exists('reply_native', $d)) {
                    $found = $d;
                }
            } elseif (is_array($v)) {
                foreach ($v as $x) {
                    $walkJson($x);
                }
            }
        };
        $walkJson(json_decode((string) $log->response_body, true));
        if ($found === null) {
            continue;
        }
        $candidates++;
        $byLength = $call->latency_ms !== null && $log->duration_ms !== null;
        $distance = $byLength ? abs((int) $log->duration_ms - (int) $call->latency_ms) : abs(ulidMs((string) $log->id) - ulidMs($modelCallId));
        if ($best === null || $distance < $best['distance_ms']) {
            $best = ['found' => true, 'log_id' => $log->id, 'matched_by' => $byLength ? 'duration_ms ≈ latency_ms' : 'ulid', 'distance_ms' => $distance,
                'reply_target' => $found['reply_target'] ?? null, 'reply_native' => $found['reply_native'] ?? null];
        }
    }
    if ($best !== null) {
        $best['candidates'] = $candidates;
    }

    return $best ?? ['found' => false, 'why' => 'в api_request_logs нет ответа модели в окне вызова (фейковая модель в сеть не ходит)'];
};
$rejections = $talkId === null ? [] : array_map(static function (array $r) use ($refused): array {
    $r['detail'] = json_decode((string) $r['detail'], true) ?: [];
    $r['refused_answer'] = $refused($r['model_call_id']);

    return $r;
}, rows(DB::table('conversation_rejections')->where('conversation_id', $talkId)->orderBy('turn_index')->orderBy('attempt')->get(['turn_index', 'attempt', 'kind', 'reason', 'detail', 'model_call_id', 'created_at'])));

// The slot judge's calls: the journal of model calls says `judge` for them — read in the walk's window.
$judgeCalls = [];
if (isset($state['walk']['started_at'], $state['walk']['finished_at'])) {
    $judgeCalls = rows(DB::table('model_calls')->where('purpose', 'judge')->whereBetween('started_at', [$state['walk']['started_at'], $state['walk']['finished_at']])
        ->orderBy('started_at')->get(['id', 'model', 'status', 'tokens_in', 'tokens_out', 'cost_usd', 'latency_ms', 'started_at']));
}

$money = static fn (float|int|string|null $usd): float => round((float) ($usd ?? 0), 6);
// The talk's money, from its journal: `audio_cost_usd` is the voice of a line, `model_cost_usd` the role's model on the
// move (every attempt — a refused answer and the one asked after it, `TurnCost::plusModelCall`); `speech_cost_usd` is
// the same voice again and `cost_usd` their sum — and `conversations.cost_usd` is the sum of THOSE, voice included. So
// the bill takes voice and model from the turns once each, and the talk's own total only to check it against them.
$talkVoice = ['lines' => 0, 'credits' => 0, 'characters' => 0, 'usd' => 0.0];
$talkModel = 0.0;
$talkSpeech = 0.0;
$talkTurnsTotal = 0.0;
foreach ($turns as $t) {
    if ($t['audio_voice_key'] !== null) {
        $talkVoice['lines']++;
        $talkVoice['credits'] += (int) $t['audio_credits'];
        $talkVoice['characters'] += (int) $t['audio_characters'];
        $talkVoice['usd'] += (float) $t['audio_cost_usd'];
    }
    $talkModel += (float) $t['model_cost_usd'];
    $talkSpeech += (float) $t['speech_cost_usd'];
    $talkTurnsTotal += (float) $t['cost_usd'];
}
$talkCheck = $conversation === null ? null : [
    'conversation_cost_usd' => $money($conversation['cost_usd']),
    'turns_model_usd' => $money($talkModel), 'turns_voice_usd' => $money($talkVoice['usd']), 'turns_speech_usd' => $money($talkSpeech), 'turns_cost_usd' => $money($talkTurnsTotal),
    // Six places after the point on both sides: a difference under a millionth of a dollar is rounding, not money.
    'agrees' => abs((float) $conversation['cost_usd'] - ($talkModel + $talkVoice['usd'])) < 0.000002 && abs($talkSpeech - $talkVoice['usd']) < 0.000002,
];
$dayScene = null;
$otherLessons = 0.0;
foreach ($scenes as $s) {
    if (($s['id'] ?? null) === ($state['create']['day1']['scene_id'] ?? null)) {
        $dayScene = $s;
    } else {
        $otherLessons += (float) ($s['cost_usd_lesson'] ?? 0);
    }
}
// The slot judge's price, exactly: each ruling carries its own call (`response.judge.cost_usd`, null when the code
// ruled). The journal's `judge` calls in the walk's window are only a cross-check — the seam judge of any lesson built on
// the same base in that window is journaled under the same purpose.
$slotJudge = array_sum(array_map(static fn (array $j): float => (float) ($j['cost_usd'] ?? 0), $state['walk']['judged'] ?? []));
$bill = [
    'voice' => [
        'day1_lines' => $voiceDay['total'],
        'talk' => $talkVoice,
        'total' => [
            'credits' => $voiceDay['total']['credits'] + $talkVoice['credits'],
            'characters' => $voiceDay['total']['characters'] + $talkVoice['characters'],
            'usd' => $money($voiceDay['total']['usd'] + $talkVoice['usd']),
        ],
    ],
    'models' => [
        'plan' => $money($planRow['cost_usd_plan'] ?? 0),
        'lesson_day1' => $money($dayScene['cost_usd_lesson'] ?? 0),
        'lessons_other_scenes' => $money($otherLessons),
        'slot_judge' => $money($slotJudge),
        'talk' => $money($talkModel),
    ],
];
$bill['models']['total'] = $money(array_sum($bill['models']));
$bill['cross_checks'] = [
    'talk' => $talkCheck,
    'judge_calls_in_walk_window_usd' => $money(array_sum(array_map(static fn (array $c): float => (float) $c['cost_usd'], $judgeCalls))),
];

$report = [
    'pair' => $pair, 'native' => $native, 'target' => $target, 'database' => $database, 'email' => $state['email'], 'user_id' => $state['user_id'],
    'plan_id' => $planId, 'goal' => $state['goal'], 'dumped_at' => nowIso(),
    'server' => $state['server'] ?? null,
    'create' => $state['create'] ?? null,
    'walk' => $state['walk'] ?? null,
    'talk' => isset($state['talk']) ? array_diff_key($state['talk'], ['documents' => true]) : null,
    'talk_documents' => $state['talk']['documents'] ?? [],
    'plan' => $plan,
    'room' => $room,
    'sql' => [
        'plan' => $planRow, 'scenes' => $scenes, 'line_audios' => ['rows' => $audios, 'summary' => $voiceDay],
        'conversation' => $conversation, 'turns' => $turns, 'rejections' => $rejections, 'slot_judge_calls' => $judgeCalls,
    ],
    'bill' => $bill,
];
file_put_contents("{$out}/{$pair}.json", json_encode($report, JSON_OUT)."\n");

// ── the page for a person ────────────────────────────────────────────────────────────────────────────────────────────
// A text of the model or of the learner on one line of Markdown: every run of whitespace (a CR, an LF, a tab) one space.
$inline = static fn (mixed $v): string => trim((string) preg_replace('/\s+/u', ' ', is_scalar($v) ? (string) $v : (string) json_encode($v, JSON_UNESCAPED_UNICODE)));
// …and inside a table cell, a pipe escaped as GFM reads it (`\|`), an empty value a dash.
$cell = static fn (mixed $v): string => $v === null || $inline($v) === '' ? '—' : str_replace('|', '\|', $inline($v));
$usd = static fn (float|int|string|null $v): string => '$'.number_format((float) ($v ?? 0), 4, '.', '');
$c = $state['create'] ?? [];
$sceneRow = $c['day1']['scene_row'] ?? [];
$delta = $c['pack_missing_delta'] ?? [];
$gained = array_sum(array_column($delta, 'delta'));
$md = [];
$md[] = "# LANG-1 · живой день · {$pair}";
$md[] = '';
$md[] = "Ученик `{$state['email']}` · план `{$planId}` · база `{$database}` · выгружено {$report['dumped_at']}";
$md[] = '';
$md[] = "Цель (на родном): «{$state['goal']}» · уровень ".LEVEL.' · дней '.DAYS_TOTAL;
$md[] = '';
foreach ($state['server'] ?? [] as $ph => $sv) {
    $md[] = sprintf('- сервер фазы %s: `%s` · база `%s` · очередь %s · модель %s · голос %s · фото %s', $ph, $sv['docroot'] ?? '?', $sv['DB_DATABASE'] ?? '?',
        $sv['QUEUE_CONNECTION'] ?? '?', $sv['PLAN_MODEL_DRIVER'] ?? '?', ($sv['SPEECH_ENABLED'] ?? false) ? 'вкл' : 'выкл', $sv['IMAGE_DRIVER'] ?? '?');
}
$md[] = '';
$md[] = '### Сборка';
$md[] = '';
$md[] = sprintf(
    'План **%s** за %s с (%s, %s, попыток %s) · день 1 «%s» — **%s**%s за %s с от POST (урок `%s`, модель %s, попыток %s) · `lang.pack_missing` %s%s.',
    $c['build']['status'] ?? '—', $c['plan_seconds'] ?? '—', $planRow['prompt_version_plan'] ?? '—', $planRow['model_plan'] ?? '—', $planRow['attempts_plan'] ?? '—',
    $dayScene['title_native'] ?? '—', $c['day1']['lesson_status'] ?? '—',
    ($c['day1']['fail_reason'] ?? null) === null ? '' : ' (`'.$c['day1']['fail_reason'].'`)', $c['day1_seconds'] ?? '—',
    $sceneRow['prompt_version_lesson'] ?? '—', $sceneRow['model_lesson'] ?? '—', $sceneRow['attempts_lesson'] ?? '—',
    $gained === 0 ? '+0 (счётчики не сдвинулись)' : '**+'.$gained.'**',
    ($c['other_builds_in_window'] ?? []) === [] ? '' : ' — в окне сборки в той же базе шли ещё '.count($c['other_builds_in_window']).' чужих уроков, дельта не только наша',
);
$md[] = '';
foreach ($delta as $d) {
    $md[] = "- `lang.pack_missing` · {$d['prompt_version']} · {$d['action']}: {$d['before']} → {$d['after']} ({$d['delta']})";
}
if ($delta === []) {
    $md[] = '- `lang.pack_missing`: строк счётчика нет ни до, ни после';
}
$md[] = '- находки урока (`checks_json`): '.(($dayScene['checks'] ?? []) === [] ? 'нет' : implode(', ', array_map(static fn (array $f): string => '`'.$f['code'].'`'.($f['address'] === null ? '' : ' @'.$f['address']), $dayScene['checks'])));
$md[] = '- сцены: '.implode(' · ', array_map(static fn (array $s): string => "{$s['order']}. «{$s['title_native']}» / «{$s['title_target']}» — {$s['lesson_status']}".($s['partner_voice_id'] === null ? '' : ", голос роли {$s['partner_voice_id']} ({$s['partner_voice_gender']})"), $scenes));
$md[] = '';

$md[] = '### Проход дня 1';
$md[] = '';
$w = $state['walk'] ?? null;
if ($w === null) {
    $md[] = 'Не проходился.';
} else {
    $groups = [];
    foreach ($w['responses'] as $r) {
        $key = $r['stage'].'|'.$r['kind'];
        $groups[$key] ??= ['stage' => $r['stage'], 'kind' => $r['kind'], 'results' => []];
        $label = $r['sent'] === 'judge' ? 'судья→'.($r['result'] ?? 'не зачтено') : ($r['status'] === 200 ? (string) $r['result'] : $r['sent'].'→'.$r['status'].($r['code'] === null ? '' : ' '.$r['code']));
        $groups[$key]['results'][$label] = ($groups[$key]['results'][$label] ?? 0) + 1;
    }
    $md[] = '| этап | вид | ответы |';
    $md[] = '|---|---|---|';
    foreach ($groups as $g) {
        $md[] = '| '.$g['stage'].' | `'.$g['kind'].'` | '.implode(', ', array_map(static fn (string $k, int $n): string => "{$k} ×{$n}", array_keys($g['results']), $g['results'])).' |';
    }
    $md[] = '';
    $md[] = 'Закрытие этапов: '.implode(' · ', array_map(static fn (array $x): string => $x['stage'].' → '.$x['status'].($x['code'] === null ? '' : ' '.$x['code']), $w['closes'])).'.';
    $md[] = 'Окно после прохода: '.implode(' · ', array_map(static fn (array $s): string => $s['stage'].' '.$s['state'], $w['window_after'] ?? [])).
        ' · неотвеченных карточек '.($w['cards_left'] ?? '?').'.';
    $md[] = '';
    $md[] = 'Судья окна (`speak_answer`; `literal` — своя строка обмена, её знакомое наполнение судит КОД; `forced` — та же строка с наполнением чужого каркаса, её окно судит МОДЕЛЬ):';
    $md[] = '';
    foreach ($w['judged'] as $j) {
        $by = match ($j['by'] ?? null) {
            'code' => 'решил код',
            'model' => 'решила модель'.(($j['model'] ?? null) === null ? '' : ' '.$j['model']).' · '.$usd($j['cost_usd'] ?? 0),
            'unavailable' => 'модель не ответила — зачтено по коду',
            default => 'кто решил — неизвестно',
        };
        $md[] = sprintf('- %s [%s] «%s» → %s%s%s · %s', $j['exchange'] ?? '?', $j['how'] ?? 'literal', $inline($j['heard']),
            $j['status'] !== 200 ? $j['status'].' '.$j['code'] : (($j['accepted'] ?? false) ? 'зачтено' : 'не зачтено'),
            ($j['slot_value'] ?? null) === null ? '' : ', окно «'.$inline($j['slot_value']).'»', ($j['reason_native'] ?? null) === null ? '' : ' — «'.$inline($j['reason_native']).'»', $by);
    }
    if ($w['judged'] === []) {
        $md[] = '- не звался (в дне нет `speak_answer`)';
    }
}
$md[] = '';

$md[] = '### Разговор';
$md[] = '';
if ($conversation === null) {
    $md[] = 'Не начинался.';
} else {
    $s = $state['talk']['summary'] ?? null;
    $talkRow = null;
    foreach ($room['window']['stages'] ?? [] as $row) {
        if (($row['stage'] ?? null) === 'conversation') {
            $talkRow = $row['state'] ?? null;
        }
    }
    $md[] = sprintf('«%s» · %s · подсказки %s · ходов %s · конец **%s**%s%s · ряд разговора в окне — %s.', $inline($state['talk']['start']['talk_title_native'] ?? '—'), $conversation['state'], $conversation['hints_enabled'] ? 'да' : 'нет',
        $conversation['turn_limit'], $conversation['ended_reason'] ?? '—', ($s['ended_by_limit'] ?? false) ? ' (по лимиту)' : '',
        $s === null ? '' : " · целей {$s['phrases_used']} из {$s['phrases_total']} · сказал сам {$s['said_count']} · понял все: ".($s['understood_all'] ? 'да' : 'нет')." · минут {$s['minutes']}",
        $talkRow ?? 'нет');
    $md[] = '';
    $error = $state['talk']['error'] ?? null;
    if ($error !== null) {
        $md[] = sprintf('**Разговор оборвался:** ход %d (%s «%s») → %d `%s`: %s', $error['move'], $error['sent']['kind'], $inline($error['sent']['heard']) === '' ? '—' : $inline($error['sent']['heard']), $error['status'],
            str_replace('`', "'", (string) ($error['exception'] ?? $error['code'] ?? '—')), $inline($error['message']));
        $md[] = '';
    }
    $sources = [];
    foreach ($state['talk']['moves'] ?? [] as $mv) {
        if (($mv['sent']['heard'] ?? null) !== null) {
            $sources[] = $mv['sent']['source'];
        }
    }
    $md[] = '| # | кто | реплика (цель) | перевод (родной) | голос | кредиты |';
    $md[] = '|---|---|---|---|---|---|';
    foreach ($turns as $t) {
        $who = $t['speaker'] === 'learner' ? 'ученик · '.$t['kind'] : 'роль'.($t['scene_event'] === null ? '' : ' · '.$t['scene_event']);
        $said = $t['phrases_used'] === [] ? '' : ' ✓'.implode(',', array_map(static fn (mixed $p): string => is_array($p) ? (string) ($p['ref'] ?? '') : (string) preg_replace('/^.*:/', '', (string) $p), $t['phrases_used']));
        $md[] = '| '.$t['turn_index'].' | '.$who.' | '.$cell($t['text_target']).$said.' | '.$cell($t['speaker'] === 'learner' ? null : $t['text_native']).' | '.$cell($t['audio_voice_key']).' | '.$cell($t['audio_credits']).' |';
    }
    $md[] = '';
    $md[] = 'Откуда реплики ученика: '.implode('; ', array_map(static fn (string $k, int $n): string => "{$k} ×{$n}", array_keys(array_count_values($sources)), array_count_values($sources))).'.';
}
$md[] = '';

$md[] = '### Отказы сторожей роли';
$md[] = '';
$translationRefusals = count(array_filter($rejections, static fn (array $r): bool => $r['reason'] === 'native_missing'));
if ($rejections === []) {
    $md[] = 'Нет — ни одного отказа (`conversation_rejections` пуст). Страж перевода (`native_missing`): 0.';
} else {
    $reasons = array_count_values(array_map(static fn (array $r): string => $r['kind'].'/'.$r['reason'], $rejections));
    $md[] = 'Всего '.count($rejections).': '.implode(', ', array_map(static fn (string $k, int $n): string => "`{$k}` ×{$n}", array_keys($reasons), $reasons)).
        ". **Страж перевода (`native_missing`): {$translationRefusals}** — ниже у каждого отказа ответ, который он не пустил: перевод настоящий — отказ ложный.";
    $md[] = '';
    foreach ($rejections as $r) {
        $a = $r['refused_answer'];
        $guard = $r['reason'] === 'native_missing' ? 'страж перевода' : ($r['kind'] === 'dropped_opening' ? 'открытие' : 'страж реплики');
        $md[] = sprintf('- ход %d · попытка %d · %s · `%s` / `%s`%s', $r['turn_index'], $r['attempt'], $guard, $r['kind'], $r['reason'],
            $r['detail'] === [] ? '' : ' · '.json_encode($r['detail'], JSON_UNESCAPED_UNICODE));
        if ($a === null) {
            $md[] = '  - отказанный ответ не прочитан: у отказа нет model_call_id';
        } elseif ($a['found']) {
            $md[] = '  - отказанный ответ: «'.$inline($a['reply_target'] ?? '—').'» / reply_native «'.$inline($a['reply_native'] ?? '—').'»'.
                sprintf(' (журнал: %s, расхождение %d мс, кандидатов %d)', $a['matched_by'], $a['distance_ms'], $a['candidates']);
        } else {
            $md[] = '  - отказанный ответ не прочитан: '.$a['why'];
        }
    }
}
$md[] = '';

$md[] = '### Деньги';
$md[] = '';
$v = $bill['voice'];
$md[] = '| что | кредиты · символы · $ |';
$md[] = '|---|---|';
$md[] = sprintf('| голос дня 1 (%d строк) | %d · %d · %s |', $v['day1_lines']['lines'], $v['day1_lines']['credits'], $v['day1_lines']['characters'], $usd($v['day1_lines']['usd']));
foreach ($voiceDay['by_kind'] as $k => $x) {
    $md[] = sprintf('| — %s (%d) | %d · %d · %s |', $k, $x['lines'], $x['credits'], $x['characters'], $usd($x['usd']));
}
foreach ($voiceDay['by_voice'] as $k => $x) {
    $md[] = sprintf('| — голос `%s` (%d) | %d · %d · %s |', $k, $x['lines'], $x['credits'], $x['characters'], $usd($x['usd']));
}
$md[] = sprintf('| голос разговора (%d реплик) | %d · %d · %s |', $v['talk']['lines'], $v['talk']['credits'], $v['talk']['characters'], $usd($v['talk']['usd']));
$md[] = sprintf('| **голос всего** | **%d · %d · %s** |', $v['total']['credits'], $v['total']['characters'], $usd($v['total']['usd']));
$md[] = '';
$md[] = '| модели | $ |';
$md[] = '|---|---|';
$md[] = '| план | '.$usd($bill['models']['plan']).' |';
$md[] = '| урок дня 1 (с починками и судьёй швов) | '.$usd($bill['models']['lesson_day1']).' |';
if ($bill['models']['lessons_other_scenes'] > 0) {
    $md[] = '| уроки других сцен плана | '.$usd($bill['models']['lessons_other_scenes']).' |';
}
$modelRulings = count(array_filter($state['walk']['judged'] ?? [], static fn (array $j): bool => ($j['by'] ?? null) === 'model'));
$md[] = '| судья окна ('.$modelRulings.' решений модели из '.count($state['walk']['judged'] ?? []).', цена из `response.judge` карточек) | '.$usd($bill['models']['slot_judge']).' |';
$md[] = '| разговор (ходы роли, `model_cost_usd` — с отказанными попытками) | '.$usd($bill['models']['talk']).' |';
$md[] = '| **модели всего** | **'.$usd($bill['models']['total']).'** |';
$md[] = '';
if ($talkCheck !== null) {
    // `conversations.cost_usd` is model AND voice: it is shown only against the turns, never added to the bill.
    $md[] = sprintf('Сверка: `conversations.cost_usd` %s = модель %s + голос %s по журналу ходов — %s (в счёт не прибавляется: голос в нём уже есть).',
        $usd($talkCheck['conversation_cost_usd']), $usd($talkCheck['turns_model_usd']), $usd($talkCheck['turns_voice_usd']), $talkCheck['agrees'] ? 'сходится' : '**НЕ сходится**');
}
$md[] = sprintf('Сверка судьи: вызовов `judge` в журнале model_calls за окно прохода — %d на %s (сюда попал бы и судья швов чужой сборки в той же базе).',
    count($judgeCalls), $usd($bill['cross_checks']['judge_calls_in_walk_window_usd']));
$md[] = '';
file_put_contents("{$out}/{$pair}.md", implode("\n", $md));

say("   записано: {$out}/{$pair}.json и {$pair}.md · голос ".$v['total']['credits'].' кр · '.$v['total']['characters'].' симв · '.$usd($v['total']['usd']).' · модели '.$usd($bill['models']['total']).' · отказов сторожей '.count($rejections)." (перевода {$translationRefusals})");
exit(0);

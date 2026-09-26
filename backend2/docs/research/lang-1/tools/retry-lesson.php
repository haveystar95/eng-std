<?php

declare(strict_types=1);

/*
 * LANG-1 part D — THE LEARNER'S «ещё раз» FOR A DAY-1 LESSON THAT DID NOT BUILD.
 *
 *   docker exec -w /wt -e DB_DATABASE=wordtrainer_e2e_test wt_lang1 php docs/research/lang-1/tools/retry-lesson.php ru-de [--port=8012]
 *
 * The phone shows a failed lesson with a retry button (`POST /plans/{id}/scenes/{sceneId}/lesson/retry`, plan-api.md);
 * this is that button, pressed once, over HTTP against the same php -S server live-day.php drives — no manual edit of a
 * lesson, a new build by the production path (with QUEUE_CONNECTION=sync it runs inside the request). It reads the pair's
 * state file written by `live-day.php create`, signs the same QA learner in through /auth/dev, presses retry for the
 * scene of day 1, and prints the lesson's new status. Run `live-day.php create <pair>` again afterwards: it re-reads the
 * build into the state file (it never makes a second plan). Every retry is recorded in `<pair>.retries.json`.
 */

$pair = $argv[1] ?? '';
$port = 8012;
foreach (array_slice($argv, 2) as $arg) {
    if (preg_match('/^--port=(\d+)$/', $arg, $m) === 1) {
        $port = (int) $m[1];
    }
}
$dir = __DIR__.'/../live';
$stateFile = "{$dir}/{$pair}.state.json";
if (! is_file($stateFile)) {
    fwrite(STDERR, "no state for «{$pair}» — run live-day.php create first\n");
    exit(1);
}
$state = json_decode((string) file_get_contents($stateFile), true);
$db = (string) getenv('DB_DATABASE');
if (! in_array($db, ['wordtrainer_e2e_test', 'wordtrainer_lang1_test'], true) || ($state['database'] ?? '') !== $db) {
    fwrite(STDERR, "refusing: DB «{$db}» is not the pair's disposable database\n");
    exit(1);
}
$base = "http://127.0.0.1:{$port}/api/v1";

$call = static function (string $method, string $path, ?array $body, ?string $token, int $timeout) use ($base): array {
    $ch = curl_init($base.$path);
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token !== null) {
        $headers[] = "Authorization: Bearer {$token}";
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_POSTFIELDS => $body === null ? null : json_encode($body, JSON_UNESCAPED_UNICODE),
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    return [$status, is_string($raw) ? json_decode($raw, true) : null, is_string($raw) ? $raw : ''];
};

[$status, $login] = $call('POST', '/auth/dev', ['email' => $state['email'], 'device_name' => 'lang-1', 'timezone' => 'Europe/Kyiv'], null, 60);
$token = is_array($login) ? ($login['token'] ?? null) : null;
if ($status !== 200 || ! is_string($token)) {
    fwrite(STDERR, "dev login failed: {$status}\n");
    exit(1);
}
$planId = (string) $state['plan_id'];
[, $plan] = $call('GET', "/plans/{$planId}", null, $token, 60);
$scenes = $plan['data']['scenes'] ?? [];
$scene = null;
foreach ($scenes as $s) {
    if (($s['order'] ?? null) === 1 || ($s['day'] ?? null) === 1) {
        $scene = $s;
        break;
    }
}
$scene ??= $scenes[0] ?? null;
if (! is_array($scene)) {
    fwrite(STDERR, "no scene of day 1 in the plan\n");
    exit(1);
}
$before = $scene['lesson_status'] ?? null;
$t0 = microtime(true);
echo date('H:i:s')." {$pair} · retry of scene {$scene['id']} (lesson {$before}) …\n";
[$rs, $rb, $raw] = $call('POST', "/plans/{$planId}/scenes/{$scene['id']}/lesson/retry", [], $token, 1800);
$wall = round(microtime(true) - $t0, 1);
[, $after] = $call('GET', "/plans/{$planId}", null, $token, 60);
$now = null;
foreach ($after['data']['scenes'] ?? [] as $s) {
    if (($s['id'] ?? null) === $scene['id']) {
        $now = $s;
    }
}
$row = [
    'at' => gmdate('c'), 'scene_id' => $scene['id'], 'before' => $before, 'http' => $rs, 'wall_s' => $wall,
    'after' => $now['lesson_status'] ?? null, 'fail_reason' => $now['fail_reason'] ?? null,
    'response' => $rs >= 400 ? mb_substr($raw, 0, 600) : null,
];
$retriesFile = "{$dir}/{$pair}.retries.json";
$retries = is_file($retriesFile) ? (array) json_decode((string) file_get_contents($retriesFile), true) : [];
$retries[] = $row;
file_put_contents($retriesFile, json_encode($retries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
echo date('H:i:s')." {$pair} · retry → HTTP {$rs} in {$wall} s · lesson {$row['after']}".($row['fail_reason'] ? " ({$row['fail_reason']})" : '')."\n";
exit(in_array($row['after'], ['ready', 'illustrating'], true) ? 0 : 2);

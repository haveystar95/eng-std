<?php

/**
 * FIX-4 — ПРИЁМКА Б: one live rehearsal on the e2e stand, through the BRANCH's HTTP API — the only purchase of the order
 * (the role's moves on gpt-5.4-mini and their voice). The learner is played by this script, with the lines of the
 * session as the plan's lesson has them (the scene's constructions with their values), one of them said one word off
 * on purpose — to see «almost» and the exact line in the next hint:
 *
 *   «Запись к врачу» (4 targets, 5 moves): T1, T2 with a value of its own, T3 one word off, T3, T4 — its targets said,
 *   the scene closes: the receptionist's goodbye, the doctor's greeting (a man's voice for the run — the owner's decision)
 *   and the first door of «Приём у врача»; there (3 targets, 4 moves): T5, T6 with an extra construction of the scene
 *   beside it (p4 «I gave him ___.»), T7 — the last scene's goodbye ends the talk.
 *
 * The script only talks to the API; the server is the branch's own `php -S` in the sidecar, on the e2e base:
 *
 *   docker exec -d -w /wt -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync -e SPEECH_ENABLED=true \
 *     wt_fix4 php -S 127.0.0.1:8012 -t /wt/public
 *   docker exec -w /wt wt_fix4 php docs/research/fix-4/tools/live-rehearsal.php
 *
 * Output: `../live/live-rehearsal.json` (every answer of the API as it came) and the transcript on stdout.
 */

declare(strict_types=1);

const BASE = 'http://127.0.0.1:8012/api/v1';
const EMAIL = 'qa-gen3-doctor@wt.test';
const PLAN = '01M2QRH5MYEFZ1DEQ78RH54P6X';
const DAY = 3;
/** The learner's moves, in order; the talk is walked until it ends or they run out. */
const MOVES = [
    'It hurts in his lower back.',
    'It started two days ago.',
    'The pain is sharp when she bends.',
    'The pain is sharp when he bends.',
    'He doesn\'t have a fever.',
    'He has a fever and a sore throat.',
    'He\'s been sick for three days. I gave him paracetamol.',
    'Should I tell you his temperature?',
];

/** @return array<string, mixed> */
function call(string $method, string $path, array $body = [], ?string $token = null): array
{
    $curl = curl_init(BASE.$path);
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => array_values(array_filter(['Accept: application/json', 'Content-Type: application/json', $token === null ? null : "Authorization: Bearer {$token}"])),
        CURLOPT_POSTFIELDS => $body === [] ? null : json_encode($body),
    ]);
    $raw = (string) curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    $json = json_decode($raw, true);
    if ($status >= 400 || ! is_array($json)) {
        fwrite(STDERR, "{$method} {$path} → {$status}: ".mb_substr($raw, 0, 600)."\n");
        exit(1);
    }

    return $json;
}

/** The lines of an answer the transcript has not printed yet. */
function newLines(array $talk, int $seen): array
{
    return array_values(array_filter($talk['turns'], static fn (array $t): bool => $t['index'] > $seen));
}

$token = call('POST', '/auth/dev', ['email' => EMAIL])['token'] ?? null;
if (! is_string($token)) {
    fwrite(STDERR, "no token\n");
    exit(1);
}

$answers = [];
$talk = call('POST', '/plans/'.PLAN.'/days/'.DAY.'/conversation', ['again' => true, 'hints' => true], $token)['data'];
$answers[] = ['move' => null, 'talk' => $talk];
$sceneTitle = array_column($talk['scenes'], 'title_native', 'scene_id');
$target = static fn (array $talk, string $scene, string $ref): ?array => array_values(array_filter($talk['targets'], static fn (array $t): bool => $t['scene_id'] === $scene && $t['ref'] === $ref))[0] ?? null;
$print = static function (array $talk, int $seen) use ($sceneTitle): int {
    foreach (newLines($talk, $seen) as $t) {
        $who = $t['speaker'] === 'partner' ? 'РОЛЬ ' : 'УЧЕНИК';
        $event = $t['scene_event'] === null ? '' : ' ['.($t['scene_event'] === 'start' ? 'начало' : 'прощание').' «'.($sceneTitle[$t['scene_id']] ?? '?').'»]';
        $said = $t['phrases_used'] === [] ? '' : ' · сказано: '.implode(', ', array_column($t['phrases_used'], 'ref'));
        $extra = ($t['extra_said'] ?? []) === [] ? '' : ' · ещё вспомнил: '.implode(', ', array_column($t['extra_said'], 'ref'));
        echo sprintf("%2d %s%s %s%s%s\n", $t['index'], $who, $event, $t['text_target'] ?? '—', $said, $extra);
        $seen = max($seen, $t['index']);
    }
    $hint = $talk['hints'];
    if ($talk['state'] !== 'ended') {
        echo sprintf("   подсказка: «Скажи, что %s»%s · цель %s\n", $hint['native'] ?? '—', $hint['target'] === null ? '' : " · точная строка: «{$hint['target']}»", $hint['ref'] ?? '—');
        $states = array_map(static fn (array $g): string => $g['ref'].'='.$g['state'], $talk['targets']);
        echo '   цели: '.implode(' ', $states)."\n";
    }

    return $seen;
};

echo "разговор {$talk['id']} · ходов {$talk['turns_left']}\n";
$seen = $print($talk, 0);
foreach (MOVES as $move) {
    if ($talk['state'] === 'ended') {
        break;
    }
    $talk = call('POST', '/plans/'.PLAN."/conversation/{$talk['id']}/turn", ['kind' => 'said', 'heard' => $move], $token)['data'];
    $answers[] = ['move' => $move, 'talk' => $talk];
    $seen = $print($talk, $seen);
}
echo "\nитог: {$talk['state']}";
if ($talk['summary'] !== null) {
    echo ' · '.json_encode(['ended_reason' => $talk['summary']['ended_reason'], 'ended_by_limit' => $talk['summary']['ended_by_limit'], 'phrases_used' => $talk['summary']['phrases_used'], 'phrases_total' => $talk['summary']['phrases_total'], 'extra_said' => array_column($talk['summary']['extra_said'], 'ref')], JSON_UNESCAPED_UNICODE);
}
echo "\n";

$live = dirname(__DIR__).'/live';
if (! is_dir($live)) {
    mkdir($live, 0775, true);
}
file_put_contents("{$live}/live-rehearsal.json", json_encode($answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
fwrite(STDERR, "wrote live/live-rehearsal.json\n");

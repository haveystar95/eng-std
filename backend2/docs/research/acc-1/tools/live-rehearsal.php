<?php

/**
 * ACC-1 §6 — ЖИВАЯ РЕПЕТИЦИЯ: one live rehearsal on the e2e stand after the deploy, through main's HTTP API — the one
 * purchase of the order (the role's moves on the model under `conversation_agent.v3.4`; the voice is switched off for
 * this run: the farewell is text, and nothing else is bought). The scenario of FIX-4 / FIX-4b / FIX-4c move for move (the
 * learner's lines of the lesson, one said one word off on purpose), in «Без подсказок», as FIX-4c walked it.
 *
 * What the order waits for (наряд ACC-1 §6): on the scene's goodbye after «Should I tell you his temperature?» (move 18 of
 * FIX-4c) the role ACCEPTS — «Yes, please…» — and does not turn it down («No, that's okay»). Around it, read off the same
 * answers: §5 — no `hints.native` in any document; §2 — `days[].lock_reason` on the plan, `access` on `/auth/me`; §3 — the
 * talk row of a day that walked its talk is still there.
 *
 * The script only talks to the API; the server is main's own `php -S` in the e2e sidecar, on the e2e base, the queue
 * synchronous (a job never reaches the production Horizon), no voice, and the replays of a day raised for this process
 * only — the day's three replays of 25.09 were spent by CLIENT-FIX-4 and FIX-4c:
 *
 *   docker exec -d -w /app -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync -e SPEECH_ENABLED=false \
 *     -e PLAN_CONVERSATION_REPLAYS_PER_DAY=10 wt_app_e2e php -S 127.0.0.1:8012 -t /app/public
 *   docker exec -w /app wt_app_e2e php docs/research/acc-1/tools/live-rehearsal.php
 *
 * Output: `../live/live-rehearsal.json` (every answer of the API as it came) and the transcript on stdout.
 */

declare(strict_types=1);

const BASE = 'http://127.0.0.1:8012/api/v1';
const EMAIL = 'qa-gen3-doctor@wt.test';
const PLAN = '01M2QRH5MYEFZ1DEQ78RH54P6X';
const DAY = 3;
const TITLE = 'Поговори с регистратором и врачом';
/** The learner's moves, in order (FIX-4's, word for word); the talk is walked until it ends or they run out. */
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

/** A hint the phone can show on «Подсказать»: a sentence and the target it belongs to. */
function hintThere(array $hints): bool
{
    return is_string($hints['sentence'] ?? null) && trim($hints['sentence']) !== '' && is_string($hints['ref'] ?? null) && is_string($hints['scene_id'] ?? null);
}

$token = call('POST', '/auth/dev', ['email' => EMAIL])['token'] ?? null;
if (! is_string($token)) {
    fwrite(STDERR, "no token\n");
    exit(1);
}
$checks = [];

// §2 — the plan's days carry `lock_reason`; `/auth/me` carries `access`. §3 — the talk row of a day that walked its talk.
$plan = call('GET', '/plans/'.PLAN, [], $token)['data'];
$me = call('GET', '/auth/me', [], $token)['data'];
echo "план · дни:\n";
foreach ($plan['days'] as $d) {
    // `??` would print a null `lock_reason` (the day is not locked) as a missing field — the first run did exactly that.
    $lock = array_key_exists('lock_reason', $d) ? json_encode($d['lock_reason']) : 'нет поля';
    echo sprintf("   день %d · %s · lock_reason %s · этапы: %s\n", $d['number'], $d['status'], $lock, implode(' ', array_map(static fn (array $s): string => $s['stage'].':'.$s['state'], $d['stages'])));
}
echo '   /auth/me access: '.json_encode($me['access'] ?? null, JSON_UNESCAPED_UNICODE)."\n";
$checks['§2 у каждого дня есть lock_reason (date | subscription | null)'] = count(array_filter($plan['days'], static fn (array $d): bool => array_key_exists('lock_reason', $d) && in_array($d['lock_reason'], [null, 'date', 'subscription'], true))) === count($plan['days']);
$checks['§2 /auth/me → access {plan, expires_at, source}'] = is_array($me['access'] ?? null) && in_array($me['access']['plan'], ['free', 'premium'], true);
$room = call('GET', '/plans/'.PLAN.'/days/'.DAY, [], $token)['data'];
$rows = array_values(array_filter($room['window']['stages'], static fn (array $r): bool => $r['stage'] === 'conversation'));
echo '   окно дня '.DAY.': ряд разговора — '.($rows === [] ? 'нет' : '«'.$rows[0]['talk_title_native'].'» · '.$rows[0]['state'])."\n";
$checks['§3 у дня '.DAY.' ряд разговора на месте'] = count($rows) === 1;

// The talk, in «Без подсказок».
$answers = [['move' => 'GET plan', 'days' => $plan['days']], ['move' => 'GET /auth/me', 'access' => $me['access'] ?? null], ['move' => 'GET day', 'room_window' => $room['window']]];
$talk = call('POST', '/plans/'.PLAN.'/days/'.DAY.'/conversation', ['again' => true, 'hints' => false], $token)['data'];
$answers[] = ['move' => null, 'talk' => $talk];
$sceneTitle = array_column($talk['scenes'], 'title_native', 'scene_id');
$hinted = [];
$print = static function (array $talk, int $seen) use ($sceneTitle, &$hinted): int {
    foreach (newLines($talk, $seen) as $t) {
        $who = $t['speaker'] === 'partner' ? 'РОЛЬ ' : 'УЧЕНИК';
        $event = $t['scene_event'] === null ? '' : ' ['.($t['scene_event'] === 'start' ? 'начало' : 'прощание').' «'.($sceneTitle[$t['scene_id']] ?? '?').'»]';
        $said = $t['phrases_used'] === [] ? '' : ' · сказано: '.implode(', ', array_column($t['phrases_used'], 'ref'));
        $extra = ($t['extra_said'] ?? []) === [] ? '' : ' · ещё вспомнил: '.implode(', ', array_column($t['extra_said'], 'ref'));
        echo sprintf("%2d %s%s %s%s%s\n", $t['index'], $who, $event, $t['text_target'] ?? '—', $said, $extra);
        if ($t['speaker'] === 'partner') {
            echo '         '.($t['text_native'] === '' ? '(без перевода)' : $t['text_native'])."\n";
        }
        $seen = max($seen, $t['index']);
    }
    $hint = $talk['hints'];
    if ($talk['state'] !== 'ended') {
        $hinted[] = ['enabled' => $hint['enabled'], 'there' => hintThere($hint)];
        echo sprintf(
            "   hints: enabled %s · «%s»%s · %s/%s\n",
            $hint['enabled'] ? 'true' : 'false', $hint['sentence'] ?? '—', $hint['target'] === null ? '' : " · точная строка «{$hint['target']}»",
            $hint['scene_id'] === null ? '—' : ($sceneTitle[$hint['scene_id']] ?? '?'), $hint['ref'] ?? '—',
        );
    }

    return $seen;
};

echo "\nразговор {$talk['id']} · «{$talk['talk_title_native']}» · ходов {$talk['turns_left']}\n";
$seen = $print($talk, 0);
foreach (MOVES as $i => $move) {
    if ($talk['state'] === 'ended') {
        break;
    }
    $talk = call('POST', '/plans/'.PLAN."/conversation/{$talk['id']}/turn", ['kind' => 'said', 'heard' => $move], $token)['data'];
    $answers[] = ['move' => $move, 'talk' => $talk];
    $seen = $print($talk, $seen);
    if ($i === 1) {
        // §2 by GET: the document as a phone reads it back.
        $doc = call('GET', '/plans/'.PLAN."/conversation/{$talk['id']}", [], $token)['data'];
        $answers[] = ['move' => 'GET conversation', 'talk' => $doc];
        $checks['§5 GET документа: в hints нет native'] = ! array_key_exists('native', $doc['hints']);
        echo sprintf("   GET документа: enabled %s · «%s» · %s\n", $doc['hints']['enabled'] ? 'true' : 'false', $doc['hints']['sentence'] ?? '—', $doc['hints']['ref'] ?? '—');
    }
}
echo "\nитог: {$talk['state']}";
if ($talk['summary'] !== null) {
    echo ' · '.json_encode(['ended_reason' => $talk['summary']['ended_reason'], 'ended_by_limit' => $talk['summary']['ended_by_limit'], 'phrases_used' => $talk['summary']['phrases_used'], 'phrases_total' => $talk['summary']['phrases_total'], 'extra_said' => array_column($talk['summary']['extra_said'], 'ref')], JSON_UNESCAPED_UNICODE);
}
$checks['§5 ни в одном документе нет hints.native'] = count(array_filter($answers, static fn (array $a): bool => isset($a['talk']) && array_key_exists('native', $a['talk']['hints']))) === 0;
// §6 — every scene's goodbye, and above all the one after «Should I tell you his temperature?».
$turns = $talk['turns'];
$goodbyes = [];
foreach ($turns as $i => $t) {
    if ($t['speaker'] === 'partner' && $t['scene_event'] === 'end') {
        $before = null;
        for ($j = $i - 1; $j >= 0; $j--) {
            if ($turns[$j]['speaker'] === 'learner') {
                $before = $turns[$j]['text_target'];
                break;
            }
        }
        $goodbyes[] = ['index' => $t['index'], 'after' => $before, 'reply' => $t['text_target'], 'native' => $t['text_native']];
    }
}
echo "\n\nпрощания сцен:\n";
foreach ($goodbyes as $g) {
    echo sprintf("   ход %d · после «%s»\n      %s\n      %s\n", $g['index'], $g['after'] ?? '—', $g['reply'], $g['native']);
}
$accepts = static fn (string $reply): bool => preg_match('/^\s*(no\b|not\b|nope\b)/i', $reply) !== 1
    && preg_match('/\b(yes|please|sure|of course|thank|thanks|great|good|okay|ok)\b/i', $reply) === 1;
$temperature = array_values(array_filter($goodbyes, static fn (array $g): bool => stripos((string) $g['after'], 'temperature') !== false));
$checks['§6 прощание после «Should I tell you his temperature?» принимает, не отказывает'] = $temperature !== [] && $accepts($temperature[0]['reply']);
$checks['§6 каждое прощание сцены принимает сказанное или благодарит'] = $goodbyes !== [] && count(array_filter($goodbyes, static fn (array $g): bool => $accepts($g['reply']))) === count($goodbyes);
$answers[] = ['move' => 'goodbyes', 'goodbyes' => $goodbyes];

echo "\n\nпроверки:\n";
foreach ($checks as $name => $ok) {
    echo ($ok ? ' ✅ ' : ' ❌ ').$name."\n";
}

$live = dirname(__DIR__).'/live';
if (! is_dir($live)) {
    mkdir($live, 0775, true);
}
file_put_contents("{$live}/live-rehearsal.json", json_encode($answers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
fwrite(STDERR, "wrote live/live-rehearsal.json\n");

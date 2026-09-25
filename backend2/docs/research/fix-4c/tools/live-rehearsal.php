<?php

/**
 * FIX-4c — ЖИВАЯ РЕПЕТИЦИЯ: one live rehearsal on the e2e stand after the deploy, through main's HTTP API — the second
 * purchase of the order (the role's moves on the model under `conversation_agent.v3.3` and their voice). The scenario of
 * FIX-4 / FIX-4b move for move (the learner's lines of the lesson, one said one word off on purpose), walked this time in
 * «Без подсказок» — so that every document of the talk shows whether the hint travels in that mode too (§2).
 *
 * What the order waits for (наряд FIX-4c, приёмка):
 *   §1 — the registrar's lines in voice F1, the doctor's in F2 (read off `conversation_turns.audio_voice_key` by the
 *        caller: the wire says only `voice: partner`);
 *   §2 — `hints` present while `hints.enabled` is false — on every answer of the moves and on a GET of the document;
 *   §3 — `window.sources[].partner_gender` for both scenes of the day;
 *   §4 — `talk_title_native` «Поговори с регистратором и врачом» on the window's talk row and on the talk;
 *   §6 — every line of the role with its translation in the learner's language (a refusal `native_missing`, if any, is
 *        read off `conversation_rejections` by the caller).
 *
 * The script only talks to the API; the server is main's own `php -S` in the e2e sidecar, on the e2e base, the queue
 * synchronous (a job never reaches the production Horizon):
 *
 *   docker exec -d -w /app -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync -e SPEECH_ENABLED=true \
 *     wt_app_e2e php -S 127.0.0.1:8012 -t /app/public
 *   docker exec -w /app wt_app_e2e php docs/research/fix-4c/tools/live-rehearsal.php
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

// §3, §4 — the day window before the talk.
$room = call('GET', '/plans/'.PLAN.'/days/'.DAY, [], $token)['data'];
$sources = $room['window']['sources'];
echo "окно дня 3 · sources:\n";
foreach ($sources as $s) {
    echo sprintf("   %s «%s» · partner_gender %s\n", $s['scene_id'], $s['title_native'], $s['partner_gender'] ?? '—');
}
$rows = array_values(array_filter($room['window']['stages'], static fn (array $r): bool => ($r['talk_title_native'] ?? null) !== null));
$rowTitle = $rows[0]['talk_title_native'] ?? null;
echo "   ряд разговора: «".($rowTitle ?? '—')."»\n";
$checks['§3 sources[].partner_gender у обеих сцен'] = count($sources) === 2 && count(array_filter($sources, static fn (array $s): bool => in_array($s['partner_gender'] ?? null, ['male', 'female'], true))) === 2;
$checks['§4 ряд окна — «'.TITLE.'»'] = $rowTitle === TITLE;

// The talk, in «Без подсказок».
$answers = [['move' => 'GET day', 'room_window' => $room['window']]];
$talk = call('POST', '/plans/'.PLAN.'/days/'.DAY.'/conversation', ['again' => true, 'hints' => false], $token)['data'];
$answers[] = ['move' => null, 'talk' => $talk];
$checks['§4 заголовок разговора — «'.TITLE.'»'] = $talk['talk_title_native'] === TITLE;
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
        $checks['§2 GET документа: hints при enabled=false'] = $doc['hints']['enabled'] === false && hintThere($doc['hints']);
        echo sprintf("   GET документа: enabled %s · «%s» · %s\n", $doc['hints']['enabled'] ? 'true' : 'false', $doc['hints']['sentence'] ?? '—', $doc['hints']['ref'] ?? '—');
    }
}
echo "\nитог: {$talk['state']}";
if ($talk['summary'] !== null) {
    echo ' · '.json_encode(['ended_reason' => $talk['summary']['ended_reason'], 'ended_by_limit' => $talk['summary']['ended_by_limit'], 'phrases_used' => $talk['summary']['phrases_used'], 'phrases_total' => $talk['summary']['phrases_total'], 'extra_said' => array_column($talk['summary']['extra_said'], 'ref')], JSON_UNESCAPED_UNICODE);
}
$checks['§2 hints на каждом ходе при enabled=false'] = $hinted !== [] && count(array_filter($hinted, static fn (array $h): bool => $h['enabled'] === false && $h['there'])) === count($hinted);
$partner = array_values(array_filter($talk['turns'], static fn (array $t): bool => $t['speaker'] === 'partner'));
$checks['§6 у каждой реплики роли перевод на родном'] = count(array_filter($partner, static fn (array $t): bool => is_string($t['text_native']) && preg_match('/\p{Cyrillic}/u', $t['text_native']) === 1)) === count($partner);

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

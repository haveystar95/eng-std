<?php

/**
 * GYM-DUMP-2 — the documents the phone was given, rebuilt by the application's own read path, read-only.
 *
 * What it rebuilds, for the owner's plan «Тренировка в зале» (`01M32DX8QCABM348XP45Z1ZD4M`):
 *   - `GET /plans/{id}/days/{1,2}` — the day room with its `window` (stages, minutes, highlights of 30-7, targets)
 *     and every dealt card as the client reads it: `raw/room-day-{1,2}.json`;
 *   - `GET /plans/{id}/conversation/{talk}` — the talk as one document (turns, targets, summary):
 *     `raw/conversation-view-day-{1,2}.json`;
 *   - «Фразы» of each scene through the dealer's own assembler (`DayAssembler::phrasesDeal`) — the ladder's rungs and
 *     what every frame with a window kept: `raw/phrases-deal.json`.
 * and checks the rebuilt room of day 2 and the rebuilt talk of day 2 against what `api_request_logs` kept of the real
 * replies: `raw/views-check.json`.
 *
 * NOTHING IS WRITTEN. It is run with (see README «Инструменты»):
 *   - PGOPTIONS=-c default_transaction_read_only=on, and the session is set READ ONLY again below — any INSERT or
 *     UPDATE the read path might attempt fails instead of landing;
 *   - LOG_CHANNEL=stderr (a log line goes to the terminal, not to storage/logs), CACHE_STORE=array, a queue connection that does not exist (a dispatch would throw), empty vendor
 *     keys, and Http::preventStrayRequests() — no model, no voice, no log line, no cache entry.
 * The clock is frozen at 2026-09-22 12:55:54 UTC — the moment of the phone's last `GET …/days/2`.
 * Output files are written to ../raw by this script's own path; the app's files are not touched.
 *
 * Run: docker exec -e PGOPTIONS='-c default_transaction_read_only=on' -e LOG_CHANNEL=stderr -e CACHE_STORE=array \
 *        -e QUEUE_CONNECTION=gym_dump_none -e OPENAI_API_KEY= -e GEMINI_API_KEY= -e ELEVENLABS_API_KEY= \
 *        -e ANTHROPIC_API_KEY= wt_app php docs/research/gym-day2/tools/views.php
 */

declare(strict_types=1);

use App\Modules\Plan\Application\Query\GetConversation;
use App\Modules\Plan\Application\Query\GetConversationHandler;
use App\Modules\Plan\Application\Query\GetDayRoom;
use App\Modules\Plan\Application\Query\GetDayRoomHandler;
use App\Modules\Plan\Application\Service\DayDealer;
use App\Modules\Plan\Application\Service\PlanAccess;
use App\Modules\Plan\Domain\Assembly\CardDraft;
use App\Modules\Plan\Domain\Assembly\PhraseCards;
use App\Modules\Plan\Domain\Assembly\PhraseSeries;
use App\Modules\Plan\Domain\Assembly\PhrasesStage;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Presentation\Http\PlanJson;
use App\Modules\Observability\Domain\Service\SecretRedactor;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

const PLAN = '01M32DX8QCABM348XP45Z1ZD4M';
const USER = '01M12HTZ1QHPNDZ5J8SPKB58QP';
const SCENES = [1 => '01M32DXHYG50H7SWQEMD33A39F', 2 => '01M32DXHYGYMK0E1DBR01PYDHA'];
const TALKS = [1 => '01M32FJ5FQNRNSQH6PNC7DP5E0', 2 => '01M34HXHP05JY8NVF1305NRY5N'];
const FROZEN_AT = '2026-09-22T12:55:54+00:00';
/** The phone's last `GET …/days/2` and the last move of the day-2 talk, as `api_request_logs` kept them. */
const LOGGED_ROOM_DAY_2 = '01M34JZWD603HWHWH5AKANEV31';
const LOGGED_TALK_DAY_2 = '01M34HZ6NXAMCH9125Q28QYY8G';

require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$raw = dirname(__DIR__).'/raw';

// ── the guards ────────────────────────────────────────────────────────────────────────────────────
DB::connection()->getPdo()->exec('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY');
$session = DB::selectOne("select current_database() as db, current_setting('default_transaction_read_only') as default_ro, current_setting('transaction_read_only') as tx_ro");
if ($session->default_ro !== 'on' || $session->tx_ro !== 'on') {
    fwrite(STDERR, "session is not read-only — stop\n");
    exit(1);
}
foreach (['OPENAI_API_KEY', 'GEMINI_API_KEY', 'ELEVENLABS_API_KEY', 'ANTHROPIC_API_KEY'] as $key) {
    if ((string) env($key) !== '') {
        fwrite(STDERR, "{$key} is set — run with the key emptied\n");
        exit(1);
    }
}
if (config('logging.default') !== 'stderr' || config('cache.default') !== 'array' || config('queue.connections.'.config('queue.default')) !== null) {
    fwrite(STDERR, "log / cache / queue are not switched off — stop\n");
    exit(1);
}
Http::preventStrayRequests();
URL::forceRootUrl('https://greedily-thermos-finer.ngrok-free.dev');
URL::forceScheme('https');
$frozen = new DateTimeImmutable(FROZEN_AT);
$app->instance(Clock::class, new class($frozen) implements Clock
{
    public function __construct(private readonly DateTimeImmutable $at) {}

    public function now(): DateTimeImmutable
    {
        return $this->at;
    }
});

$put = static function (string $name, mixed $data) use ($raw): void {
    file_put_contents("{$raw}/{$name}", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)."\n");
    echo "wrote {$name}\n";
};
/** jsonb reorders keys; compare two decoded documents with every object's keys sorted. */
$canon = static function (mixed $v) use (&$canon): mixed {
    if (! is_array($v)) {
        return $v;
    }
    if (! array_is_list($v)) {
        ksort($v);
    }

    return array_map($canon, $v);
};

$planId = PlanId::fromString(PLAN);
$actor = UserId::fromString(USER);
$check = [
    'database' => $session->db,
    'default_transaction_read_only' => $session->default_ro,
    'transaction_read_only' => $session->tx_ro,
    'clock' => FROZEN_AT,
];

// ── the rooms ─────────────────────────────────────────────────────────────────────────────────────
$rooms = app(GetDayRoomHandler::class);
foreach ([1, 2] as $n) {
    $reply = ['data' => PlanJson::room($rooms(new GetDayRoom($planId, $n, $actor)))];
    $put("room-day-{$n}.json", $reply);

    if ($n === 2) {
        // What the phone got (`response()->json(…, JSON_PRESERVE_ZERO_FRACTION)`) and what the log kept of it:
        // the body decoded, passed through the log's SecretRedactor (`tokens_in`/`tokens_out` of a judge block become
        // «[REDACTED]»), re-encoded with JSON_UNESCAPED_UNICODE and cut to a head slice over the cap.
        $wire = json_encode($reply, JSON_PRESERVE_ZERO_FRACTION);
        $logged = json_encode((new SecretRedactor)->redact((array) json_decode((string) $wire, true)), JSON_UNESCAPED_UNICODE);
        $row = DB::selectOne('select response_bytes, response_body from api_request_logs where id = ?', [LOGGED_ROOM_DAY_2]);
        $body = json_decode((string) $row->response_body, true);
        $check['room_day_2'] = [
            'logged_request' => LOGGED_ROOM_DAY_2,
            'wire_bytes_logged' => (int) $row->response_bytes,
            'wire_bytes_rebuilt' => strlen((string) $wire),
            'body_bytes_logged' => $body['bytes'] ?? null,
            'body_bytes_rebuilt' => strlen((string) $logged),
            'preview_bytes' => strlen((string) ($body['preview'] ?? '')),
            'preview_is_prefix_of_rebuilt' => str_starts_with((string) $logged, (string) ($body['preview'] ?? "\0")),
        ];
    }
}

// ── the talks ─────────────────────────────────────────────────────────────────────────────────────
$talks = app(GetConversationHandler::class);
foreach (TALKS as $n => $id) {
    $reply = ['data' => PlanJson::conversation($talks(new GetConversation(ConversationId::fromString($id), $actor)))];
    $put("conversation-view-day-{$n}.json", $reply);

    if ($n === 2) {
        $row = DB::selectOne('select response_body from api_request_logs where id = ?', [LOGGED_TALK_DAY_2]);
        $logged = json_decode((string) $row->response_body, true);
        $rebuilt = json_decode((string) json_encode($reply), true);
        $check['talk_day_2'] = [
            'logged_request' => LOGGED_TALK_DAY_2,
            'rebuilt_equals_logged_reply' => $canon($logged) === $canon($rebuilt),
        ];
    }
}

// ── «Фразы» through the dealer's assembler ────────────────────────────────────────────────────────
$plan = app(PlanAccess::class)->owned($planId, $actor);
$dealer = app(DayDealer::class);
$material = new ReflectionMethod($dealer, 'material');
$assembler = (new ReflectionProperty($dealer, 'assembler'))->getValue($dealer);
$deals = [];
foreach (SCENES as $n => $sceneId) {
    $scene = $material->invoke($dealer, $plan, [PlanSceneId::fromString($sceneId)])[$sceneId];
    $deal = $assembler->phrasesDeal($scene, $plan->level());
    $deals["day_{$n}"] = [
        'scene_id' => $sceneId,
        'level' => $plan->level()->value,
        'seconds' => $deal->seconds,
        'budget' => $deal->budget,
        'over_ceiling' => $deal->overCeiling(),
        'rungs' => $deal->rungs,
        'frames' => $deal->frames,
        // What every frame had BEFORE the ladder (rung 0): the value rounds the level allows and the recognitions it is
        // built with — `PhrasesStage::deal()` starts from exactly these.
        'built' => array_values(array_map(static fn ($phrase): array => [
            'ref' => $phrase->ref(),
            'fillers_most' => PhraseSeries::most($scene, $phrase),
            'recognitions' => min(PhrasesStage::RECOGNITIONS, PhraseSeries::most($scene, $phrase)),
            'rounds' => PhraseCards::hasSlot($phrase) ? count(PhraseCards::rounds($scene, $phrase, $plan->level())) : null,
        ], $scene->phrases())),
        'cards' => array_map(static fn (CardDraft $d): array => [
            'kind' => $d->kind->value,
            'unit_ref' => $d->unitRef,
            'rounds' => isset($d->payload['rounds']) ? array_map(static fn (array $r): string => (string) ($r['expected_text'] ?? ''), $d->payload['rounds']) : null,
            'own_round' => isset($d->payload['own_round']),
        ], $deal->drafts),
    ];
}
$put('phrases-deal.json', $deals);

$put('views-check.json', $check);

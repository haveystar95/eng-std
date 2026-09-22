<?php

declare(strict_types=1);

/**
 * FIX-3 §7 · THE LIVE RUN — a day's talk and a rehearsal, led ONE MOVE AT A TIME the way a person leads them: the role
 * says its line, the learner hears it and answers it. Through the same handlers the phone calls, on the e2e stand
 * (`wordtrainer_e2e_test`) with the наряд's code (the sidecar `wt_app_e2e` serves the worktree). Every model call is a
 * real one and is billed; the voice is OFF (`SPEECH_ENABLED=false` — the наряд buys no sound on e2e).
 *
 *   docker exec -e SPEECH_ENABLED=false wt_app_e2e php docs/research/fix-3/tools/live-run.php <command> …
 *
 *   start <plan>                   — «Начать»: a plan made and never started gets its day 1 open
 *   open <plan> <day>              — «Открыть»: the day dealt
 *   walk <plan> <day>              — every card answered as the test walk does (judged — skipped, the rest passed), stages closed
 *   talk <plan> <day> [--again] [--hints=0]
 *                                  — the talk started (or «Ещё раз» of a walked one): the role's first line and the hint
 *   move <conversation> <line>     — one move of the learner: a line, `rescue` or `skip`; the role's answer, the targets said
 *   show <conversation>            — the transcript: every move, the doors, the hints, the targets said, the time and the bill
 *   dump <conversation> [<file>]   — `GET …/conversation/{id}` (its `data`)
 *   room <plan> <day> [<file>]     — `GET …/days/{n}` (its `data`)
 *   bill                           — `model_calls` of this run: calls, tokens and dollars by purpose
 *   snapshot-room <plan> <day> <file>
 *                                  — the fixture of a day just opened: its cards, talks and passages set aside, the day
 *                                    opened by the наряд's code, `GET …/days/{n}` written — all inside a transaction
 *                                    ROLLED BACK: the stand keeps its day as it was
 *   dump-at <conversation> <turn> <file>
 *                                  — the fixture of a talk in the middle: its journal up to that turn, the talk open —
 *                                    inside a transaction rolled back
 *
 * A PERSON'S PACE. The talk's time cap counts the gaps of its journal (each up to a minute): the gaps between a role's
 * line and the learner's answer on the owner's gym talks were 9–20 s, 10 s in the middle (GYM-DUMP-2, `conversation-day-
 * 1/2.json`). The one who runs this harness reads and thinks longer than that, so a move is stamped {@see PERSON_GAP}
 * seconds after the line it answers, and the role's answer after that by the real time it took — the talk's clock is a
 * person's, the model's latency is its own.
 *
 * THE LIMITS OF THE НАРЯД are held here, not by memory: at most {@see MAX_TALKS} talks and {@see CAP_USD} of model calls
 * since {@see SINCE}, the moment this run began; a move that would start past them is refused.
 */

use App\Modules\Plan\Application\Command\AnswerCard;
use App\Modules\Plan\Application\Command\AnswerCardHandler;
use App\Modules\Plan\Application\Command\CloseStage;
use App\Modules\Plan\Application\Command\CloseStageHandler;
use App\Modules\Plan\Application\Command\OpenDay;
use App\Modules\Plan\Application\Command\OpenDayHandler;
use App\Modules\Plan\Application\Command\StartConversation;
use App\Modules\Plan\Application\Command\StartConversationHandler;
use App\Modules\Plan\Application\Command\StartPlan;
use App\Modules\Plan\Application\Command\StartPlanHandler;
use App\Modules\Plan\Application\Command\TakeConversationTurn;
use App\Modules\Plan\Application\Command\TakeConversationTurnHandler;
use App\Modules\Plan\Application\Dto\ConversationView;
use App\Modules\Plan\Application\Query\GetConversation;
use App\Modules\Plan\Application\Query\GetConversationHandler;
use App\Modules\Plan\Application\Query\GetDayRoom;
use App\Modules\Plan\Application\Query\GetDayRoomHandler;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\TurnKind;
use App\Modules\Plan\Presentation\Http\PlanJson;
use App\Modules\Shared\Domain\Service\Clock;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** The moment this run began (UTC): the talks and the dollars of the наряд are counted from it. */
const SINCE = '2026-09-22 15:44:00+00';
const MAX_TALKS = 6;
const CAP_USD = 0.50;
/** Seconds a person takes to answer the role's line — the middle of the owner's gym talks (GYM-DUMP-2). */
const PERSON_GAP = 10;
const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

$database = (string) config('database.connections.pgsql.database');
if ($database !== 'wordtrainer_e2e_test') {
    fwrite(STDERR, "ОТКАЗ: этот харнесс пишет и покупает — только на стенде wordtrainer_e2e_test, а не на «{$database}».\n");
    exit(1);
}
if (config('generation.speech.enabled') === true) {
    fwrite(STDERR, "ОТКАЗ: голос включён — наряд звук на e2e не покупает; запускать с -e SPEECH_ENABLED=false.\n");
    exit(1);
}

function money(float|string $usd): string
{
    return '$'.number_format((float) $usd, 6, '.', '');
}

function actorOf(string $planId): UserId
{
    $userId = DB::table('plans')->where('id', $planId)->value('user_id');
    if (! is_string($userId)) {
        fwrite(STDERR, "нет плана {$planId}\n");
        exit(1);
    }

    return UserId::fromString($userId);
}

function write(mixed $data, ?string $file): void
{
    $json = json_encode($data, JSON_FLAGS | JSON_THROW_ON_ERROR)."\n";
    if ($file === null) {
        echo $json;

        return;
    }
    file_put_contents($file, $json);
    echo "записано: {$file} (".strlen($json)." байт)\n";
}

/** @return array{talks: int, usd: float} what this run has spent so far */
function spent(): array
{
    return [
        'talks' => DB::table('conversations')->where('created_at', '>=', SINCE)->count(),
        'usd' => (float) DB::table('model_calls')->where('started_at', '>=', SINCE)->sum('cost_usd'),
    ];
}

function withinLimits(bool $newTalk): void
{
    $s = spent();
    if ($s['usd'] >= CAP_USD || ($newTalk && $s['talks'] >= MAX_TALKS)) {
        fwrite(STDERR, sprintf("ОТКАЗ: лимит наряда — разговоров %d из %d, потрачено %s из $%.2f\n", $s['talks'], MAX_TALKS, money($s['usd']), CAP_USD));
        exit(1);
    }
}

/** The talk's clock at a person's pace: a move {@see PERSON_GAP} s after the last line of the journal, then real time. */
function pacedClock(string $conversationId): void
{
    $last = DB::table('conversation_turns')->where('conversation_id', $conversationId)->max('created_at');
    $base = (new DateTimeImmutable((string) $last))->modify('+'.PERSON_GAP.' seconds');
    $started = hrtime(true);
    app()->instance(Clock::class, new class($base, $started) implements Clock
    {
        public function __construct(private DateTimeImmutable $base, private int|float $started) {}

        public function now(): DateTimeImmutable
        {
            $ms = (int) round((hrtime(true) - $this->started) / 1_000_000);

            return $this->base->modify("+{$ms} milliseconds");
        }
    });
}

function printView(ConversationView $view): void
{
    echo '  состояние ', $view->state, ' · ходов осталось ', $view->turnsLeft, ' · сцена «', $view->sceneTitleNative, '» · ', $view->partnerRoleNative,
        $view->hintNative === null ? '' : ' · подсказка «'.$view->hintNative.'»', PHP_EOL;
    foreach ($view->targets as $t) {
        echo '    ', $t['said'] ? '✓' : '·', ' ', $t['ref'], ' ', $t['frame_target'], $t['value_target'] === null ? '' : ' ← «'.$t['value_target'].'»', PHP_EOL;
    }
    if ($view->summary !== null) {
        $s = $view->summary;
        echo '  ИТОГ: сказал сам ', $s->saidCount, ' · фразы плана ', $s->phrasesUsed, ' из ', $s->phrasesTotal,
            ' · понял все: ', $s->understoodAll ? 'да' : 'нет ('.$s->notUnderstood.')', ' · переспросов ', $s->rescues,
            ' · конец ', (string) $s->endedReason, ' · минут ', $s->minutes, ' · вернётся завтра: ', $s->returnsTomorrow ? 'да' : 'нет', PHP_EOL;
    }
}

/** The transcript as the journal holds it — what the report quotes. */
function transcript(string $conversationId): void
{
    $talk = DB::table('conversations')->where('id', $conversationId)->first();
    if ($talk === null) {
        fwrite(STDERR, "нет разговора {$conversationId}\n");

        return;
    }
    $scenes = [];
    foreach (DB::table('plan_scenes')->whereIn('id', json_decode((string) $talk->scene_ids, true) ?: [])->get() as $scene) {
        $scenes[$scene->id] = $scene->title_native.' · '.$scene->partner_role_native;
    }
    $short = static fn (?string $id): string => $id === null ? '—' : (count($parts = explode(':', $id)) === 2 ? $parts[1].(count($scenes) > 1 ? '@'.substr($parts[0], -4) : '') : $id);

    echo str_repeat('─', 110), PHP_EOL;
    echo "РАЗГОВОР {$talk->id} · {$talk->type} · план {$talk->plan_id} · день {$talk->day_number} · {$talk->state}",
        $talk->ended_reason === null ? '' : " ({$talk->ended_reason})", PHP_EOL;
    echo 'сцены: ', implode(' → ', array_map(static fn (string $id): string => ($scenes[$id] ?? $id).' ['.substr($id, -4).']', json_decode((string) $talk->scene_ids, true) ?: [])), PHP_EOL;
    echo 'ходов: ', $talk->turn_limit, ' · подсказки: ', $talk->hints_enabled ? 'да' : 'нет', ' · начат ', $talk->started_at,
        ' · окончен ', $talk->ended_at ?? '—', ' · журнал цены ', money((string) $talk->cost_usd), PHP_EOL;
    echo str_repeat('─', 110), PHP_EOL;

    $model = 0.0;
    $latencies = [];
    $first = null;
    $last = null;
    foreach (DB::table('conversation_turns')->where('conversation_id', $conversationId)->orderBy('turn_index')->get() as $turn) {
        $at = new DateTimeImmutable((string) $turn->created_at);
        $first ??= $at;
        $last = $at;
        $who = $turn->speaker === 'partner' ? 'РОЛЬ   ' : 'УЧЕНИК ';
        echo sprintf('%2d %s[%s] %s', $turn->turn_index, $who, $turn->kind, (string) ($turn->text_target ?? '—')), PHP_EOL;
        if ($turn->text_native !== null) {
            echo '              ', $turn->text_native, PHP_EOL;
        }
        $marks = [];
        if ($turn->understood !== null) {
            $marks[] = 'понял: '.($turn->understood ? 'да' : 'НЕТ');
        }
        if ($turn->off_topic !== null && $turn->off_topic) {
            $marks[] = 'в сторону: ДА';
        }
        if ($turn->checkpoint_done !== null) {
            $marks[] = 'закрыл сцену: '.($scenes[$turn->checkpoint_done] ?? $turn->checkpoint_done);
        }
        $phrases = json_decode((string) $turn->phrases_used, true) ?: [];
        if ($phrases !== []) {
            $marks[] = 'цели засчитаны: '.implode(', ', array_map($short, $phrases));
        }
        if ($turn->speaker === 'partner') {
            $marks[] = 'дверь: '.$short($turn->opens_target);
        }
        if ($turn->hint_native !== null) {
            $marks[] = 'подсказка: «'.$turn->hint_native.'»';
        }
        if ($marks !== []) {
            echo '              · ', implode(' · ', $marks), PHP_EOL;
        }
        if ($turn->speaker === 'partner') {
            $model += (float) $turn->model_cost_usd;
            $latencies[] = (int) $turn->latency_ms;
            echo sprintf('              · %s · %s · вход %s, выход %s · ход %d мс · %s · t+%d с',
                (string) $turn->model, (string) $turn->prompt_version, (string) $turn->tokens_in, (string) $turn->tokens_out,
                (int) $turn->latency_ms, money((string) $turn->model_cost_usd), $at->getTimestamp() - $first->getTimestamp()), PHP_EOL;
        }
    }
    sort($latencies);
    $p = static fn (float $q): int => $latencies === [] ? 0 : $latencies[min(count($latencies) - 1, (int) ceil($q * count($latencies)) - 1)];
    echo str_repeat('─', 110), PHP_EOL;
    echo 'модель ', money($model), ' (голос выключен) · время хода роли: p50 ', $p(0.5), ' мс · p95 ', $p(0.95), ' мс · максимум ',
        $latencies === [] ? 0 : max($latencies), ' мс · ходов роли ', count($latencies), ' · время разговора по журналу ',
        $first === null ? 0 : $last->getTimestamp() - $first->getTimestamp(), ' с', PHP_EOL;
}

$argv = $_SERVER['argv'];
$command = $argv[1] ?? '';
$args = array_slice($argv, 2);
$flags = [];
foreach ($args as $i => $arg) {
    if (str_starts_with($arg, '--')) {
        [$name, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, '1');
        $flags[$name] = $value;
        unset($args[$i]);
    }
}
$args = array_values($args);

if ($command === 'start') {
    $planId = (string) $args[0];
    app(StartPlanHandler::class)(new StartPlan(PlanId::fromString($planId), actorOf($planId)));
    echo "план {$planId} начат: ", DB::table('plans')->where('id', $planId)->value('status'), PHP_EOL;
    exit(0);
}

if ($command === 'open') {
    [$planId, $number] = [(string) $args[0], (int) $args[1]];
    app(OpenDayHandler::class)(new OpenDay(PlanId::fromString($planId), $number, actorOf($planId)));
    $cards = DB::table('day_cards')->where('day_id', DB::table('plan_days')->where('plan_id', $planId)->where('number', $number)->value('id'))->get(['stage', 'kind']);
    echo "открыт день {$number}: ", $cards->count(), ' карточек · ', $cards->countBy('stage')->map(static fn (int $n, string $s): string => "{$s} {$n}")->implode(', '), PHP_EOL;
    exit(0);
}

if ($command === 'walk') {
    [$planId, $number] = [(string) $args[0], (int) $args[1]];
    $actor = actorOf($planId);
    $dayId = DB::table('plan_days')->where('plan_id', $planId)->where('number', $number)->value('id');
    $answer = app(AnswerCardHandler::class);
    $answered = 0;
    while (($cards = DB::table('day_cards')->where('day_id', $dayId)->whereNull('result')->orderBy('position')->get(['id', 'kind']))->isNotEmpty()) {
        foreach ($cards as $card) {
            $result = CardKind::from($card->kind)->isJudged() ? CardResult::Skipped : CardResult::Passed;
            $answer(new AnswerCard(PlanId::fromString($planId), $number, DayCardId::fromString($card->id), $result, 1, $actor));
            $answered++;
        }
    }
    foreach (Stage::ofCards() as $stage) {
        app(CloseStageHandler::class)(new CloseStage(PlanId::fromString($planId), $number, $stage, $actor));
    }
    echo "день {$number}: отвечено карточек {$answered}, этапы карточек закрыты\n";
    exit(0);
}

if ($command === 'room') {
    [$planId, $number] = [(string) $args[0], (int) $args[1]];
    write(PlanJson::room(app(GetDayRoomHandler::class)(new GetDayRoom(PlanId::fromString($planId), $number, actorOf($planId)))), $args[2] ?? null);
    exit(0);
}

if ($command === 'dump') {
    $id = (string) $args[0];
    $planId = (string) DB::table('conversations')->where('id', $id)->value('plan_id');
    write(PlanJson::conversation(app(GetConversationHandler::class)(new GetConversation(ConversationId::fromString($id), actorOf($planId)))), $args[1] ?? null);
    exit(0);
}

if ($command === 'snapshot-room') {
    [$planId, $number, $file] = [(string) $args[0], (int) $args[1], (string) $args[2]];
    DB::beginTransaction();
    try {
        $dayId = DB::table('plan_days')->where('plan_id', $planId)->where('number', $number)->value('id');
        $talks = DB::table('conversations')->where('day_id', $dayId)->pluck('id')->all();
        DB::table('plan_stage_passages')->where('day_id', $dayId)->delete();
        DB::table('conversation_turns')->whereIn('conversation_id', $talks)->delete();
        DB::table('conversations')->whereIn('id', $talks)->delete();
        DB::table('day_cards')->where('day_id', $dayId)->delete();
        DB::table('plan_days')->where('id', $dayId)->update(['status' => 'open', 'opened_at' => null, 'closed_at' => null, 'cards_total' => 0, 'cards_done' => 0, 'minutes_spent' => 0]);
        app(OpenDayHandler::class)(new OpenDay(PlanId::fromString($planId), $number, actorOf($planId)));
        write(PlanJson::room(app(GetDayRoomHandler::class)(new GetDayRoom(PlanId::fromString($planId), $number, actorOf($planId)))), $file);
    } finally {
        DB::rollBack();
    }
    echo "день {$number} плана {$planId} — как был (транзакция откатана)\n";
    exit(0);
}

if ($command === 'dump-at') {
    [$id, $upTo, $file] = [(string) $args[0], (int) $args[1], (string) $args[2]];
    $planId = (string) DB::table('conversations')->where('id', $id)->value('plan_id');
    DB::beginTransaction();
    try {
        DB::table('conversation_turns')->where('conversation_id', $id)->where('turn_index', '>', $upTo)->delete();
        $done = DB::table('conversation_turns')->where('conversation_id', $id)->whereNotNull('checkpoint_done')->orderBy('turn_index')->pluck('checkpoint_done')->unique()->values()->all();
        DB::table('conversations')->where('id', $id)->update([
            'state' => 'your_turn', 'ended_at' => null, 'ended_reason' => null,
            'checkpoints_done' => json_encode($done),
            'cost_usd' => DB::table('conversation_turns')->where('conversation_id', $id)->sum('model_cost_usd'),
        ]);
        write(PlanJson::conversation(app(GetConversationHandler::class)(new GetConversation(ConversationId::fromString($id), actorOf($planId)))), $file);
    } finally {
        DB::rollBack();
    }
    echo "разговор {$id} — как был (транзакция откатана)\n";
    exit(0);
}

if ($command === 'show') {
    transcript((string) $args[0]);
    exit(0);
}

if ($command === 'bill') {
    $rows = DB::table('model_calls')->where('started_at', '>=', SINCE)
        ->selectRaw('purpose, model, status, count(*) AS calls, sum(tokens_in) AS tokens_in, sum(tokens_out) AS tokens_out, sum(cost_usd) AS cost')
        ->groupBy('purpose', 'model', 'status')->orderBy('purpose')->get();
    foreach ($rows as $row) {
        echo sprintf("%-14s %-22s %-10s вызовов %3d · вход %6d · выход %5d · %s\n", (string) $row->purpose, $row->model, $row->status, $row->calls, (int) $row->tokens_in, (int) $row->tokens_out, money((string) $row->cost));
    }
    $s = spent();
    echo 'с ', SINCE, ': разговоров ', $s['talks'], ' из ', MAX_TALKS, ' · ', money($s['usd']), ' из $', number_format(CAP_USD, 2), PHP_EOL;
    exit(0);
}

if ($command === 'talk') {
    withinLimits(true);
    [$planId, $number] = [(string) $args[0], (int) $args[1]];
    $at = hrtime(true);
    $view = app(StartConversationHandler::class)(new StartConversation(
        PlanId::fromString($planId), $number, actorOf($planId), again: isset($flags['again']), hints: ($flags['hints'] ?? '1') !== '0',
    ));
    $turns = $view->turns;
    echo 'РАЗГОВОР ', $view->id, ' · ', $view->type, $view->replay ? ' · повтор' : '', ' · ', (int) round((hrtime(true) - $at) / 1_000_000), " мс\n";
    echo '  роль: ', end($turns)->textTarget ?? '—', PHP_EOL;
    printView($view);
    exit(0);
}

if ($command === 'move') {
    withinLimits(false);
    [$id, $line] = [(string) $args[0], (string) ($args[1] ?? '')];
    $planId = (string) DB::table('conversations')->where('id', $id)->value('plan_id');
    $kind = match ($line) {
        'rescue' => TurnKind::Rescue,
        'skip' => TurnKind::Skip,
        default => TurnKind::Said,
    };
    pacedClock($id);
    $at = hrtime(true);
    $view = app(TakeConversationTurnHandler::class)(new TakeConversationTurn(ConversationId::fromString($id), $kind, $kind === TurnKind::Said ? $line : '', actorOf($planId)));
    $turns = $view->turns;
    $reply = end($turns);
    echo '> ', $kind === TurnKind::Said ? $line : strtoupper($line), PHP_EOL;
    echo '  роль: ', $reply === false ? '—' : (string) $reply->textTarget, ' · ', (int) round((hrtime(true) - $at) / 1_000_000), " мс\n";
    printView($view);
    exit(0);
}

fwrite(STDERR, "команды: start | open | walk | talk | move | show | dump | dump-at | room | snapshot-room | bill\n");
exit(1);

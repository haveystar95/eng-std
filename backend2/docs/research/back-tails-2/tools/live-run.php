<?php

declare(strict_types=1);

/**
 * BACK-TAILS-2 §12 · THE LIVE RUN — the days and the talks through the same handlers the phone calls, on a disposable copy
 * of the e2e stand (`wordtrainer_bt2_e2e_test`), with the наряд's code. Every model call is a real one and is billed.
 *
 *   docker exec -e DB_DATABASE=wordtrainer_bt2_e2e_test wt_tails2 php docs/research/back-tails-2/tools/live-run.php <command> …
 *
 *   reset <plan> <day> [<day> …]   — the days as never opened: their cards, talks and passages gone, the first one open
 *   reset-talks <plan> <day>       — the day's talks and the passage of its sixth stage gone, its cards as they are
 *   open <plan> <day>              — «Открыть»: the day dealt
 *   room <plan> <day> [<file>]     — `GET …/days/{n}` as the client reads it (the `data` of it), into a file or stdout
 *   walk <plan> <day>              — every card answered as the test walk does (judged — skipped, the rest passed), stages closed
 *   talk <plan> <day> [--again] [--hints=0] [--leave] -- "line" "rescue" "skip" …
 *                                  — the talk started (or carried on) and led by these moves; `--leave` leaves it open
 *   close <plan> <day>             — «Закрыть день»
 *   shift <plan>                   — the calendar a day back (`plan:shift-day`), so the next day may open
 *   dump <conversation> [<file>]   — `GET …/conversation/{id}` (its `data`)
 *   show <conversation>            — the transcript: every move, the role's guards, the targets said, the time and the bill
 *   bill <since>                   — `model_calls` since an ISO moment: calls, tokens and dollars by purpose
 *
 * The voice is off on this stand (`SPEECH_ENABLED=false`): no vendor of speech is paid, and a turn's time is the model's
 * and the server's — see the report for what the voice adds.
 */

use App\Modules\Plan\Application\Command\AnswerCard;
use App\Modules\Plan\Application\Command\AnswerCardHandler;
use App\Modules\Plan\Application\Command\CloseDay;
use App\Modules\Plan\Application\Command\CloseDayHandler;
use App\Modules\Plan\Application\Command\CloseStage;
use App\Modules\Plan\Application\Command\CloseStageHandler;
use App\Modules\Plan\Application\Command\OpenDay;
use App\Modules\Plan\Application\Command\OpenDayHandler;
use App\Modules\Plan\Application\Command\StartConversation;
use App\Modules\Plan\Application\Command\StartConversationHandler;
use App\Modules\Plan\Application\Command\TakeConversationTurn;
use App\Modules\Plan\Application\Command\TakeConversationTurnHandler;
use App\Modules\Plan\Application\Dto\ConversationView;
use App\Modules\Plan\Application\Query\GetConversation;
use App\Modules\Plan\Application\Query\GetConversationHandler;
use App\Modules\Plan\Application\Query\GetDayRoom;
use App\Modules\Plan\Application\Query\GetDayRoomHandler;
use App\Modules\Plan\Domain\Check\Language\LanguagePack;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Service\PhraseUse;
use App\Modules\Plan\Domain\Service\RoleLines;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\Stage;
use App\Modules\Plan\Domain\ValueObject\TurnKind;
use App\Modules\Plan\Presentation\Http\PlanJson;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.pgsql.database');
if ($database !== 'wordtrainer_bt2_e2e_test') {
    fwrite(STDERR, "ОТКАЗ: этот харнесс пишет и покупает — только на копии стенда wordtrainer_bt2_e2e_test, а не на «{$database}».\n");
    exit(1);
}

const JSON_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;

/** @return array{0: list<string>, 1: array<string, string>} positional args and --flags */
function parseArgs(array $argv): array
{
    $lines = [];
    $flags = [];
    $afterDashes = false;
    foreach (array_slice($argv, 2) as $arg) {
        if ($arg === '--') {
            $afterDashes = true;

            continue;
        }
        if (! $afterDashes && str_starts_with($arg, '--')) {
            [$name, $value] = array_pad(explode('=', substr($arg, 2), 2), 2, '1');
            $flags[$name] = $value;

            continue;
        }
        $lines[] = $arg;
    }

    return [$lines, $flags];
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

/** @return array<string, int> the hits of every check of the role's prompt, `check · action` → hits */
function guards(): array
{
    $out = [];
    foreach (DB::table('plan_check_counters')->where('prompt_version', 'conversation_agent.v2.1')->get() as $row) {
        $out[$row->check_name.' · '.$row->action] = (int) $row->hits;
    }

    return $out;
}

/** @param array<string, int> $before */
function guardsSince(array $before): string
{
    $moved = [];
    foreach (guards() as $key => $hits) {
        if ($hits > ($before[$key] ?? 0)) {
            $moved[] = $key.' +'.($hits - ($before[$key] ?? 0));
        }
    }

    return $moved === [] ? 'нет' : implode(', ', $moved);
}

/**
 * The guard's reading of a reply against the move it answers (§9): the largest share of a sentence of the reply that the
 * move had already said, as said and with the persons swapped — what `RoleLines::echoIn()` holds against 0.7.
 */
function echoOf(string $reply, string $heard, LanguagePack $pack, PhraseUse $use): string
{
    $best = [0.0, 0.0];
    foreach (preg_split('/(?<=[.?!…])\s+/u', trim($reply), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $sentence) {
        $best = [max($best[0], $use->share($sentence, $heard, $pack)), max($best[1], $use->share($sentence, $heard, $pack, true))];
    }
    $echo = RoleLines::echoIn($reply, $heard, $pack, $use);

    return sprintf('эхо: доля предложения %.2f / с заменой лиц %.2f (порог 0,70)%s', $best[0], $best[1], $echo === null ? '' : ' — ЭХО «'.$echo.'»');
}

function printView(ConversationView $view): void
{
    $said = array_values(array_filter($view->targets, static fn (array $t): bool => $t['said']));
    echo '  состояние ', $view->state, ' · ходов сцены осталось ', $view->turnsLeft, ' · сцена «', $view->sceneTitleNative, '» · ', $view->partnerRoleNative,
        ' · сказано целей ', count($said), ' из ', count($view->targets),
        $said === [] ? '' : ' ('.implode(', ', array_map(static fn (array $t): string => $t['ref'], $said)).')',
        $view->hintNative === null ? '' : ' · подсказка «'.$view->hintNative.'»', PHP_EOL;
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
    $pack = app(LanguagePacks::class)->for((string) DB::table('plans')->where('id', $talk->plan_id)->value('target_lang'));
    $use = new PhraseUse;

    echo str_repeat('─', 110), PHP_EOL;
    echo "РАЗГОВОР {$talk->id} · {$talk->type} · план {$talk->plan_id} · день {$talk->day_number} · {$talk->state}",
        $talk->ended_reason === null ? '' : " ({$talk->ended_reason})", PHP_EOL;
    echo 'сцены: ', implode(' → ', array_map(static fn (string $id): string => $scenes[$id] ?? $id, json_decode((string) $talk->scene_ids, true) ?: [])), PHP_EOL;
    echo 'ходов сцены: ', $talk->turn_limit, ' · подсказки: ', $talk->hints_enabled ? 'да' : 'нет', ' · начат ', $talk->started_at,
        ' · окончен ', $talk->ended_at ?? '—', ' · журнал цены разговора ', money((string) $talk->cost_usd), PHP_EOL;
    echo str_repeat('─', 110), PHP_EOL;

    $model = 0.0;
    $latencies = [];
    $heard = null;
    foreach (DB::table('conversation_turns')->where('conversation_id', $conversationId)->orderBy('turn_index')->get() as $turn) {
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
            $marks[] = 'цели засчитаны: '.implode(', ', array_map(static fn (string $id): string => explode(':', $id)[1] ?? $id, $phrases));
        }
        if ($turn->hint_native !== null) {
            $marks[] = 'подсказка: «'.$turn->hint_native.'»';
        }
        if ($turn->speaker === 'partner' && $heard !== null && $turn->text_target !== null) {
            $marks[] = echoOf((string) $turn->text_target, $heard, $pack, $use);
        }
        if ($marks !== []) {
            echo '              · ', implode(' · ', $marks), PHP_EOL;
        }
        if ($turn->speaker === 'partner') {
            $model += (float) $turn->model_cost_usd;
            $latencies[] = (int) $turn->latency_ms;
            echo sprintf('              · %s · %s · вход %s, выход %s · модель %d мс · ход %d мс · %s',
                (string) $turn->model, (string) $turn->prompt_version, (string) $turn->tokens_in, (string) $turn->tokens_out,
                (int) $turn->model_latency_ms, (int) $turn->latency_ms, money((string) $turn->model_cost_usd)), PHP_EOL;
            $heard = null;
        } elseif ($turn->kind === 'said') {
            $heard = (string) $turn->text_target;
        }
    }
    sort($latencies);
    $p = static fn (float $q): int => $latencies === [] ? 0 : $latencies[min(count($latencies) - 1, (int) ceil($q * count($latencies)) - 1)];
    echo str_repeat('─', 110), PHP_EOL;
    echo 'модель ', money($model), ' (голос выключен) · время хода: p50 ', $p(0.5), ' мс · p95 ', $p(0.95), ' мс · максимум ',
        $latencies === [] ? 0 : max($latencies), ' мс · ходов роли ', count($latencies), PHP_EOL;
}

[$positional, $flags] = parseArgs($_SERVER['argv']);
$command = $_SERVER['argv'][1] ?? '';

if ($command === 'reset') {
    $planId = (string) array_shift($positional);
    $days = DB::table('plan_days')->where('plan_id', $planId)->whereIn('number', array_map('intval', $positional))->orderBy('number')->get(['id', 'number']);
    DB::transaction(static function () use ($days): void {
        $ids = $days->pluck('id')->all();
        $talks = DB::table('conversations')->whereIn('day_id', $ids)->pluck('id')->all();
        DB::table('plan_stage_passages')->whereIn('day_id', $ids)->delete();
        DB::table('conversation_turns')->whereIn('conversation_id', $talks)->delete();
        DB::table('conversations')->whereIn('id', $talks)->delete();
        DB::table('day_cards')->whereIn('day_id', $ids)->delete();
        foreach ($days as $i => $day) {
            DB::table('plan_days')->where('id', $day->id)->update([
                'status' => $i === 0 ? 'open' : 'locked', 'opened_at' => null, 'closed_at' => null,
                'cards_total' => 0, 'cards_done' => 0, 'minutes_spent' => 0,
            ]);
        }
    });
    echo 'сброшены дни ', implode(', ', $days->pluck('number')->all()), " плана {$planId}\n";
    exit(0);
}

if ($command === 'reset-talks') {
    [$planId, $number] = [(string) $positional[0], (int) $positional[1]];
    $dayId = DB::table('plan_days')->where('plan_id', $planId)->where('number', $number)->value('id');
    DB::transaction(static function () use ($dayId): void {
        $talks = DB::table('conversations')->where('day_id', $dayId)->pluck('id')->all();
        DB::table('plan_stage_passages')->where('day_id', $dayId)->delete();
        DB::table('conversation_turns')->whereIn('conversation_id', $talks)->delete();
        DB::table('conversations')->whereIn('id', $talks)->delete();
    });
    echo "разговоры дня {$number} плана {$planId} сняты вместе с прохождением этапа; карточки дня — как были\n";
    exit(0);
}

if ($command === 'open') {
    [$planId, $number] = [(string) $positional[0], (int) $positional[1]];
    app(OpenDayHandler::class)(new OpenDay(PlanId::fromString($planId), $number, actorOf($planId)));
    $cards = DB::table('day_cards')->where('day_id', DB::table('plan_days')->where('plan_id', $planId)->where('number', $number)->value('id'))->get(['stage', 'kind']);
    echo "открыт день {$number}: ", $cards->count(), ' карточек · ', $cards->countBy('stage')->map(static fn (int $n, string $s): string => "{$s} {$n}")->implode(', '), PHP_EOL;
    exit(0);
}

if ($command === 'room') {
    [$planId, $number] = [(string) $positional[0], (int) $positional[1]];
    $view = app(GetDayRoomHandler::class)(new GetDayRoom(PlanId::fromString($planId), $number, actorOf($planId)));
    write(PlanJson::room($view), $positional[2] ?? null);
    exit(0);
}

if ($command === 'walk') {
    [$planId, $number] = [(string) $positional[0], (int) $positional[1]];
    $actor = actorOf($planId);
    $dayId = DB::table('plan_days')->where('plan_id', $planId)->where('number', $number)->value('id');
    $answer = app(AnswerCardHandler::class);
    $answered = 0;
    // A copy dealt at the end of a stage is walked too: read the unanswered ones until none is left.
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

if ($command === 'close') {
    [$planId, $number] = [(string) $positional[0], (int) $positional[1]];
    app(CloseDayHandler::class)(new CloseDay(PlanId::fromString($planId), $number, actorOf($planId)));
    $day = DB::table('plan_days')->where('plan_id', $planId)->where('number', $number)->first(['status', 'cards_total', 'cards_done', 'minutes_spent']);
    echo "день {$number} закрыт: {$day->status} · карточек {$day->cards_done} из {$day->cards_total} · minutes_spent {$day->minutes_spent}\n";
    exit(0);
}

if ($command === 'shift') {
    Artisan::call('plan:shift-day', ['plan' => (string) $positional[0], '--days' => 1]);
    echo Artisan::output();
    exit(0);
}

if ($command === 'dump') {
    $id = (string) $positional[0];
    $planId = (string) DB::table('conversations')->where('id', $id)->value('plan_id');
    $view = app(GetConversationHandler::class)(new GetConversation(ConversationId::fromString($id), actorOf($planId)));
    write(PlanJson::conversation($view), $positional[1] ?? null);
    exit(0);
}

if ($command === 'show') {
    transcript((string) $positional[0]);
    exit(0);
}

if ($command === 'bill') {
    $since = (string) ($positional[0] ?? '');
    $rows = DB::table('model_calls')->where('started_at', '>=', $since)
        ->selectRaw('purpose, model, status, count(*) AS calls, sum(tokens_in) AS tokens_in, sum(tokens_out) AS tokens_out, sum(cost_usd) AS cost')
        ->groupBy('purpose', 'model', 'status')->orderBy('purpose')->get();
    $total = 0.0;
    foreach ($rows as $row) {
        $total += (float) $row->cost;
        echo sprintf("%-14s %-22s %-10s вызовов %3d · вход %6d · выход %5d · %s\n", (string) $row->purpose, $row->model, $row->status, $row->calls, (int) $row->tokens_in, (int) $row->tokens_out, money((string) $row->cost));
    }
    echo 'всего с ', $since, ': ', money($total), ' (кап наряда $0.50)', PHP_EOL;
    exit(0);
}

if ($command !== 'talk') {
    fwrite(STDERR, "команды: reset | open | room | walk | talk | close | shift | dump | show | bill\n");
    exit(1);
}

[$planId, $number] = [(string) array_shift($positional), (int) array_shift($positional)];
$actor = actorOf($planId);
$pack = app(LanguagePacks::class)->for((string) DB::table('plans')->where('id', $planId)->value('target_lang'));
$use = new PhraseUse;

$view = app(StartConversationHandler::class)(new StartConversation(
    PlanId::fromString($planId), $number, $actor, again: isset($flags['again']), hints: ($flags['hints'] ?? '1') !== '0',
));
echo 'РАЗГОВОР ', $view->id, ' · ', $view->type, $view->replay ? ' · повтор' : '', ' · цели: ',
    implode(' | ', array_map(static fn (array $t): string => $t['ref'].' '.$t['text_target'], $view->targets)), PHP_EOL;
printView($view);

foreach ($positional as $line) {
    if ($view->state === 'ended') {
        echo "(разговор окончен — остальные ходы не сказаны)\n";
        break;
    }
    $kind = match ($line) {
        'rescue' => TurnKind::Rescue,
        'skip' => TurnKind::Skip,
        default => TurnKind::Said,
    };
    $before = guards();
    $at = hrtime(true);
    echo PHP_EOL, '> ', $kind === TurnKind::Said ? $line : strtoupper($line), PHP_EOL;
    $view = app(TakeConversationTurnHandler::class)(new TakeConversationTurn(ConversationId::fromString($view->id), $kind, $kind === TurnKind::Said ? $line : '', $actor));
    $turns = $view->turns;
    $reply = end($turns);
    echo '  роль: ', $reply === false ? '—' : (string) $reply->textTarget, PHP_EOL;
    if ($kind === TurnKind::Said && $reply !== false && $reply->textTarget !== null) {
        printf("  %s · сторожа роли: %s · %d мс\n", echoOf($reply->textTarget, $line, $pack, $use), guardsSince($before),
            (int) round((hrtime(true) - $at) / 1_000_000));
    }
    printView($view);
}

if (! isset($flags['leave']) && $view->state !== 'ended') {
    echo "(ходы кончились, разговор открыт)\n";
}
echo PHP_EOL;
transcript($view->id);

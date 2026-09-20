<?php

declare(strict_types=1);

/**
 * CONV-1 · ЖИВОЙ РАЗГОВОР С АГЕНТОМ, через те же обработчики, что и телефон.
 *
 *   prepare-day <plan>                 — поставить день-сцену плана в `in_progress` с шестым этапом (стенд).
 *   prepare-rehearsal <plan> <from>    — вторую сцену плана заполнить уроком сцены `<from>` (её первой), сделать обе
 *                                        `ready`, а день 2 — репетицией в `in_progress` (стенд).
 *   talk <plan> <day> [--hints=0] -- "реплика" "rescue" "skip" …
 *                                      — начать разговор и вести его сказанными репликами до конца; `rescue` и `skip`
 *                                        как слова — это ходы «Не понял» и «Пропустить».
 *   show <conversation>                — стенограмма разговора из журнала: суждения, подсказки, цена и время хода.
 *
 * ТОЛЬКО одноразовая база. Запуск:
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test -e QUEUE_CONNECTION=sync -e CACHE_STORE=array \
 *     app php docs/research/conv-1/tools/live-talk.php talk <plan> 1 -- "My son has a fever." …
 *
 * Деньги: каждый ход печатает свою цену (модель + озвучка) и время; в конце — итог разговора и сумма.
 */

use App\Modules\Plan\Application\Command\StartConversation;
use App\Modules\Plan\Application\Command\StartConversationHandler;
use App\Modules\Plan\Application\Command\TakeConversationTurn;
use App\Modules\Plan\Application\Command\TakeConversationTurnHandler;
use App\Modules\Plan\Application\Dto\ConversationView;
use App\Modules\Plan\Domain\ValueObject\ConversationId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\TurnKind;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = (string) config('database.connections.pgsql.database');
if ($database !== 'wordtrainer_e2e_test') {
    fwrite(STDERR, "ОТКАЗ: этот харнесс пишет и покупает — только на wordtrainer_e2e_test, а не на «{$database}».\n");
    exit(1);
}

$argv = $_SERVER['argv'];
$command = $argv[1] ?? '';

/** @return array{0: list<string>, 1: array<string, string>} positional args and --flags */
function parseArgs(array $argv): array
{
    $rest = array_slice($argv, 2);
    $lines = [];
    $flags = [];
    $afterDashes = false;
    foreach ($rest as $arg) {
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

function money(string $usd): string
{
    return '$'.number_format((float) $usd, 6, '.', '');
}

/** The turns of a talk as the journal holds them — what the report quotes. */
function transcript(string $conversationId): void
{
    $talk = DB::table('conversations')->where('id', $conversationId)->first();
    if ($talk === null) {
        fwrite(STDERR, "нет такого разговора: {$conversationId}\n");

        return;
    }
    $scenes = [];
    foreach (DB::table('plan_scenes')->whereIn('id', json_decode((string) $talk->scene_ids, true) ?: [])->get() as $scene) {
        $scenes[$scene->id] = $scene->title_native.' · '.$scene->partner_role_native;
    }

    echo str_repeat('─', 100), PHP_EOL;
    echo "РАЗГОВОР {$talk->id} · {$talk->type} · день {$talk->day_number} · {$talk->state}",
        ($talk->ended_reason === null ? '' : " ({$talk->ended_reason})"), PHP_EOL;
    echo 'сцены: ', implode(' → ', $scenes), PHP_EOL;
    echo 'ходов сцены: ', $talk->turn_limit, ' · подсказки: ', $talk->hints_enabled ? 'да' : 'нет',
        ' · потрачено: ', money((string) $talk->cost_usd), PHP_EOL;
    echo str_repeat('─', 100), PHP_EOL;

    $model = 0.0;
    $speech = 0.0;
    $latencies = [];
    foreach (DB::table('conversation_turns')->where('conversation_id', $conversationId)->orderBy('turn_index')->get() as $turn) {
        $who = $turn->speaker === 'partner' ? 'РОЛЬ ' : 'УЧЕНИК';
        echo sprintf('%2d %s [%s] %s', $turn->turn_index, $who, $turn->kind, (string) ($turn->text_target ?? '—')), PHP_EOL;
        if ($turn->text_native !== null) {
            echo '            ', $turn->text_native, PHP_EOL;
        }
        $marks = [];
        if ($turn->understood !== null) {
            $marks[] = 'понял: '.($turn->understood ? 'да' : 'НЕТ');
        }
        if ($turn->off_topic !== null) {
            $marks[] = 'в сторону: '.($turn->off_topic ? 'ДА' : 'нет');
        }
        if ($turn->checkpoint_done !== null) {
            $marks[] = 'закрыл сцену: '.($scenes[$turn->checkpoint_done] ?? $turn->checkpoint_done);
        }
        $phrases = json_decode((string) $turn->phrases_used, true) ?: [];
        if ($phrases !== []) {
            $marks[] = 'фразы плана: '.implode(', ', array_map(static fn (string $id): string => explode(':', $id)[1] ?? $id, $phrases));
        }
        if ($turn->hint_native !== null) {
            $marks[] = 'подсказка: «'.$turn->hint_native.'»';
        }
        if ($marks !== []) {
            echo '            · ', implode(' · ', $marks), PHP_EOL;
        }
        if ($turn->speaker === 'partner') {
            $model += (float) $turn->model_cost_usd;
            $speech += (float) $turn->speech_cost_usd;
            $latencies[] = (int) $turn->latency_ms;
            echo sprintf(
                '            · модель %s (%s, вход %s, выход %s, %d мс) · голос %s (%s симв., %s кредитов, %d мс)%s · ХОД %d мс',
                money((string) $turn->model_cost_usd), (string) $turn->model, (string) $turn->tokens_in, (string) $turn->tokens_out, (int) $turn->model_latency_ms,
                money((string) $turn->speech_cost_usd), (string) ($turn->audio_characters ?? 0), (string) ($turn->audio_credits ?? 0), (int) $turn->speech_latency_ms,
                $turn->audio_path === null ? ' БЕЗ ЗВУКА' : '',
                (int) $turn->latency_ms,
            ), PHP_EOL;
        }
    }

    sort($latencies);
    $p = static fn (float $q): int => $latencies === [] ? 0 : $latencies[min(count($latencies) - 1, (int) floor($q * (count($latencies) - 1)))];
    echo str_repeat('─', 100), PHP_EOL;
    echo 'модель ', money((string) $model), ' · голос ', money((string) $speech), ' · всего ', money((string) ($model + $speech)), PHP_EOL;
    echo 'время хода: p50 ', $p(0.5), ' мс · p95 ', $p(0.95), ' мс · максимум ', $latencies === [] ? 0 : max($latencies), ' мс',
        ' · ходов роли ', count($latencies), PHP_EOL;
}

function printView(ConversationView $view): void
{
    echo 'состояние: ', $view->state, ' · ходов осталось: ', $view->turnsLeft,
        ' · сцена: ', $view->sceneTitleNative, ' · ', $view->partnerRoleNative,
        ($view->hintNative === null ? '' : ' · подсказка: «'.$view->hintNative.'»'), PHP_EOL;
    if ($view->summary !== null) {
        $s = $view->summary;
        echo 'ИТОГ: сказал сам ', $s->saidCount, ' · фразы плана ', $s->phrasesUsed, ' из ', $s->phrasesTotal,
            ' · понял все: ', $s->understoodAll ? 'да' : 'нет ('.$s->notUnderstood.')',
            ' · переспросов ', $s->rescues, ' · конец: ', (string) $s->endedReason, ' · минут ', (string) $s->minutes,
            ' · вернётся завтра: ', $s->returnsTomorrow ? 'да' : 'нет', PHP_EOL;
        $notSaid = array_values(array_filter($s->phrases, static fn (array $p): bool => ! $p['used']));
        echo 'не прозвучало: ', $notSaid === [] ? '—' : implode(' | ', array_map(static fn (array $p): string => $p['ref'].' '.$p['text_target'], $notSaid)), PHP_EOL;
    }
}

[$positional, $flags] = parseArgs($argv);

if ($command === 'prepare-day') {
    $planId = $positional[0] ?? '';
    $number = (int) ($positional[1] ?? 1);
    $day = DB::table('plan_days')->where('plan_id', $planId)->where('number', $number)->first();
    DB::table('plans')->where('id', $planId)->update(['status' => 'active', 'level' => $flags['level'] ?? 'beginner']);
    DB::table('plan_scenes')->where('plan_id', $planId)->whereNotNull('lesson_json')->update(['lesson_status' => 'ready']);
    DB::table('plan_days')->where('id', $day->id)->update([
        'status' => 'in_progress',
        'has_conversation' => true,
        'opened_at' => $day->opened_at ?? now(),
    ]);
    DB::table('conversations')->where('day_id', $day->id)->update(['state' => 'ended', 'ended_reason' => 'replayed', 'ended_at' => now()]);
    echo "готово: день {$number} плана {$planId} идёт, шестой этап есть\n";
    exit(0);
}

if ($command === 'prepare-rehearsal') {
    $planId = $positional[0] ?? '';
    $fromPlan = $positional[1] ?? '';
    $from = DB::table('plan_scenes')->where('plan_id', $fromPlan)->orderBy('order')->first();
    $target = DB::table('plan_scenes')->where('plan_id', $planId)->orderBy('order')->skip(1)->first();
    if ($from === null || $target === null) {
        fwrite(STDERR, "нет сцены-источника или второй сцены плана\n");
        exit(1);
    }
    DB::transaction(static function () use ($from, $target, $planId): void {
        // The order is unique per plan: park the copy out of the way, move the other scene, then take place 1.
        DB::table('plan_scenes')->where('id', $target->id)->update(['order' => 9]);
        DB::table('plan_scenes')->where('plan_id', $planId)->where('id', '!=', $target->id)->update(['order' => 2, 'lesson_status' => 'ready']);
        DB::table('plan_scenes')->where('id', $target->id)->update([
            'title_native' => $from->title_native,
            'title_target' => $from->title_target,
            'teaches_native' => $from->teaches_native,
            'goals_native' => $from->goals_native,
            'learner_role_target' => $from->learner_role_target,
            'learner_role_native' => $from->learner_role_native,
            'partner_role_target' => $from->partner_role_target,
            'partner_role_native' => $from->partner_role_native,
            'partner_voice_gender' => $from->partner_voice_gender,
            'topic_description' => $from->topic_description,
            'lesson_json' => $from->lesson_json,
            'lesson_status' => 'ready',
            'order' => 1,
        ]);
        // The copied scene's terms, with ids of their own.
        DB::table('plan_terms')->where('scene_id', $target->id)->delete();
        foreach (DB::table('plan_terms')->where('scene_id', $from->id)->get() as $term) {
            $row = (array) $term;
            $row['id'] = (string) Illuminate\Support\Str::ulid();
            $row['scene_id'] = $target->id;
            DB::table('plan_terms')->insert($row);
        }
    });
    $day = DB::table('plan_days')->where('plan_id', $planId)->orderByDesc('number')->first();
    DB::table('plans')->where('id', $planId)->update(['status' => 'active', 'level' => $flags['level'] ?? 'beginner']);
    DB::table('plan_days')->where('id', $day->id)->update([
        'type' => 'rehearsal', 'scene_id' => null, 'status' => 'in_progress', 'has_conversation' => true, 'opened_at' => now(),
    ]);
    DB::table('conversations')->where('day_id', $day->id)->update(['state' => 'ended', 'ended_reason' => 'replayed', 'ended_at' => now()]);
    echo "готово: день {$day->number} плана {$planId} — репетиция по двум сценам\n";
    exit(0);
}

if ($command === 'show') {
    transcript($positional[0] ?? '');
    exit(0);
}

if ($command !== 'talk') {
    fwrite(STDERR, "команды: prepare-day | prepare-rehearsal | talk | show\n");
    exit(1);
}

$planId = array_shift($positional) ?? '';
$number = (int) (array_shift($positional) ?? 1);
$plan = DB::table('plans')->where('id', $planId)->first();
if ($plan === null) {
    fwrite(STDERR, "нет плана {$planId}\n");
    exit(1);
}
$actor = UserId::fromString((string) $plan->user_id);

$start = app(StartConversationHandler::class);
$turn = app(TakeConversationTurnHandler::class);

$startedAt = hrtime(true);
$view = $start(new StartConversation(PlanId::fromString($planId), $number, $actor, again: true, hints: ($flags['hints'] ?? '1') !== '0'));
echo 'НАЧАТ разговор ', $view->id, PHP_EOL;
printView($view);

foreach ($positional as $line) {
    if ($view->state === 'ended') {
        echo "(разговор окончен — остальные реплики не сказаны)\n";
        break;
    }
    $kind = match ($line) {
        'rescue' => TurnKind::Rescue,
        'skip' => TurnKind::Skip,
        default => TurnKind::Said,
    };
    echo PHP_EOL, '> ', $kind === TurnKind::Said ? $line : strtoupper($line), PHP_EOL;
    $view = $turn(new TakeConversationTurn(ConversationId::fromString($view->id), $kind, $kind === TurnKind::Said ? $line : '', $actor));
    printView($view);
}

echo PHP_EOL, 'весь прогон: ', (int) round((hrtime(true) - $startedAt) / 1_000_000), ' мс', PHP_EOL, PHP_EOL;
transcript($view->id);

<?php

declare(strict_types=1);

/**
 * GEN-4c · THE TAIL OF THE DAY (наряд GEN-4c, §2, §3, §4, §6) — what the new code reads in every stage answer GEN-4 and GEN-4b
 * recorded, and what it would cost. `gate.php recheck` (RECHECK_FILE=recheck-c.json) reads every answer with the checks of
 * the code as it is now; this reads the rest:
 *
 *   docker exec wt_gen4c php docs/research/gen-4b/tools/gen4c.php asks          (no call) every ask frame and its replies
 *   docker exec wt_gen4c php docs/research/gen-4b/tools/gen4c.php table         (no call) the new codes of recheck-c.json
 *   docker exec wt_gen4c php docs/research/gen-4b/tools/gen4c.php cost          (no call) a day's repairs, two cards against four
 *   docker exec wt_gen4c php docs/research/gen-4b/tools/gen4c.php judge [--only=gpt54-b/02,…]   (PAID, gpt-5.4-mini)
 *
 * `asks` — every `ask` frame of every skeleton answer (and of both e2e days as stored): what it asks for by the target's pack
 * ({@see AskedFor}: yes or no, a fact, a choice) and the word that decided it, and each statement paired with it with the word
 * it opens with — `runs/asks-c.json`. `table` — per run: the skeleton answers, those a new code finds in, those whose budgeted
 * cards (foreign letters, placeholder words, yes or no) are more than a stage's four repairs — `summary-c.md`. `cost` — for the
 * days of gpt-5.4 as their last answers stand: the cards each stage would send, the repairs two and four cards buy, at the
 * price the runs paid for a repair. `judge` — the seam judge v1.2 on the first skeleton answer of the days of gpt-5.4 (GEN-4
 * and GEN-4b) and both e2e days: every reply to a question of the learner's, whether it names a filler — `runs/judge-c.json`,
 * every call in `spend.json` under the unit `judge-c`.
 */

use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Service\LessonContexts;
use App\Modules\Plan\Application\Service\LessonSeamJudge;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\Skeleton\AskedFor;
use App\Modules\Plan\Domain\Lesson\AskReplies;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\Skeleton;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

// gate.php's recorder, builder, requests and money — required, it runs none of its own commands.
require __DIR__.'/gate.php';

/** The skeleton answers the runs recorded, each with the request of its day: [run, plan, attempt, request, raw skeleton]. */
function skeletonAnswers(): Generator
{
    foreach ([...array_map(static fn (string $r): string => "days/{$r}", array_keys(DAY_RUNS)), 'days/gpt54-interrupted', ...array_map(static fn (string $r): string => "skeletons/{$r}", array_keys(SKELETON_RUNS))] as $dir) {
        foreach (glob(RUNS."/{$dir}/*.json") ?: [] as $file) {
            $row = readJson($file);
            $id = (string) $row['plan'];
            $request = dayRequest($id, (array) readJson(RUNS."/plans/{$id}.json"));
            if ($request === null) {
                continue;
            }
            $attempt = 0;
            foreach ($row['calls'] as $call) {
                if ($call['purpose'] === 'skeleton' && array_key_exists('payload', $call)) {
                    yield [$dir, $id, ++$attempt, $request, (array) $call['payload']];
                }
            }
        }
    }
    foreach (['e2e', 'e2e-b'] as $dir) {
        $plan = readJson(OUT."/{$dir}/plan.json");
        $stored = readJson(OUT."/{$dir}/day1-skeleton.json");
        if ($plan !== null && $stored !== null) {
            yield ['e2e', $dir, 'stored', e2eRequest($plan), $stored];
        }
    }
}

$contexts = app(LessonContexts::class);
$parser = new LessonParser;

switch ($argv[1] ?? '') {
    case 'asks':
        $rows = [];
        foreach (skeletonAnswers() as [$run, $id, $attempt, $request, $raw]) {
            try {
                $skeleton = $parser->skeleton($raw);
            } catch (Throwable) {
                continue;
            }
            $words = $contexts->skeleton($request)->targetReading(...AskedFor::keys());
            foreach ($skeleton->frames as $frame) {
                if ($frame->phrase->kind->value !== 'ask' || $words === null) {
                    continue;
                }
                $asked = AskedFor::of($frame->phrase->frameTarget, $words);
                $replies = [];
                foreach ($skeleton->repliesToAsks() as [$line, $to]) {
                    if ($to->id() === $frame->id()) {
                        $replies[] = ['id' => $line->id, 'text' => $line->textTarget, 'opens_with' => $words->yesNoOpening($line->textTarget)];
                    }
                }
                $rows[] = [
                    'run' => $run, 'plan' => $id, 'attempt' => $attempt, 'target' => $request->targetLangCode, 'frame' => $frame->id(),
                    'question' => $frame->phrase->frameTarget, 'asks' => $asked->kind, 'word' => $asked->word, 'replies' => $replies,
                ];
            }
        }
        write(RUNS.'/asks-c.json', $rows);
        $kinds = array_count_values(array_column($rows, 'asks'));
        fwrite(STDERR, sprintf("%d ask frames: %s — written runs/asks-c.json\n", count($rows), json_encode($kinds)));
        break;

    case 'table':
        $recheck = readJson(RUNS.'/recheck-c.json') ?? throw new RuntimeException('run gate.php recheck with RECHECK_FILE=recheck-c.json first');
        $new = ['vocab.from_placeholder', 'partner.yes_no_missing', 'partner.yes_no_extra'];
        $lines = ['| прогон | ответов-скелетов | vocab.from_placeholder | partner.yes_no_missing | partner.yes_no_extra | хоть один новый | бюджетных карточек > 4 (стал бы повтор) | было > 2 (foreign_script, GEN-4b) |', '|---|---|---|---|---|---|---|---|'];
        foreach ($recheck['runs'] as $run => $plans) {
            $answers = 0;
            $with = array_fill_keys($new, 0);
            $any = 0;
            $over = 0;
            $overBefore = 0;
            foreach ($plans as $stages) {
                foreach ($stages['skeleton'] ?? [] as $answer) {
                    if ($answer['off_schema'] !== null) {
                        continue;
                    }
                    $answers++;
                    $codes = array_column($answer['findings'], 'code');
                    foreach ($new as $code) {
                        $with[$code] += in_array($code, $codes, true) ? 1 : 0;
                    }
                    $any += array_intersect($codes, $new) !== [] ? 1 : 0;
                    $cards = [];
                    $foreign = [];
                    foreach ($answer['findings'] as $f) {
                        if (in_array($f['code'], LessonCodes::BUDGETED, true)) {
                            $cards[LessonCard::at($f['address'])?->address ?? 'none'] = true;
                        }
                        if ($f['code'] === 'pronunciation.foreign_script') {
                            $foreign[LessonCard::at($f['address'])?->address ?? 'none'] = true;
                        }
                    }
                    $over += count($cards) > 4 || isset($cards['none']) ? 1 : 0;
                    $overBefore += count($foreign) > 2 || isset($foreign['none']) ? 1 : 0;
                }
            }
            $lines[] = "| {$run} | {$answers} | {$with['vocab.from_placeholder']} | {$with['partner.yes_no_missing']} | {$with['partner.yes_no_extra']} | {$any} | {$over} | {$overBefore} |";
        }
        file_put_contents(OUT.'/summary-c.md', "# GEN-4c · новые коды на записанных ответах GEN-4 и GEN-4b\n\n".implode("\n", $lines)."\n");
        echo implode("\n", $lines), "\n";
        break;

    case 'cost':
        // The days of gpt-5.4 that were built (GEN-4 and GEN-4b), on the stage answers each went on with — its last skeleton and
        // last dialogue. BEFORE: the cards of the warnings the code of GEN-4b finds there (`recheck-b.json`) and the frames the
        // seam judge found not reading in the run, two repairs a stage. AFTER: the code of GEN-4c (`recheck-c.json`), the same
        // frames, the replies the judge v1.2 finds naming a filler (`judge-c.json`, read on the first skeleton answer — a day
        // whose skeleton was asked twice gets none), four repairs a stage. The price of a repair and of a judge call — the mean
        // the runs paid; a second read of the judge when a repair changed a reply or a frame.
        $before = readJson(RUNS.'/recheck-b.json')['runs'] ?? throw new RuntimeException('no recheck-b.json');
        $after = readJson(RUNS.'/recheck-c.json')['runs'] ?? throw new RuntimeException('no recheck-c.json');
        $named = readJson(RUNS.'/judge-c.json') ?? [];
        $cards = static function (array $findings, array $kinds): array {
            $at = [];
            foreach ($findings as $f) {
                $card = ($f['fatal'] ?? false) ? null : LessonCard::at((string) $f['address']);
                if ($card !== null && in_array($card->kind, $kinds, true)) {
                    $at[$card->address] = $card->kind;
                }
            }

            return $at;
        };
        $rows = [];
        $price = ['repair' => [], 'judge' => []];
        foreach (['gpt54', 'gpt54-b'] as $run) {
            foreach (glob(RUNS."/days/{$run}/*.json") ?: [] as $file) {
                $day = readJson($file);
                foreach ($day['calls'] as $call) {
                    if (in_array($call['purpose'], ['repair', 'seam_judge'], true) && isset($call['cost_usd'])) {
                        $price[$call['purpose'] === 'repair' ? 'repair' : 'judge'][] = (float) $call['cost_usd'];
                    }
                }
                $id = (string) $day['plan'];
                if (($day['outcome']['status'] ?? '') !== 'ok' || ! isset($after["days/{$run}"][$id], $before["days/{$run}"][$id])) {
                    continue;
                }
                $skeletons = $after["days/{$run}"][$id]['skeleton'];
                $seams = [];
                foreach ($day['outcome']['judgements'][0]['not_reading'] ?? [] as $address) {
                    $card = LessonCard::at((string) $address);
                    if ($card !== null) {
                        $seams[$card->address] = LessonCard::FRAME;
                    }
                }
                $names = [];
                if (count($skeletons) === 1) {
                    foreach ($named["days/{$run}/{$id}"]['naming'] ?? [] as $line) {
                        $names[$line] = LessonCard::PARTNER_LINE;
                    }
                }
                $skeletonBefore = [...$cards(end($before["days/{$run}"][$id]['skeleton'])['findings'] ?? [], LessonCard::SKELETON_KINDS), ...$seams];
                $skeletonAfter = [...$cards(end($skeletons)['findings'] ?? [], LessonCard::SKELETON_KINDS), ...$seams, ...$names];
                $dialogue = count($cards(end($after["days/{$run}"][$id]['dialogue'])['findings'] ?? [], LessonCard::DIALOGUE_KINDS));
                $rows[] = [
                    'run' => $run, 'plan' => $id, 'skeleton_cards_before' => count($skeletonBefore), 'skeleton_cards_after' => count($skeletonAfter),
                    'dialogue_cards' => $dialogue, 'repairs_before' => min(2, count($skeletonBefore)) + min(2, $dialogue),
                    'repairs_after' => min(4, count($skeletonAfter)) + min(4, $dialogue), 'reread_after' => $names !== [] || $seams !== [] ? 1 : 0,
                    'reread_before' => $seams !== [] ? 1 : 0, 'day_usd' => (float) $day['cost_usd'],
                ];
            }
        }
        $mean = static fn (array $xs): float => $xs === [] ? 0.0 : array_sum($xs) / count($xs);
        $repair = $mean($price['repair']);
        $judge = $mean($price['judge']);
        $days = max(1, count($rows));
        $sum = static fn (string $key): float => array_sum(array_column($rows, $key));
        $extra = (($sum('repairs_after') - $sum('repairs_before')) * $repair + ($sum('reread_after') - $sum('reread_before')) * $judge) / $days;
        $out = [
            'days' => count($rows),
            'repair_usd_mean' => round($repair, 6),
            'judge_usd_mean' => round($judge, 6),
            'skeleton_cards_before' => round($sum('skeleton_cards_before') / $days, 2),
            'skeleton_cards_after' => round($sum('skeleton_cards_after') / $days, 2),
            'dialogue_cards' => round($sum('dialogue_cards') / $days, 2),
            'repairs_per_day_before' => round($sum('repairs_before') / $days, 2),
            'repairs_per_day_after' => round($sum('repairs_after') / $days, 2),
            'day_usd_before' => round($sum('day_usd') / $days, 4),
            'extra_usd_per_day' => round($extra, 4),
            'day_usd_after' => round($sum('day_usd') / $days + $extra, 4),
            'rows' => $rows,
        ];
        write(RUNS.'/cost-c.json', $out);
        echo json_encode(array_diff_key($out, ['rows' => true]), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), "\n";
        break;

    case 'judge':
        $database = (string) DB::connection()->getDatabaseName();
        if (! str_starts_with($database, 'wordtrainer_gen4')) {
            fwrite(STDERR, "Refused: the database is «{$database}», not a stand's (wordtrainer_gen4*).\n");
            exit(1);
        }
        Event::listen(ResponseReceived::class, static function (ResponseReceived $event): void {
            if (str_contains($event->request->url(), '/chat/completions')) {
                RecordingPlanModel::$wire = $event->response->body();
            }
        });
        fwrite(STDERR, sprintf("database=%s cap=$%.2f spent=$%.4f\nNOTE: the REAL judge — every call below is paid.\n", $database, CAP_USD, spent()));
        $only = null;
        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--only=')) {
                $only = array_values(array_filter(explode(',', substr($arg, 7))));
            }
        }
        $model = new RecordingPlanModel(builder(), 'judge-c');
        app()->instance(PlanModelPort::class, $model);
        app()->forgetInstance(LessonSeamJudge::class);
        $judge = app(LessonSeamJudge::class);
        $out = readJson(RUNS.'/judge-c.json') ?? [];
        foreach (skeletonAnswers() as [$run, $id, $attempt, $request, $raw]) {
            $key = "{$run}/{$id}";
            $first = $attempt === 1 || $attempt === 'stored';
            if (! $first || ! in_array($run, ['days/gpt54', 'days/gpt54-b', 'e2e'], true) || ($only !== null && ! in_array(str_replace('days/', '', $key), $only, true)) || isset($out[$key])) {
                continue;
            }
            try {
                $skeleton = $parser->skeleton($raw);
            } catch (Throwable) {
                continue;
            }
            $replies = AskReplies::of($skeleton);
            if ($replies === [] || ! affordable("judge {$key}", 0.01)) {
                continue;
            }
            // The replies alone: the native seams of these answers were read by v1.1 in their own runs.
            $verdict = $judge->judge([], $request->nativeLanguage, $replies, $request->targetLanguage);
            $out[$key] = [
                'replies' => $replies,
                'status' => $verdict->status,
                'naming' => $verdict->naming,
                'note' => $verdict->note,
                'cost_usd' => $verdict->costUsd,
                'raw' => end($model->calls)['raw'] ?? null,
            ];
            write(RUNS.'/judge-c.json', $out);
            fwrite(STDERR, sprintf("judge %s: %d replies, naming %s · spent $%.4f\n", $key, count($replies), json_encode($verdict->naming), spent()));
        }
        break;

    default:
        fwrite(STDERR, "gen4c.php asks|table|cost|judge\n");
        exit(1);
}

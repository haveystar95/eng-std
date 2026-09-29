<?php

declare(strict_types=1);

/**
 * GEN-4 · THE GATE RUN'S TABLES — read off `runs/` alone, no call, no database (included by `gate.php table`). Writes
 * `docs/research/gen-4b/summary.md`:
 *
 *  - per run of days (`luna`, `gpt54`), a row a scene: the first answer of each stage — its findings by code, fatal ones
 *    marked —, the stage repeats, the cards sent to a repair, kept and helped, the seam judge's reads, what the day kept, its
 *    price (every call of it: both stages, their repeats, the judge, the repairs) and its time (the calls' own);
 *  - the summary the order asks for — model → share of clean skeletons, share of clean dialogues, mean repairs a day, mean
 *    price a day — with what else a reader of it needs: days built, stage repeats, repairs that helped, time;
 *  - the codes found on the stages' first answers, by run;
 *  - the skeletons alone (`luna-high`) beside the first skeletons of `luna` on the same scenes.
 *
 * «Clean» is the stage's FIRST answer with no finding at all; «no fatal» — with none of the fatal codes. The shares of the
 * summary and the codes are ONE MEASURE (`runs/recheck.json`, `gate.php recheck`): every answer read by the checks of the
 * code as it is now — a run made before a check changed is measured by the same rules as the one after it; the run's own
 * reading stands beside it where the two differ, and the days whose path the final code would have changed are listed.
 */

use App\Modules\Plan\Domain\Check\LessonCodes;

const TABLE_OUT = OUT.'/summary.md';

/** @return array<string, array<string, mixed>> the runs of a kind, by plan id */
function runFiles(string $dir): array
{
    $out = [];
    foreach (glob(RUNS."/{$dir}/*.json") ?: [] as $file) {
        $row = json_decode((string) file_get_contents($file), true);
        if (is_array($row)) {
            $out[(string) $row['plan']] = $row;
        }
    }
    ksort($out);

    return $out;
}

/** @param array<string, mixed> $day @return array<string, mixed>|null the first attempt of a stage */
function firstAttempt(array $day, string $stage): ?array
{
    foreach ($day['outcome']['attempts'] ?? [] as $attempt) {
        if ($attempt['stage'] === $stage && (int) $attempt['attempt'] === 1) {
            return $attempt;
        }
    }

    return null;
}

/** @param list<array{code: string}> $findings */
function codeList(array $findings): string
{
    if ($findings === []) {
        return '—';
    }
    $counts = [];
    foreach ($findings as $f) {
        $counts[$f['code']] = ($counts[$f['code']] ?? 0) + 1;
    }
    ksort($counts);

    return implode(', ', array_map(
        static fn (string $code, int $n): string => (LessonCodes::isFatal($code) ? '**'.$code.'**' : $code).($n > 1 ? " ×{$n}" : ''),
        array_keys($counts),
        $counts,
    ));
}

function attempts(array $day, string $stage): int
{
    return count(array_filter($day['outcome']['attempts'] ?? [], static fn (array $a): bool => $a['stage'] === $stage));
}

function money(float $usd): string
{
    return '$'.number_format($usd, 4);
}

function share(int $n, int $of): string
{
    return $of === 0 ? '—' : sprintf('%d / %d (%d %%)', $n, $of, (int) round(100 * $n / $of));
}

/** A share by the final code, and the run's own reading beside it when it differs. */
function both(int $n, int $asRun, int $of): string
{
    return share($n, $of).($n === $asRun ? '' : sprintf(' · в прогоне %d', $asRun));
}

/**
 * The answers of one stage of a run by the final code (`recheck.json`): all of them, or the first of each day.
 *
 * @param  array<string, array{skeleton: list<array<string, mixed>>, dialogue: list<array<string, mixed>>}>  $days
 * @return array{n: int, clean: int, fatal_free: int, off: int, codes: array<string, int>}
 */
function measured(array $days, string $stage, bool $firstOnly): array
{
    $out = ['n' => 0, 'clean' => 0, 'fatal_free' => 0, 'off' => 0, 'codes' => []];
    foreach ($days as $answers) {
        foreach ($answers[$stage] ?? [] as $i => $answer) {
            if ($firstOnly && $i > 0) {
                break;
            }
            $out['n']++;
            if ($answer['off_schema'] !== null) {
                $out['off']++;

                continue;
            }
            $out['clean'] += $answer['findings'] === [] ? 1 : 0;
            $out['fatal_free'] += array_filter($answer['findings'], static fn (array $f): bool => $f['fatal']) === [] ? 1 : 0;
            foreach (array_unique(array_column($answer['findings'], 'code')) as $code) {
                $out['codes'][$code] = ($out['codes'][$code] ?? 0) + 1;
            }
        }
    }

    return $out;
}

$plans = runFiles('plans');
$recheck = (readJson(RUNS.'/recheck.json') ?? [])['runs'] ?? [];
$diverged = [];
// One measure or none: a run recorded after the last recheck would be counted by the run's reading alone.
foreach ([...array_map(static fn (string $r): string => "days/{$r}", array_keys(DAY_RUNS)), ...array_map(static fn (string $r): string => "skeletons/{$r}", array_keys(SKELETON_RUNS))] as $dir) {
    $missing = array_diff(array_keys(runFiles($dir)), array_keys($recheck[$dir] ?? []));
    if ($missing !== []) {
        fwrite(STDERR, "Refused: runs/recheck.json has no {$dir} ".implode(', ', $missing)." — run gate.php recheck first.\n");
        exit(1);
    }
}
$lines = ['# GEN-4 · прогон ворот — таблицы', '', '_Собрано `tools/gate.php table` из `runs/` (без вызовов). «Чистый» — ПЕРВЫЙ ответ ступени без единой находки; «без фатальных» — без фатальных кодов (жирным)._', ''];
$summary = [];

foreach (array_keys(DAY_RUNS) as $run) {
    $days = runFiles("days/{$run}");
    if ($days === []) {
        continue;
    }
    $lines[] = "## Дни — `{$run}` (скелет и диалог на `".DAY_RUNS[$run]['model'].'`; починки и судья — по конфигу)';
    $lines[] = '';
    $lines[] = '| план | пара | сцена | скелет, 1-й ответ | повт. | диалог, 1-й ответ | повт. | починки отпр. / оставл. / помогли | судья: прочтений, «не читается» | осталось у дня | итог | цена | время, с |';
    $lines[] = '|---|---|---|---|---|---|---|---|---|---|---|---|---|';
    $s = ['days' => 0, 'built' => 0, 'clean_skeleton' => 0, 'fatal_free_skeleton' => 0, 'clean_dialogue' => 0, 'fatal_free_dialogue' => 0, 'dialogues' => 0,
        'repairs' => 0, 'kept' => 0, 'helped' => 0, 'skeleton_repeats' => 0, 'dialogue_repeats' => 0, 'cost' => 0.0, 'seconds' => 0.0];
    foreach ($days as $id => $day) {
        $goal = PLANS[$id];
        $sk = firstAttempt($day, 'skeleton');
        $dl = firstAttempt($day, 'dialogue');
        $repairs = $day['outcome']['repairs'] ?? [];
        $kept = count(array_filter($repairs, static fn (array $r): bool => (bool) $r['kept']));
        $helped = count(array_filter($repairs, static fn (array $r): bool => $r['helped'] === true));
        $judge = $day['outcome']['judgements'] ?? [];
        $notReading = array_sum(array_map(static fn (array $j): int => count($j['not_reading']), $judge));
        $cost = (float) $day['cost_usd'];
        $seconds = ((int) $day['latency_ms']) / 1000;
        $status = $day['outcome']['status'] === 'ok' ? 'ok' : ($day['outcome']['fail_reason'] ?? $day['outcome']['error'] ?? 'failed');
        $lines[] = sprintf(
            '| %s | %s→%s | %s | %s | %d | %s | %d | %d / %d / %d | %d, %d | %s | %s | %s | %.0f |',
            $id, $goal['native'], $goal['target'], $day['scene']['title_native'] ?? '?',
            $sk === null ? '—' : ($sk['off_schema'] !== null ? 'не по схеме' : codeList($sk['findings'])), max(0, attempts($day, 'skeleton') - 1),
            $dl === null ? '—' : ($dl['off_schema'] !== null ? 'не по схеме' : codeList($dl['findings'])), max(0, attempts($day, 'dialogue') - 1),
            count($repairs), $kept, $helped, count($judge), $notReading,
            codeList($day['outcome']['findings'] ?? []), $status, money($cost), $seconds,
        );
        $s['days']++;
        $s['built'] += $day['outcome']['status'] === 'ok' ? 1 : 0;
        if ($sk !== null && $sk['off_schema'] === null) {
            $s['clean_skeleton'] += $sk['findings'] === [] ? 1 : 0;
            $s['fatal_free_skeleton'] += $sk['fatal'] === [] ? 1 : 0;
        }
        if ($dl !== null) {
            $s['dialogues']++;
            if ($dl['off_schema'] === null) {
                $s['clean_dialogue'] += $dl['findings'] === [] ? 1 : 0;
                $s['fatal_free_dialogue'] += $dl['fatal'] === [] ? 1 : 0;
            }
        }
        foreach ($day['outcome']['attempts'] ?? [] as $attempt) {
            $now = $recheck["days/{$run}"][$id][$attempt['stage']][(int) $attempt['attempt'] - 1] ?? null;
            if ($now === null || $attempt['off_schema'] !== null) {
                continue;
            }
            $was = array_values(array_unique($attempt['fatal']));
            $is = array_values(array_unique(array_column(array_filter($now['findings'], static fn (array $f): bool => $f['fatal']), 'code')));
            sort($was);
            sort($is);
            if ($was !== $is) {
                $diverged[] = sprintf('| `%s` | %s | %s %d | %s | %s |', $run, $id, $attempt['stage'], $attempt['attempt'],
                    $was === [] ? '—' : implode(', ', $was), $is === [] ? '—' : implode(', ', $is));
            }
        }
        $s['repairs'] += count($repairs);
        $s['kept'] += $kept;
        $s['helped'] += $helped;
        $s['skeleton_repeats'] += max(0, attempts($day, 'skeleton') - 1);
        $s['dialogue_repeats'] += max(0, attempts($day, 'dialogue') - 1);
        $s['cost'] += $cost;
        $s['seconds'] += $seconds;
    }
    $summary[$run] = $s;
    $lines[] = '';
}

$lines[] = '## Итог';
$lines[] = '';
$lines[] = '_Доли — первый ответ ступени каждого дня, одной меркой (финальный код проверок, `recheck.json`); «в прогоне» — как прочёл его код прогона, где отличается. Починки, повторы, цена, время — как прошёл прогон._';
$lines[] = '';
$lines[] = '| модель ступеней | дней собрано | чистых скелетов | скелетов без фатальных | чистых диалогов | диалогов без фатальных | починок на день (отпр.) | оставлено / помогло | повторов скелета / диалога | средняя цена дня | среднее время дня, с |';
$lines[] = '|---|---|---|---|---|---|---|---|---|---|---|';
foreach ($summary as $run => $s) {
    $sk = measured($recheck["days/{$run}"] ?? [], 'skeleton', true);
    $dl = measured($recheck["days/{$run}"] ?? [], 'dialogue', true);
    $lines[] = sprintf(
        '| `%s` | %s | %s | %s | %s | %s | %.2f | %d / %d | %d / %d | %s | %.0f |',
        DAY_RUNS[$run]['model'], share($s['built'], $s['days']),
        both($sk['clean'], $s['clean_skeleton'], $s['days']), both($sk['fatal_free'], $s['fatal_free_skeleton'], $s['days']),
        both($dl['clean'], $s['clean_dialogue'], $s['dialogues']), both($dl['fatal_free'], $s['fatal_free_dialogue'], $s['dialogues']),
        $s['days'] === 0 ? 0 : $s['repairs'] / $s['days'], $s['kept'], $s['helped'], $s['skeleton_repeats'], $s['dialogue_repeats'],
        money($s['days'] === 0 ? 0 : $s['cost'] / $s['days']), $s['days'] === 0 ? 0 : $s['seconds'] / $s['days'],
    );
}
$lines[] = '';
$lines[] = '### Все ответы ступеней, одной меркой (первые и повторы)';
$lines[] = '';
$lines[] = '| прогон | скелетов | чистых | без фатальных | не по схеме | диалогов | чистых | без фатальных | не по схеме |';
$lines[] = '|---|---|---|---|---|---|---|---|---|';
foreach ([...array_map(static fn (string $r): string => "days/{$r}", array_keys(DAY_RUNS)), ...array_map(static fn (string $r): string => "skeletons/{$r}", array_keys(SKELETON_RUNS))] as $dir) {
    if (! isset($recheck[$dir])) {
        continue;
    }
    $sk = measured($recheck[$dir], 'skeleton', false);
    $dl = measured($recheck[$dir], 'dialogue', false);
    $lines[] = sprintf('| `%s` | %d | %s | %s | %d | %d | %s | %s | %d |', $dir, $sk['n'], share($sk['clean'], $sk['n']), share($sk['fatal_free'], $sk['n']), $sk['off'],
        $dl['n'], share($dl['clean'], $dl['n']), share($dl['fatal_free'], $dl['n']), $dl['off']);
}
$lines[] = '';
if ($diverged !== []) {
    $lines[] = '### Где финальный код прочёл бы ответ иначе (фатальные коды)';
    $lines[] = '';
    $lines[] = '| прогон | план | ответ | фатальные в прогоне | фатальные финальным кодом |';
    $lines[] = '|---|---|---|---|---|';
    array_push($lines, ...$diverged);
    $lines[] = '';
}

foreach (array_keys(SKELETON_RUNS) as $run) {
    $skeletons = runFiles("skeletons/{$run}");
    if ($skeletons === []) {
        continue;
    }
    $luna = runFiles('days/luna');
    $lines[] = "## Скелет отдельно — `{$run}` (`".SKELETON_RUNS[$run]['model'].'`, reasoning_effort `'.SKELETON_RUNS[$run]['effort'].'`) рядом с первым скелетом `luna` (обе колонки — финальным кодом)';
    $lines[] = '';
    $lines[] = '| план | сцена | `'.$run.'`: находки | токены рассуждения | цена | `luna` (без effort): находки 1-го скелета | токены рассуждения |';
    $lines[] = '|---|---|---|---|---|---|---|';
    $clean = ['high' => 0, 'luna' => 0, 'n' => 0, 'high_fatal_free' => 0, 'luna_fatal_free' => 0, 'cost' => 0.0];
    foreach ($skeletons as $id => $row) {
        $found = $row['findings'];
        $reasoning = $row['calls'][0]['reasoning_tokens'] ?? null;
        $day = $luna[$id] ?? null;
        $first = $recheck['days/luna'][$id]['skeleton'][0] ?? null;
        $found = $recheck["skeletons/{$run}"][$id]['skeleton'][0]['findings'] ?? $found;
        $lunaCall = $day === null ? null : ($day['calls'][0] ?? null);
        $lines[] = sprintf('| %s | %s | %s | %s | %s | %s | %s |', $id, $row['scene']['title_native'] ?? '?',
            $row['error'] !== null ? $row['error'] : codeList($found ?? []), $reasoning ?? '—', money((float) $row['cost_usd']),
            $first === null ? '—' : ($first['off_schema'] !== null ? 'не по схеме' : codeList($first['findings'])), $lunaCall['reasoning_tokens'] ?? '—');
        $clean['n']++;
        $clean['cost'] += (float) $row['cost_usd'];
        $clean['high'] += $row['error'] === null && $found === [] ? 1 : 0;
        $clean['high_fatal_free'] += $row['error'] === null && array_filter($found ?? [], static fn (array $f): bool => $f['fatal']) === [] ? 1 : 0;
        if ($first !== null && $first['off_schema'] === null) {
            $clean['luna'] += $first['findings'] === [] ? 1 : 0;
            $clean['luna_fatal_free'] += array_filter($first['findings'], static fn (array $f): bool => $f['fatal']) === [] ? 1 : 0;
        }
    }
    $lines[] = '';
    $lines[] = sprintf('Чистых скелетов: `%s` — %s, `luna` — %s; без фатальных: %s против %s; средняя цена скелета `%s` — %s.',
        $run, share($clean['high'], $clean['n']), share($clean['luna'], $clean['n']), share($clean['high_fatal_free'], $clean['n']), share($clean['luna_fatal_free'], $clean['n']),
        $run, money($clean['n'] === 0 ? 0 : $clean['cost'] / $clean['n']));
    $lines[] = '';
}

$codeCounts = [];
foreach ($recheck as $dir => $days) {
    foreach (['skeleton', 'dialogue'] as $stage) {
        foreach (measured($days, $stage, true)['codes'] as $code => $n) {
            $codeCounts[$code][str_replace(['days/', 'skeletons/'], '', $dir).'/'.$stage] = $n;
        }
    }
}
if ($codeCounts !== []) {
    ksort($codeCounts);
    $columns = [];
    foreach ($codeCounts as $byRun) {
        $columns = array_values(array_unique([...$columns, ...array_keys($byRun)]));
    }
    sort($columns);
    $lines[] = '## Коды на первых ответах ступеней (одной меркой; число дней, где код найден)';
    $lines[] = '';
    $lines[] = '| код | '.implode(' | ', $columns).' |';
    $lines[] = '|---|'.str_repeat('---|', count($columns));
    foreach ($codeCounts as $code => $byRun) {
        $lines[] = '| '.(LessonCodes::isFatal($code) ? "**{$code}**" : $code).' | '.implode(' | ', array_map(static fn (string $c): string => (string) ($byRun[$c] ?? 0), $columns)).' |';
    }
    $lines[] = '';
}

$spent = spent();
$lines[] = sprintf('Потрачено на OpenAI за наряд до этой таблицы (spend.json): **%s** (кап наряда $5.00; кап прогона $%.2f).', money($spent), CAP_USD);
$lines[] = '';
file_put_contents(TABLE_OUT, implode("\n", $lines));
fwrite(STDERR, 'written '.TABLE_OUT."\n");

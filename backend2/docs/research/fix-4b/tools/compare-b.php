<?php

/**
 * FIX-4b — ПРИЁМКА Б, the comparison: two runs of `replay-b.php` over the owner's three gym talks (2DX8QC) — by `main`
 * (FIX-4) and by the branch (FIX-4b) — side by side, move by move, in the scene FIX-4's acceptance read each move in. For a
 * learner's move: said · almost · extra with what went into each window; for a line of the role: the door it named and
 * what the server does with it. The last column says whether the two agree; the other readings of the JSON (`stored`,
 * `rule`) are compared too, and a difference there is printed under the table.
 *
 * Run: php docs/research/fix-4b/tools/compare-b.php docs/research/fix-4b/live/replay-fix4.json \
 *        docs/research/fix-4b/live/replay-fix4b.json > docs/research/fix-4b/live/replay-b.md
 */

declare(strict_types=1);

[, $beforeFile, $afterFile] = $argv + [null, null, null];
if (! is_string($beforeFile) || ! is_string($afterFile)) {
    fwrite(STDERR, "usage: compare-b.php <FIX-4 json> <FIX-4b json>\n");
    exit(1);
}
/** @var list<array<string, mixed>> $before */
$before = json_decode((string) file_get_contents($beforeFile), true, flags: JSON_THROW_ON_ERROR);
/** @var list<array<string, mixed>> $after */
$after = json_decode((string) file_get_contents($afterFile), true, flags: JSON_THROW_ON_ERROR);

/** One reading as a cell: the judge's verdict with the windows, or the door. */
$cell = static function (array $now): string {
    if (array_key_exists('door', $now)) {
        return $now['door'] === null ? '—' : "дверь: {$now['door']}";
    }
    $parts = [];
    foreach (['said' => 'said', 'almost' => 'almost', 'extra' => 'extra'] as $key => $label) {
        foreach ($now[$key] as $id) {
            $parts[] = "{$id} {$label}";
        }
    }
    $values = array_map(static fn (string $id, ?string $v): string => '«'.($v ?? '—').'»', array_keys($now['values'] ?? []), array_values($now['values'] ?? []));

    return ($parts === [] ? 'ничего' : implode(', ', $parts)).($values === [] ? '' : ' · '.implode(', ', $values));
};

$rows = ['| день | ход | реплика | FIX-4 | FIX-4b | совпало |', '|---|---|---|---|---|---|'];
$differences = [];
$same = 0;
$total = 0;
foreach ($before as $t => $talk) {
    foreach ($talk['turns'] as $i => $turn) {
        $other = $after[$t]['turns'][$i] ?? null;
        if ($other === null || $other['index'] !== $turn['index']) {
            $differences[] = "день {$talk['day']}, ход {$turn['index']}: нет такого хода в FIX-4b";

            continue;
        }
        $total++;
        $agree = $turn['now'] === $other['now'];
        $same += $agree ? 1 : 0;
        foreach (['stored', 'rule'] as $reading) {
            if ($turn['now'][$reading] !== $other['now'][$reading]) {
                $differences[] = "день {$talk['day']}, ход {$turn['index']}, чтение `{$reading}`: {$cell($turn['now'][$reading])} → {$cell($other['now'][$reading])}";
            }
        }
        $rows[] = sprintf(
            '| %d | %d %s | %s | %s: %s | %s: %s | %s |',
            $talk['day'], $turn['index'], $turn['kind'] === 'agent' ? 'роль' : 'ученик', str_replace('|', '/', $turn['text']),
            $turn['now']['acceptance']['scene'], $cell($turn['now']['acceptance']),
            $other['now']['acceptance']['scene'], $cell($other['now']['acceptance']),
            $agree ? '✅' : '❌',
        );
    }
}

echo implode("\n", $rows)."\n\n";
echo "Ходов и реплик: {$total}; совпало во всех трёх чтениях (приёмки, записанных чекпойнтов, правила сервера): {$same}.\n";
echo $differences === [] ? "Расхождений нет.\n" : "Расхождения:\n- ".implode("\n- ", $differences)."\n";

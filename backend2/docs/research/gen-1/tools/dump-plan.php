<?php

declare(strict_types=1);

/**
 * GEN-1 — выгрузить план с тестовой базы в читаемый markdown + сырой JSON.
 *
 *   php docs/research/gen-1/tools/dump-plan.php <plan_id> <out.md>
 *
 * Пишет: цель, сцены каркаса (вводка, умения, opening_lines), по каждому дню — пары в порядке
 * цепочки (реплика роли → моя реплика, переводы, ключ говорения), слова и связки с примерами, числа,
 * спасатели, а также строки реестра трат. Рядом кладёт `<out>.json` с тем же содержимым для
 * машинного сравнения.
 */

use Illuminate\Support\Facades\DB;

require __DIR__ . '/bootstrap.php';

[$script, $planId, $out] = $argv + [null, null, null];
if ($out === null) {
    fwrite(STDERR, "usage: dump-plan.php <plan_id> <out.md>\n");
    exit(2);
}

$plan = DB::table('learning_plans')->where('id', $planId)->first();
if ($plan === null) {
    fwrite(STDERR, "no plan {$planId}\n");
    exit(1);
}
$outline = json_decode((string) $plan->outline, true) ?: [];
$days = DB::table('learning_plan_days')->where('plan_id', $planId)->orderBy('day_index')->get();
$spend = DB::table('generation_requests')->where('plan_id', $planId)->orderBy('created_at')->get();

$md = [];
$raw = ['plan_id' => $planId, 'goal' => $plan->goal_text, 'pair' => "{$plan->support_lang}→{$plan->target_lang}", 'level' => $plan->level, 'days' => []];

$md[] = "# {$plan->title}";
$md[] = '';
$md[] = "- план `{$planId}` · {$plan->support_lang}→{$plan->target_lang} · {$plan->level} · событие {$plan->event_date} · статус {$plan->status}";
$md[] = "- цель: «{$plan->goal_text}»";
$md[] = '- сводка: ' . (string) ($outline['goal_summary'] ?? '');
$md[] = '';
$md[] = '## Сцены каркаса (P1)';
$md[] = '';
foreach ($outline['scenes'] ?? [] as $i => $scene) {
    $md[] = '### Сцена ' . ($i + 1) . ' — ' . (string) ($scene['title'] ?? '');
    $md[] = '';
    $md[] = (string) ($scene['intro'] ?? '');
    $md[] = '';
    foreach ($scene['skills'] ?? [] as $k => $skill) {
        // Каркас хранит умения без id — сервер нумерует их по позиции (`PlanOutline::fromArray()`).
        $md[] = '- умение `s' . ($i + 1) . '.' . ($k + 1) . '`: ' . (string) ($skill['outcome'] ?? '') . ' — чек: ' . (string) ($skill['checkpoint'] ?? '');
    }
    $md[] = '- opening_lines: ' . implode(' · ', array_map(static fn ($l): string => '«' . (string) $l . '»', $scene['opening_lines'] ?? []));
    $md[] = '- entities: ' . implode(', ', $scene['entities'] ?? []);
    $md[] = '';
}

foreach ($days as $day) {
    $md[] = "## День {$day->day_index} — {$day->title} ({$day->kind}, {$day->status}, попыток {$day->generation_attempts}, починок {$day->repair_calls})";
    $md[] = '';
    if ($day->fail_code !== null) {
        $md[] = "**Отбой `{$day->fail_code}`:** " . (string) $day->fail_reason;
        $md[] = '';
    }
    if ($day->collection_id === null) {
        $md[] = '_материала нет_';
        $md[] = '';
        continue;
    }

    $terms = [];
    $rows = DB::table('terms as t')
        ->join('collection_items as ci', 'ci.term_id', '=', 't.id')
        ->where('ci.collection_id', $day->collection_id)
        ->orderBy('t.id')
        ->get(['t.id', 't.text', 't.shelf', 't.kind', 't.frame', 't.filler', 't.speaking_key', 't.skill_ref', 't.number_value']);
    $ids = $rows->pluck('id')->all();
    $translations = [];
    foreach (DB::table('term_translations')->whereIn('term_id', $ids)->where('lang', $plan->support_lang)->orderByDesc('is_primary')->get() as $tr) {
        $translations[(string) $tr->term_id] ??= (string) $tr->text;
    }
    $examples = [];
    foreach (DB::table('term_examples')->whereIn('term_id', $ids)->where('scope_collection_id', $day->collection_id)->get() as $ex) {
        $examples[(string) $ex->term_id] = ['example' => (string) $ex->sentence, 'translation' => (string) $ex->sentence_translation];
    }
    foreach ($rows as $row) {
        $terms[(string) $row->id] = [
            'text' => (string) $row->text,
            'translation' => $translations[(string) $row->id] ?? '',
            'shelf' => (string) $row->shelf,
            'frame' => (string) $row->frame,
            'filler' => (string) $row->filler,
            'speaking_key' => $row->speaking_key !== null ? (string) $row->speaking_key : null,
            'skill_ref' => $row->skill_ref !== null ? (string) $row->skill_ref : null,
            'number_value' => $row->number_value !== null ? (string) $row->number_value : null,
            'example' => $examples[(string) $row->id]['example'] ?? null,
            'example_translation' => $examples[(string) $row->id]['translation'] ?? null,
        ];
    }

    $chain = json_decode((string) $day->dialogue, true) ?: [];
    $pairs = [];
    $md[] = '### Пары (по цепочке)';
    $md[] = '';
    $n = 0;
    for ($i = 0; $i < count($chain); $i += 2) {
        $role = $terms[$chain[$i]['term_id'] ?? ''] ?? null;
        $you = $terms[$chain[$i + 1]['term_id'] ?? ''] ?? null;
        $n++;
        $kind = (string) ($chain[$i]['pair'] ?? '?');
        $md[] = "**{$n}. [{$kind}]**";
        $md[] = '- role: «' . ($role['text'] ?? '—') . '» — ' . ($role['translation'] ?? '—');
        $md[] = '- you: «' . ($you['text'] ?? '—') . '» — ' . ($you['translation'] ?? '—')
            . ' · ключ: ' . (isset($you['speaking_key']) ? '`' . $you['speaking_key'] . '`' : '_вся фраза_')
            . ' · ' . (string) ($you['skill_ref'] ?? '');
        $md[] = '';
        $pairs[] = ['kind' => $kind, 'role' => $role, 'you' => $you];
    }

    $byShelf = static fn (string $shelf): array => array_values(array_filter($terms, static fn (array $t): bool => $t['shelf'] === $shelf));

    $md[] = '### Слова и связки';
    $md[] = '';
    foreach (['words', 'chunks'] as $shelf) {
        foreach ($byShelf($shelf) as $t) {
            $md[] = "- [{$shelf}] **{$t['text']}** — {$t['translation']} · пример: «{$t['example']}» — {$t['example_translation']}";
        }
    }
    $md[] = '';
    $md[] = '### Числа на слух';
    $md[] = '';
    foreach ($byShelf('numbers') as $t) {
        $md[] = "- «{$t['text']}» — {$t['translation']} · value `{$t['number_value']}`";
    }
    $md[] = '';
    $rescue = $byShelf('rescue');
    if ($rescue !== []) {
        $md[] = '### Спасатели (сервер)';
        $md[] = '';
        foreach ($rescue as $t) {
            $md[] = "- «{$t['text']}» — {$t['translation']}";
        }
        $md[] = '';
    }

    $raw['days'][] = [
        'day_index' => $day->day_index,
        'title' => $day->title,
        'status' => $day->status,
        'attempts' => $day->generation_attempts,
        'repairs' => $day->repair_calls,
        'fail_code' => $day->fail_code,
        'pairs' => $pairs,
        'words' => $byShelf('words'),
        'chunks' => $byShelf('chunks'),
        'numbers' => $byShelf('numbers'),
    ];
}

$md[] = '## Реестр трат';
$md[] = '';
$md[] = '| вызов | статус | версия | $ |';
$md[] = '|---|---|---|---|';
$total = 0.0;
foreach ($spend as $s) {
    $total += (float) $s->cost_usd;
    $md[] = '| ' . str_replace('|', '\|', (string) $s->prompt) . " | {$s->status} | {$s->prompt_version} | " . number_format((float) $s->cost_usd, 6) . ' |';
}
$md[] = '| **итого** | | | **' . number_format($total, 6) . '** |';
$md[] = '';
$raw['spend'] = ['calls' => $spend->count(), 'total_usd' => round($total, 6)];

file_put_contents($out, implode("\n", $md) . "\n");
file_put_contents(preg_replace('/\.md$/', '', $out) . '.json', json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
fwrite(STDOUT, "wrote {$out} (calls {$spend->count()}, \${$total})\n");

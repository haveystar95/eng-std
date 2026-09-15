<?php

declare(strict_types=1);

/**
 * GEN-2b · «БЫЛО / СТАЛО»: the six GEN-2a days on `lesson_day.v4.4` against the same six on `v4.5`, plus the two new
 * pairs (ro→en, uk→en) — every answer as the model wrote it, no repair, one validator: the code as it is now. NO MODEL
 * IS ASKED HERE: the seam judge's verdicts are read from `judge/` — `v4_4-<slug>.json` / `v4_5-<slug>.json` (judge v1,
 * bought by the GEN-2b hand-over) and `v1_1-<slug>.json` (judge v1.1 on the eight v4.5 days, `tools/judge.php`); a
 * verdict missing for the sentences of a day stops the export.
 *
 * The v4.4 days are read with the judge v1 (the only one they had), the v4.5 days with v1.1 — and with v1 beside it,
 * for the same sentences under both. Written:
 *
 *  - `validator.json` — code → v4.4 (6 days) → v4.5 (6 days) → ro/uk, with examples, units and the share of units
 *    each code fired on (> 20 % on the six v4.5 days — «правило, не модель»); the judge's «no» per day under v1 and v1.1;
 *  - `compare.json` — the server's keys, native seams, listening and the lines side by side;
 *  - `<slug>.md` — the eight v4.5 days for a human, with an empty «оценка» column.
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/gen-2b/tools/export.php
 */

use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonGate;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\NativeSeams;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use App\Modules\Shared\Domain\Service\LanguageName;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$dir = realpath(__DIR__.'/..');
$json = static fn (mixed $value): string => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
$cell = static fn (?string $text): string => str_replace(['|', "\n"], ['\\|', ' '], trim((string) $text));
$levels = ['beginner' => 'начальный', 'intermediate' => 'средний'];
$kinds = ['answer' => 'ответ', 'ask' => 'вопрос ученика', 'rescue' => 'переспрос'];
$six = ['interview', 'rent', 'bank', 'restaurant', 'airport', 'doctor'];

$packs = $app->make(LanguagePacks::class);
$validator = $app->make(LessonValidator::class);
$parser = new LessonParser;

/** The judge's verdict over one answer, as kept in `judge/` — never bought here. */
$judge = static function (Lesson $answer, string $file) use ($dir): array {
    $hash = sha1((string) json_encode(NativeSeams::of($answer), JSON_UNESCAPED_UNICODE));
    $cached = is_file("{$dir}/judge/{$file}") ? json_decode((string) file_get_contents("{$dir}/judge/{$file}"), true) : null;
    if (($cached['hash'] ?? null) !== $hash) {
        throw new RuntimeException("judge/{$file}: no verdict for these sentences — run tools/judge.php (v1.1) first");
    }

    return $cached;
};

/** The judge's «no» as findings — the way the build writes them. */
$seamFindings = static function (array $record, string $native): array {
    $byId = array_column($record['items'], null, 'id');
    $out = [];
    foreach ($record['verdicts'] as $verdict) {
        if (($verdict['reads'] ?? true) === false && isset($byId[$verdict['id']])) {
            $item = $byId[$verdict['id']];
            $out[] = new LessonViolation(LessonCodes::FILLER_NATIVE_SEAM, $item['id'], "«{$item['sentence']}» («{$item['pattern']}» with «{$item['value']}») does not read as {$native}, the seam judge says");
        }
    }

    return $out;
};

/** How many places of a lesson each code is read at — what a share of findings is taken of. */
$units = static function (Lesson $lesson): array {
    $learner = count(array_filter($lesson->exchanges, static fn ($e): bool => $e->learner() !== null));
    $partner = count(array_filter($lesson->exchanges, static fn ($e): bool => $e->partner() !== null));
    $frames = count($lesson->phrases);
    $slotted = count(array_filter($lesson->phrases, static fn ($p): bool => $p->slot !== null));
    $fillers = array_sum(array_map(static fn ($p): int => count($p->fillers()), $lesson->phrases));
    $vocab = count($lesson->vocabulary);

    return [
        'lesson' => 1, 'exchanges' => count($lesson->exchanges), 'partner' => $partner, 'learner' => $learner, 'frames' => $frames,
        'slotted' => $slotted, 'fillers' => $fillers, 'native_seams' => count(NativeSeams::of($lesson)), 'questions' => count($lesson->listening),
        'vocab' => $vocab, 'readings' => $frames + $fillers + $vocab + $learner, 'native_texts' => $learner + $frames + $fillers,
        'answers' => count(array_filter($lesson->exchanges, static fn ($e): bool => $e->kind === ExchangeKind::Answer)),
        'rescues' => count(array_filter($lesson->exchanges, static fn ($e): bool => $e->kind === ExchangeKind::Rescue)),
    ];
};
$unitOf = [
    'dialogue.count' => 'lesson', 'vocab.count' => 'lesson', 'exchange.shape' => 'exchanges', 'exchange.second_question' => 'exchanges',
    'exchange.repeats' => 'exchanges', 'check.shape' => 'exchanges', 'listening.shape' => 'questions', 'pronunciation.script' => 'readings',
    'frame.count' => 'lesson', 'frame.unused' => 'frames', 'frame.too_long' => 'frames', 'frame.no_slot_share' => 'lesson',
    'frame.native_alternatives' => 'frames', 'frame.no_end_punct' => 'frames', 'frame.native_punct' => 'frames', 'frame.unresolved_pronoun' => 'frames',
    'frame.native_agreement' => 'slotted', 'filler.count' => 'frames', 'filler.ungrammatical' => 'fillers',
    'filler.one_in_dialogue' => 'fillers', 'filler.is_clause' => 'fillers', 'filler.article_seam' => 'fillers',
    'filler.native_seam' => 'native_seams', 'line.ne_frame' => 'learner', 'line.too_long' => 'learner', 'line.no_frame' => 'learner',
    'variant.longer' => 'learner', 'learner.restates_partner' => 'answers', 'kind.ask_count' => 'lesson', 'kind.rescue_count' => 'lesson',
    'rescue.not_first' => 'rescues', 'rescue.new_fact' => 'rescues', 'rescue.no_prev' => 'rescues', 'partner.two_questions' => 'partner',
    'partner.too_long' => 'partner', 'partner.closer' => 'partner', 'check.about_learner' => 'exchanges', 'check.verbatim' => 'exchanges',
    'check.listed_alternative_as_wrong' => 'exchanges', 'listening.count' => 'lesson', 'listening.same_exchange' => 'questions',
    'listening.no_learner_value' => 'lesson', 'listening.distractor_not_filler' => 'questions', 'vocab.free_combination' => 'vocab',
    'vocab.everyday_word' => 'vocab', 'vocab.used_in_wrong' => 'vocab', 'vocab.learner_share' => 'lesson', 'vocab.nested' => 'vocab',
    'native.gendered_past' => 'native_texts', 'image_prompt.rule_text' => 'vocab',
];

// ── the days ─────────────────────────────────────────────────────────────────────────────────────────────────────────
$gen2a = array_column(json_decode((string) file_get_contents("{$dir}/../gen-2a/runs.json"), true), null, 'slug');
$gen2b = array_column(json_decode((string) file_get_contents("{$dir}/runs.json"), true), null, 'slug');
$days = [];
foreach ($six as $slug) {
    $scene = DB::table('plan_scenes')->where('id', $gen2a[$slug]['scene_id'])->first();
    $days["v4.4:{$slug}"] = ['version' => 'v4.4', 'slug' => $slug, 'native' => 'ru', 'level' => $gen2a[$slug]['level'], 'answer' => json_decode((string) $scene->lesson_json, true), 'run' => $gen2a[$slug], 'title' => (string) $scene->title_native];
}
foreach ($gen2b as $slug => $run) {
    $raw = json_decode((string) file_get_contents("{$dir}/answers/{$slug}.json"), true);
    $days["v4.5:{$slug}"] = ['version' => 'v4.5', 'slug' => $slug, 'native' => explode('→', $run['pair'])[0], 'level' => $run['level'], 'answer' => $raw, 'run' => $run, 'title' => $run['topic']];
}

$table = [];
foreach (LessonCodes::all() as $code) {
    $table[$code] = ['unit' => $unitOf[$code] ?? '?', 'v4.4' => ['total' => 0, 'days' => [], 'addresses' => 0, 'units' => 0, 'examples' => []], 'v4.5' => ['total' => 0, 'days' => [], 'addresses' => 0, 'units' => 0, 'examples' => []], 'ro/uk' => ['total' => 0, 'days' => [], 'examples' => []]];
}
$totals = [];
$judged = [];
$perDay = [];
foreach ($days as $key => $day) {
    $answer = $parser->parse($day['answer']);
    $context = new LessonValidationContext(8, 8, $packs->for($day['native']), $packs->for('en'));
    $validated = $validator->run($answer, $context);
    $nativeName = LanguageName::of($day['native']);
    $v1 = $judge($answer, str_replace([':', '.'], ['-', '_'], $key).'.json');
    $v11 = $day['version'] === 'v4.5' ? $judge($answer, "v1_1-{$day['slug']}.json") : null;
    $verdict = $v11 ?? $v1;
    $found = [...$validated, ...$seamFindings($verdict, $nativeName)];
    $column = in_array($day['native'], ['ro', 'uk'], true) ? 'ro/uk' : $day['version'];
    $dayUnits = $units($answer);
    foreach (LessonCodes::all() as $code) {
        if ($column !== 'ro/uk') {
            $table[$code][$column]['units'] += $dayUnits[$unitOf[$code]] ?? 0;
        }
    }
    $addresses = [];
    foreach ($found as $f) {
        $table[$f->code][$column]['total']++;
        $table[$f->code][$column]['days'][$day['slug']] = ($table[$f->code][$column]['days'][$day['slug']] ?? 0) + 1;
        if (count($table[$f->code][$column]['examples']) < 4) {
            $table[$f->code][$column]['examples'][] = "{$day['slug']} {$f->address}: {$f->detail}";
        }
        $addresses[$f->code][$f->address] = true;
    }
    if ($column !== 'ro/uk') {
        foreach ($addresses as $code => $set) {
            $table[$code][$column]['addresses'] += $unitOf[$code] === 'lesson' ? 1 : count($set);
        }
    }
    $totals[$column] ??= ['fatal' => 0, 'validator' => 0, 'judge' => 0, 'skipped_codes' => 0];
    $totals[$column]['fatal'] += count(LessonGate::fatal($validated));
    $totals[$column]['validator'] += count($validated);
    $totals[$column]['judge'] += count($found) - count($validated);
    $totals[$column]['skipped_codes'] += count($context->skips->codes());
    $no = static fn (?array $record): ?array => $record === null ? null : array_values(array_map(
        static fn (array $v): string => $v['id'],
        array_filter($record['verdicts'], static fn (array $v): bool => ($v['reads'] ?? true) === false),
    ));
    $judged[$key] = ['sentences' => count($verdict['items']), 'no_v1' => $no($v1), 'no_v1_1' => $no($v11)];
    $perDay[$key] = ['answer' => $answer, 'found' => $found, 'skips' => $context->skips->all(), 'verdict' => $verdict, 'verdict_v1' => $v1, 'units' => $dayUnits, 'context' => $context];
    fwrite(STDOUT, sprintf("%s: %d validator findings (%d fatal) · judge %s «no» %d · skips %d\n", $key, count($validated), count(LessonGate::fatal($validated)), $v11 === null ? 'v1' : 'v1.1', count($found) - count($validated), count($context->skips->codes())));
}
foreach ($table as $code => $row) {
    foreach (['v4.4', 'v4.5'] as $v) {
        $table[$code][$v]['share'] = $row[$v]['units'] > 0 ? round($row[$v]['addresses'] / $row[$v]['units'], 3) : null;
    }
    $table[$code]['rule_not_model'] = ($table[$code]['v4.5']['share'] ?? 0) > 0.2;
    $table[$code]['fatal'] = LessonGate::isFatal($code);
}
file_put_contents("{$dir}/validator.json", $json([
    'days' => ['v4.4' => $six, 'v4.5' => $six, 'ro/uk' => ['airport-ro', 'doctor-uk']],
    'judge' => ['v4.4' => 'lesson_seam_judge.v1', 'v4.5' => 'lesson_seam_judge.v1.1', 'ro/uk' => 'lesson_seam_judge.v1.1', 'per_day' => $judged],
    'totals' => $totals,
    'skips' => array_map(static fn (array $d): array => array_map(static fn ($s): array => $s->toArray(), $d['skips']), $perDay),
    'codes' => $table,
]));

// ── side by side: the server's keys, native seams, listening, the lines ─────────────────────────────────────────────
$compare = [];
foreach ($six as $slug) {
    foreach (['v4.4', 'v4.5'] as $v) {
        $d = $perDay["{$v}:{$slug}"];
        $lesson = $d['answer'];
        $read = LessonAssembly::said($lesson, $d['context']->target);
        $verdicts = array_column($d['verdict']['verdicts'], 'reads', 'id');
        $verdictsV1 = array_column($d['verdict_v1']['verdicts'], 'reads', 'id');
        $compare[$slug][$v] = [
            'keys' => array_values(array_map(static function ($e) use ($read): array {
                $line = $e->learner();
                $frame = $line?->phraseId === null ? null : $read->phrase($line->phraseId);

                return ['step' => $e->step, 'frame' => $frame?->frameTarget, 'line' => $line?->textTarget, 'filler' => $line?->filler, 'key' => $line?->speakingKey];
            }, $read->exchanges)),
            'seams' => array_map(static fn (array $item): array => $item + ['reads' => $verdicts[$item['id']] ?? null] + ($v === 'v4.5' ? ['reads_v1' => $verdictsV1[$item['id']] ?? null] : []), NativeSeams::of($lesson)),
            'listening' => array_map(static fn ($q): array => ['question' => $q->textNative, 'options' => $q->optionsNative, 'right' => $q->correctOption()], $lesson->listening),
            'lines' => array_values(array_map(static fn ($e): array => [
                'step' => $e->step, 'kind' => $e->kind->value,
                'partner' => [$e->partner()?->textTarget, $e->partner()?->textNative],
                'learner' => [$e->learner()?->textTarget, $e->learner()?->textNative],
            ], $lesson->exchanges)),
            'frames' => array_map(static fn ($p): array => [$p->id, $p->frameTarget, $p->frameNative, array_map(static fn ($f): string => "{$f->target} / {$f->native}", $p->fillers())], $lesson->phrases),
            'codes' => array_count_values(array_map(static fn (LessonViolation $f): string => $f->code, $d['found'])),
        ];
    }
}
file_put_contents("{$dir}/compare.json", $json($compare));

// ── the eight v4.5 days for a human ──────────────────────────────────────────────────────────────────────────────────
$gateFile = "{$dir}/gate-2-gpt-5.4.json";
$gates = is_file($gateFile) ? array_column(json_decode((string) file_get_contents($gateFile), true), null, 'slug') : [];
foreach ($gen2b as $slug => $run) {
    $d = $perDay["v4.5:{$slug}"];
    $answer = $d['answer'];
    $served = LessonAssembly::serve($answer, "gen-2b:{$slug}", $d['context']->target);
    $verdicts = array_column($d['verdict']['verdicts'], 'reads', 'id');
    $lessonCall = array_values(array_filter($run['calls'], static fn (array $c): bool => $c['call'] === 'lesson'));
    $last = $lessonCall[count($lessonCall) - 1] ?? ['tokens_in' => 0, 'tokens_out' => 0, 'cost_usd' => '0', 'latency_ms' => 0, 'model' => '?'];
    $fatal = LessonGate::fatal($d['found']);

    $md = [];
    $md[] = "# GEN-2b · «{$run['topic']}» — {$slug} ({$run['pair']}, {$levels[$run['level']]})";
    $md[] = '';
    $md[] = "Цель плана (слова ученика): «{$run['goal']}»";
    $md[] = '';
    $md[] = sprintf('Ученик: %s · собеседник: %s (%s)', $answer->learnerRoleNative, $answer->exchanges[0]->partner()?->roleNative ?? '—', $answer->roleGender?->value === 'female' ? 'женщина' : 'мужчина');
    $md[] = '';
    $md[] = sprintf('Промт `lesson_day.v4.5` · модель `%s` · вызов урока $%s · %.1f с · токены вход/выход %d/%d · попыток урока: %d · находок валидатора и судьи в ответе модели: %d (фатальных %d) · судья швов `lesson_seam_judge.v1.1`',
        $last['model'], $last['cost_usd'], $last['latency_ms'] / 1000, $last['tokens_in'], $last['tokens_out'], (int) $run['lesson_attempts'], count($d['found']), count($fatal));
    $md[] = '';
    $md[] = '> Колонка «оценка» пустая — ставит Ден: **✓** / **так не говорят** / **слишком длинно** / **не то слово**. Это ответ модели БЕЗ починок (починки порога — внизу). Реплики — как их написала модель и получит приложение; наполнение реплики и ключ — серверные: наполнение найдено по тексту реплики среди наполнений её каркаса, ключ взят из каркаса. «Судья» — вердикт судьи швов о собранной фразе на родном.';
    $md[] = '';
    $md[] = '## Сценарий диалога';
    $md[] = '';
    $md[] = '| # | вид обмена | кто | реплика | перевод | каркас · наполнение | ключ | оценка |';
    $md[] = '|---|---|---|---|---|---|---|---|';
    foreach ($served->exchanges as $exchange) {
        foreach ($exchange->messages as $message) {
            $who = $message->isLearner() ? "{$message->roleNative} (ученик)" : "{$message->roleNative} (собеседник)";
            $frame = '';
            if ($message->isLearner()) {
                $phrase = $message->phraseId === null ? null : $served->phrase($message->phraseId);
                $frame = $message->phraseId === null ? '—' : $message->phraseId.' · '.($message->filler
                    ?? ($phrase !== null && FrameText::hasSlot($phrase->frameTarget) ? '**не каркас ни с одним наполнением**' : '—'));
            }
            $md[] = sprintf('| %d | %s | %s | %s | %s | %s | %s | |', $exchange->step, $kinds[$exchange->kind->value], $cell($who), $cell($message->textTarget), $cell($message->textNative), $cell($frame), $cell($message->isLearner() ? $message->speakingKey : ''));
        }
    }
    $md[] = '';
    $md[] = '## Каркасы';
    $md[] = '';
    foreach ($served->phrases as $phrase) {
        $lines = $served->linesOf($phrase->id);
        $uses = array_map(static fn (array $u): int => $u['exchange']->step, $lines);
        $firstLine = $lines[0]['message'] ?? null;
        $md[] = sprintf('### %s · %s — «%s»', $phrase->id, $kinds[$phrase->kind->value], $phrase->frameTarget);
        $md[] = '';
        $md[] = sprintf('На родном: «%s» · чтение: «%s» · окно: %s · звучит в обменах: %s', $phrase->frameNative, $phrase->pronunciationNative,
            $phrase->slot === null ? 'нет' : '«'.$phrase->slot->hintNative.'»', $uses === [] ? 'нигде' : implode(', ', $uses));
        $md[] = '';
        $said = static fn (string $text, ?string $line): string => $line === null ? $text : FrameText::withEndMarkOf($text, $line);
        if ($phrase->slot === null) {
            $md[] = '| фраза | оценка |';
            $md[] = '|---|---|';
            $md[] = sprintf('| %s | |', $cell($said(trim($phrase->frameTarget), $firstLine?->textTarget)));
        } else {
            $md[] = '| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |';
            $md[] = '|---|---|---|---|---|---|---|---|';
            foreach ($phrase->slot->fillers as $n => $filler) {
                $reads = $verdicts[$phrase->id.'.f'.($n + 1)] ?? null;
                $md[] = sprintf('| %s | %s | %s | %s | %s | %s | %s | |', $cell($filler->target), $cell($filler->native), $cell($filler->pronunciationNative), $filler->inDialogue ? 'да' : '—',
                    $cell($said(FrameText::fill($phrase->frameTarget, $filler->target), $firstLine?->textTarget)),
                    $cell($said(FrameText::fill($phrase->frameNative, $filler->native), $firstLine?->textNative)), $reads === null ? '—' : ($reads ? 'читается' : '**не читается**'));
            }
        }
        $md[] = '';
    }
    $md[] = '## Проверки обменов';
    $md[] = '';
    $md[] = '| # | вопрос | варианты (✓ — верный) | пояснение | оценка |';
    $md[] = '|---|---|---|---|---|';
    foreach ($served->exchanges as $exchange) {
        $options = [];
        foreach ($exchange->check->options as $k => $option) {
            $options[] = ($k === $exchange->check->correctOptionIndex ? '✓ ' : '')."{$option->textTarget} / {$option->textNative}";
        }
        $md[] = sprintf('| %d | %s / %s | %s | %s | |', $exchange->step, $cell($exchange->check->textTarget), $cell($exchange->check->textNative), $cell(implode(' · ', $options)), $cell($exchange->check->explanationNative));
    }
    $md[] = '';
    $md[] = '## Слушаю весь визит (listening)';
    $md[] = '';
    $md[] = '| # | вопрос | варианты (✓ — верный) | пояснение | оценка |';
    $md[] = '|---|---|---|---|---|';
    foreach ($served->listening as $k => $question) {
        $options = [];
        foreach ($question->optionsNative as $n => $option) {
            $options[] = ($n === $question->correctOptionIndex ? '✓ ' : '').$option;
        }
        $md[] = sprintf('| L%d | %s | %s | %s | |', $k + 1, $cell($question->textNative), $cell(implode(' · ', $options)), $cell($question->explanationNative));
    }
    $md[] = '';
    $md[] = '## Словарь';
    $md[] = '';
    $md[] = '| id | слово | вид | перевод | чтение | определение | где звучит | картинка (запрос) | оценка |';
    $md[] = '|---|---|---|---|---|---|---|---|---|';
    foreach ($served->vocabulary as $item) {
        $md[] = sprintf('| %s | %s | %s | %s | %s | %s | %s | %s | |', $item->id, $cell($item->termTarget), $item->kind === 'chunk' ? 'связка' : 'слово', $cell($item->translationNative),
            $cell($item->pronunciationNative), $cell($item->definitionTarget), implode(', ', $item->usedIn), $cell($item->imagePrompt ?? '—'));
    }
    $md[] = '';
    $md[] = '## Находки (ответ модели без починок; фатальные держат день до P2R)';
    $md[] = '';
    if ($d['found'] === []) {
        $md[] = 'Нет.';
    } else {
        $md[] = '| код | порог | адрес | что |';
        $md[] = '|---|---|---|---|';
        foreach ($d['found'] as $f) {
            $md[] = sprintf('| `%s` | %s | %s | %s |', $f->code, LessonGate::isFatal($f->code) ? '**фатально**' : 'предупреждение', $f->address, $cell($f->detail));
        }
    }
    $md[] = '';
    $md[] = '## Не проверено — у языка нет пакета (`lang.pack_missing`, не находка)';
    $md[] = '';
    if ($d['skips'] === []) {
        $md[] = 'Всё проверено: пакеты обоих языков пары полные.';
    } else {
        $md[] = '| код | сторона пары | язык | чего нет в пакете |';
        $md[] = '|---|---|---|---|';
        foreach ($d['skips'] as $skip) {
            $md[] = sprintf('| `%s` | %s | %s | %s |', $skip->code, $skip->side->value === 'native' ? 'родной' : 'цель', $skip->language, implode(', ', $skip->keys));
        }
    }
    $md[] = '';
    $md[] = '## Порог (фатальные коды → P2R, не больше двух карточек)';
    $md[] = '';
    $repairs = array_values(array_filter($run['calls'], static fn (array $c): bool => $c['call'] === 'repair'));
    $md[] = '- **Живая сборка GEN-2b** (валидатор до сдачи, P2R на `gpt-5.4-mini`): '.($repairs === []
        ? sprintf('фатальных нет — P2R не звался; итог: %s.', $run['status'])
        : sprintf('P2R %s; итог: %s%s.',
            implode('; ', array_map(static fn (array $c): string => "{$c['asked']['address']} ({$c['asked']['kind']}, \${$c['cost_usd']}, {$c['latency_ms']} мс: ".implode(', ', array_unique(array_column($c['asked']['findings'], 'code'))).')', $repairs)),
            $run['status'], $run['fail_reason'] ? " — {$run['fail_reason']}" : ''));
    $gate = $gates[$slug] ?? null;
    if ($gate !== null) {
        $outcome = match (true) {
            $gate['outcome'] === 'passes' => 'фатальных нет, проходит без починки',
            $gate['outcome'] === 'passes after repair' => 'проходит после починки',
            default => str_replace('failed — ', '**failed** — ', $gate['outcome']),
        };
        $md[] = sprintf('- **Порог доработки** (валидатор доработки, P2R на `gpt-5.4`): %s%s.', $outcome,
            $gate['cards_asked'] === [] ? '' : ' · карточки '.implode(', ', $gate['cards_asked']).' · $'.$gate['repair_cost_usd'].' · '.$gate['repair_latency_ms'].' мс');
    }
    $md[] = '';
    file_put_contents("{$dir}/{$slug}.md", implode("\n", $md));
    fwrite(STDOUT, "{$slug}.md written\n");
}
fwrite(STDOUT, "validator.json, compare.json written\n");

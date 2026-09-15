<?php

declare(strict_types=1);

/**
 * GEN-2b · «БЫЛО / СТАЛО»: the six GEN-2a days on `lesson_day.v4.4` against the same six on `v4.5`, plus the two new
 * pairs (ro→en, uk→en) — every answer as the model wrote it, no repair, one validator: the code as it is now.
 *
 * For each day: the validator's findings, what the pair's packs could not check, and the seam judge's verdicts over
 * the answer (one call a day; a v4.5 day whose answer passed the gate untouched reuses the verdict its build bought,
 * any other day's verdict is bought once and kept in `judge/`). Written:
 *
 *  - `validator.json` — code → v4.4 (6 days) → v4.5 (6 days) → ro/uk, with examples, units and the share of units
 *    each code fired on (> 20 % on the six v4.5 days — «правило, не модель»);
 *  - `compare.json` — keys, native seams, listening and the interview's lines side by side;
 *  - `<slug>.md` — the eight v4.5 days for a human, with an empty «оценка» column.
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/gen-2b/tools/export.php
 */

use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Service\LessonSeamJudge;
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
@mkdir("{$dir}/judge");
$json = static fn (mixed $value): string => json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n";
$cell = static fn (?string $text): string => str_replace(['|', "\n"], ['\\|', ' '], trim((string) $text));
$levels = ['beginner' => 'начальный', 'intermediate' => 'средний'];
$kinds = ['answer' => 'ответ', 'ask' => 'вопрос ученика', 'rescue' => 'переспрос'];
$six = ['interview', 'rent', 'bank', 'restaurant', 'airport', 'doctor'];

$packs = $app->make(LanguagePacks::class);
$validator = $app->make(LessonValidator::class);
$parser = new LessonParser;

/** The seam judge over one answer — the build's own verdict when it read these very sentences, else one call, kept. */
$judge = static function (Lesson $answer, string $native, string $cacheKey, array $bought = []) use ($app, $dir): array {
    $items = NativeSeams::of($answer);
    $file = "{$dir}/judge/{$cacheKey}.json";
    $hash = sha1((string) json_encode($items, JSON_UNESCAPED_UNICODE));
    if (is_file($file)) {
        $cached = json_decode((string) file_get_contents($file), true);
        if (($cached['hash'] ?? null) === $hash) {
            return $cached;
        }
    }
    foreach ($bought as $call) {
        if (sha1((string) json_encode($call['asked']['items'] ?? null, JSON_UNESCAPED_UNICODE)) === $hash) {
            $record = ['hash' => $hash, 'source' => 'the build', 'items' => $items, 'verdicts' => $call['payload']['verdicts'] ?? [], 'cost_usd' => $call['cost_usd'], 'latency_ms' => $call['latency_ms'], 'model' => $call['model'], 'tokens_in' => $call['tokens_in'], 'tokens_out' => $call['tokens_out']];
            file_put_contents($file, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

            return $record;
        }
    }
    $reply = $app->make(PlanModelPort::class)->judgeNativeSeams(new NativeSeamJudgeRequest($native, $items));
    $record = ['hash' => $hash, 'source' => 'export', 'items' => $items, 'verdicts' => $reply->payload['verdicts'] ?? [], 'cost_usd' => $reply->costUsd, 'latency_ms' => $reply->latencyMs, 'model' => $reply->model, 'tokens_in' => $reply->tokensIn, 'tokens_out' => $reply->tokensOut];
    file_put_contents($file, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

    return $record;
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
    'frame.native_alternatives' => 'frames', 'frame.native_punct' => 'frames', 'frame.unresolved_pronoun' => 'frames',
    'frame.native_agreement' => 'slotted', 'filler.count' => 'frames', 'filler.ungrammatical' => 'fillers',
    'filler.one_in_dialogue' => 'fillers', 'filler.is_clause' => 'fillers', 'filler.article_seam' => 'fillers',
    'filler.native_seam' => 'native_seams', 'line.ne_frame' => 'learner', 'line.too_long' => 'learner', 'line.no_frame' => 'learner',
    'key.not_in_line' => 'learner', 'key.contains_filler' => 'learner', 'key.no_content_word' => 'learner', 'key.too_long' => 'learner',
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
    $days["v4.4:{$slug}"] = ['version' => 'v4.4', 'slug' => $slug, 'native' => 'ru', 'level' => $gen2a[$slug]['level'], 'answer' => json_decode((string) $scene->lesson_json, true), 'bought' => [], 'run' => $gen2a[$slug], 'seed' => (string) $scene->id, 'title' => (string) $scene->title_native];
}
foreach ($gen2b as $slug => $run) {
    $raw = json_decode((string) file_get_contents("{$dir}/answers/{$slug}.json"), true);
    $bought = array_values(array_filter($run['calls'], static fn (array $c): bool => $c['call'] === 'judge'));
    $days["v4.5:{$slug}"] = ['version' => 'v4.5', 'slug' => $slug, 'native' => explode('→', $run['pair'])[0], 'level' => $run['level'], 'answer' => $raw, 'bought' => $bought, 'run' => $run, 'seed' => "gen-2b:{$slug}", 'title' => $run['topic']];
}

$table = [];
foreach (LessonCodes::all() as $code) {
    $table[$code] = ['unit' => $unitOf[$code] ?? '?', 'v4.4' => ['total' => 0, 'days' => [], 'addresses' => 0, 'units' => 0, 'examples' => []], 'v4.5' => ['total' => 0, 'days' => [], 'addresses' => 0, 'units' => 0, 'examples' => []], 'ro/uk' => ['total' => 0, 'days' => [], 'examples' => []]];
}
$perDay = [];
foreach ($days as $key => $day) {
    $answer = $parser->parse($day['answer']);
    $context = new LessonValidationContext(8, 8, $packs->for($day['native']), $packs->for('en'));
    $found = $validator->run($answer, $context);
    $nativeName = LanguageName::of($day['native']);
    $verdict = $judge($answer, $nativeName, str_replace([':', '.'], ['-', '_'], $key), $day['bought']);
    $found = [...$found, ...$seamFindings($verdict, $nativeName)];
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
    $perDay[$key] = ['answer' => $answer, 'found' => $found, 'skips' => $context->skips->all(), 'verdict' => $verdict, 'units' => $dayUnits];
    fwrite(STDOUT, sprintf("%s: %d findings (%d fatal) · skips %d · judge %s %s $%s\n", $key, count($found), count(LessonGate::fatal($found)), count($context->skips->codes()), $verdict['source'], $verdict['model'], $verdict['cost_usd']));
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
    'skips' => array_map(static fn (array $d): array => array_map(static fn ($s): array => $s->toArray(), $d['skips']), $perDay),
    'codes' => $table,
]));

// ── side by side: keys, native seams, listening, the interview's lines ───────────────────────────────────────────────
$compare = [];
foreach ($six as $slug) {
    foreach (['v4.4', 'v4.5'] as $v) {
        $d = $perDay["{$v}:{$slug}"];
        $lesson = $d['answer'];
        $verdicts = array_column($d['verdict']['verdicts'], 'reads', 'id');
        $compare[$slug][$v] = [
            'keys' => array_values(array_map(static function ($e) use ($lesson): array {
                $line = $e->learner();
                $frame = $line?->phraseId === null ? null : $lesson->phrase($line->phraseId);

                return ['step' => $e->step, 'frame' => $frame?->frameTarget, 'line' => $line?->textTarget, 'key' => $line?->speakingKey];
            }, $lesson->exchanges)),
            'seams' => array_map(static fn (array $item): array => $item + ['reads' => $verdicts[$item['id']] ?? null], NativeSeams::of($lesson)),
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
foreach ($gen2b as $slug => $run) {
    $d = $perDay["v4.5:{$slug}"];
    $answer = $d['answer'];
    $served = LessonAssembly::serve($answer, "gen-2b:{$slug}");
    $verdicts = array_column($d['verdict']['verdicts'], 'reads', 'id');
    $lessonCall = array_values(array_filter($run['calls'], static fn (array $c): bool => $c['call'] === 'lesson'));
    $last = $lessonCall[count($lessonCall) - 1] ?? ['tokens_in' => 0, 'tokens_out' => 0, 'cost_usd' => '0', 'latency_ms' => 0, 'model' => '?'];

    $md = [];
    $md[] = "# GEN-2b · «{$run['topic']}» — {$slug} ({$run['pair']}, {$levels[$run['level']]})";
    $md[] = '';
    $md[] = "Цель плана (слова ученика): «{$run['goal']}»";
    $md[] = '';
    $md[] = sprintf('Ученик: %s · собеседник: %s (%s)', $answer->learnerRoleNative, $answer->exchanges[0]->partner()?->roleNative ?? '—', $answer->roleGender?->value === 'female' ? 'женщина' : 'мужчина');
    $md[] = '';
    $md[] = sprintf('Промт `lesson_day.v4.5` · модель `%s` · вызов урока $%s · %.1f с · токены вход/выход %d/%d · попыток урока: %d · находок валидатора и судьи в ответе модели: %d (фатальных %d) · порог в сборке: %s',
        $last['model'], $last['cost_usd'], $last['latency_ms'] / 1000, $last['tokens_in'], $last['tokens_out'], (int) $run['lesson_attempts'], count($d['found']), count(LessonGate::fatal($d['found'])),
        $run['status'] === 'ready' ? 'урок прошёл' : "урок failed ({$run['fail_reason']})");
    $md[] = '';
    $md[] = '> Колонка «оценка» пустая — ставит Ден: **✓** / **так не говорят** / **слишком длинно** / **не то слово**. Это ответ модели БЕЗ починок; реплики ученика — как их получит приложение (сервер собирает их из каркаса и наполнения); если модель написала иначе, её текст — в скобках. «Судья» — вердикт судьи швов о собранной фразе на родном.';
    $md[] = '';
    $md[] = '## Сценарий диалога';
    $md[] = '';
    $md[] = '| # | вид обмена | кто | реплика | перевод | каркас · наполнение | ключ | оценка |';
    $md[] = '|---|---|---|---|---|---|---|---|';
    foreach ($served->exchanges as $i => $exchange) {
        $written = $answer->exchanges[$i];
        foreach ($exchange->messages as $j => $message) {
            $who = $message->isLearner() ? "{$message->roleNative} (ученик)" : "{$message->roleNative} (собеседник)";
            $text = $message->textTarget;
            $modelText = $written->messages[$j]->textTarget ?? $text;
            if ($modelText !== $text) {
                $text .= " (модель: «{$modelText}»)";
            }
            $frame = $message->isLearner() ? ($message->phraseId === null ? '—' : $message->phraseId.' · '.($message->filler ?? '—')) : '';
            $md[] = sprintf('| %d | %s | %s | %s | %s | %s | %s | |', $exchange->step, $kinds[$exchange->kind->value], $cell($who), $cell($text), $cell($message->textNative), $cell($frame), $cell($message->isLearner() ? $message->speakingKey : ''));
        }
    }
    $md[] = '';
    $md[] = '## Каркасы';
    $md[] = '';
    foreach ($served->phrases as $phrase) {
        $uses = array_map(static fn (array $u): int => $u['exchange']->step, $served->linesOf($phrase->id));
        $md[] = sprintf('### %s · %s — «%s»', $phrase->id, $kinds[$phrase->kind->value], $phrase->frameTarget);
        $md[] = '';
        $md[] = sprintf('На родном: «%s» · чтение: «%s» · окно: %s · звучит в обменах: %s', $phrase->frameNative, $phrase->pronunciationNative,
            $phrase->slot === null ? 'нет' : '«'.$phrase->slot->hintNative.'»', $uses === [] ? 'нигде' : implode(', ', $uses));
        $md[] = '';
        if ($phrase->slot === null) {
            $md[] = '| фраза | оценка |';
            $md[] = '|---|---|';
            $md[] = sprintf('| %s | |', $cell($phrase->frameTarget));
        } else {
            $md[] = '| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | судья | оценка |';
            $md[] = '|---|---|---|---|---|---|---|---|';
            foreach ($phrase->slot->fillers as $n => $filler) {
                $reads = $verdicts[$phrase->id.'.f'.($n + 1)] ?? null;
                $md[] = sprintf('| %s | %s | %s | %s | %s | %s | %s | |', $cell($filler->target), $cell($filler->native), $cell($filler->pronunciationNative), $filler->inDialogue ? 'да' : '—',
                    $cell(FrameText::fill($phrase->frameTarget, $filler->target)), $cell(FrameText::fill($phrase->frameNative, $filler->native)), $reads === null ? '—' : ($reads ? 'читается' : '**не читается**'));
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
    $md[] = '- **Живая сборка** (валидатор до двух уточнений отчёта §3, P2R на `gpt-5.4-mini`): '.($repairs === []
        ? sprintf('фатальных нет — P2R не звался; итог: %s.', $run['status'])
        : sprintf('P2R %s; итог: %s%s.',
            implode('; ', array_map(static fn (array $c): string => "{$c['asked']['address']} ({$c['asked']['kind']}, \${$c['cost_usd']}, {$c['latency_ms']} мс: ".implode(', ', array_unique(array_column($c['asked']['findings'], 'code'))).')', $repairs)),
            $run['status'], $run['fail_reason'] ? " — {$run['fail_reason']}" : ''));
    foreach (['gpt-5.4', 'gpt-5.4-mini'] as $model) {
        $gateFile = "{$dir}/gate-{$model}.json";
        $gate = is_file($gateFile) ? (array_column(json_decode((string) file_get_contents($gateFile), true), null, 'slug')[$slug] ?? null) : null;
        if ($gate !== null) {
            $md[] = sprintf('- **Порог на валидаторе сдачи, P2R на `%s`**: %s%s.', $model, $gate['outcome'],
                $gate['cards_asked'] === [] ? '' : ' · карточки '.implode(', ', $gate['cards_asked']).' · $'.$gate['repair_cost_usd'].' · '.$gate['repair_latency_ms'].' мс');
        }
    }
    $md[] = '';
    file_put_contents("{$dir}/{$slug}.md", implode("\n", $md));
    fwrite(STDOUT, "{$slug}.md written\n");
}
fwrite(STDOUT, "validator.json, compare.json written\n");

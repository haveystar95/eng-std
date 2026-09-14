<?php

declare(strict_types=1);

/**
 * GEN-2a · THE READABLE DAYS AND THE VALIDATOR TABLE.
 *
 * For every run in `docs/research/gen-2a/runs.json`: the scene's stored answer is read back, the served
 * lesson put together from it (lines from frames, answers at their shuffled places — what the phone
 * gets), the validator run over the answer as it is NOW, and `docs/research/gen-2a/<slug>.md` written for
 * a human with an empty «оценка» column. The code table across all days goes to `validator.json`.
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/gen-2a/tools/export.php
 */

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Service\FrameText;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$dir = realpath(__DIR__.'/..');
$runs = json_decode((string) file_get_contents($dir.'/runs.json'), true);
$cell = static fn (?string $text): string => str_replace(['|', "\n"], ['\\|', ' '], trim((string) $text));
$levels = ['beginner' => 'начальный', 'intermediate' => 'средний'];
$kinds = ['answer' => 'ответ', 'ask' => 'вопрос ученика', 'rescue' => 'переспрос'];

$table = [];
foreach (LessonCodes::all() as $code) {
    $table[$code] = ['total' => 0, 'days' => [], 'examples' => []];
}

foreach ($runs as $run) {
    $scene = DB::table('plan_scenes')->where('id', $run['scene_id'])->first();
    $plan = DB::table('plans')->where('id', $run['plan_id'])->first();
    if ($scene === null || $plan === null) {
        fwrite(STDERR, "no scene for {$run['slug']}\n");
        continue;
    }
    $answer = (new LessonParser)->parse(json_decode((string) $scene->lesson_json, true));
    $served = LessonAssembly::serve($answer, (string) $scene->id);
    $findings = (new LessonValidator)->run($answer, new LessonValidationContext(count($answer->vocabulary), count($answer->exchanges), 'ru', 'en', null));
    foreach ($findings as $f) {
        $table[$f->code]['total']++;
        $table[$f->code]['days'][$run['slug']] = ($table[$f->code]['days'][$run['slug']] ?? 0) + 1;
        if (count($table[$f->code]['examples']) < 3) {
            $table[$f->code]['examples'][] = "{$run['slug']} {$f->address}: {$f->detail}";
        }
    }
    $call = $run['lesson_calls'][0] ?? ['tokens_in' => 0, 'tokens_out' => 0];

    $md = [];
    $md[] = "# GEN-2a · «{$scene->title_native}» — {$run['slug']} ({$levels[$run['level']]})";
    $md[] = '';
    $md[] = "Цель плана (слова ученика): «{$run['goal']}»";
    $md[] = '';
    $md[] = sprintf('Сцена: «%s» / «%s» · ученик: %s · собеседник: %s (%s)', $scene->title_native, $scene->title_target, $answer->learnerRoleNative, $answer->exchanges[0]->partner()?->roleNative ?? '—', $answer->roleGender?->value === 'female' ? 'женщина' : 'мужчина');
    $md[] = '';
    $md[] = sprintf('Промт `%s` · модель `%s` · урок $%s · %.1f с · токены вход/выход %d/%d · одна попытка: %s · находок валидатора: %d',
        $scene->prompt_version_lesson, $scene->model_lesson, $scene->cost_usd_lesson, $scene->latency_ms_lesson / 1000, $call['tokens_in'], $call['tokens_out'],
        (int) $scene->attempts_lesson === 1 ? 'да' : 'нет ('.$scene->attempts_lesson.')', count($findings));
    $md[] = '';
    $md[] = '> Колонка «оценка» пустая — ставит Ден: **✓** / **так не говорят** / **слишком длинно** / **не то слово**. Реплики ученика — как их получит приложение (сервер собирает их из каркаса и наполнения); если модель написала иначе, её текст — в скобках.';
    $md[] = '';
    $md[] = '## Сценарий диалога';
    $md[] = '';
    $md[] = '| # | вид обмена | кто | реплика | перевод | каркас · наполнение | оценка |';
    $md[] = '|---|---|---|---|---|---|---|';
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
            $md[] = sprintf('| %d | %s | %s | %s | %s | %s | |', $exchange->step, $kinds[$exchange->kind->value], $cell($who), $cell($text), $cell($message->textNative), $cell($frame));
        }
    }
    $md[] = '';
    $md[] = '## Каркасы';
    $md[] = '';
    foreach ($served->phrases as $phrase) {
        $uses = array_map(static fn (array $u): int => $u['exchange']->step, $served->linesOf($phrase->id));
        $md[] = sprintf('### %s · %s — «%s»', $phrase->id, $kinds[$phrase->kind->value], $phrase->frameTarget);
        $md[] = '';
        $md[] = sprintf('По-русски: «%s» · чтение: «%s» · окно: %s · звучит в обменах: %s', $phrase->frameNative, $phrase->pronunciationNative,
            $phrase->slot === null ? 'нет' : '«'.$phrase->slot->hintNative.'»', $uses === [] ? 'нигде' : implode(', ', $uses));
        $md[] = '';
        if ($phrase->slot === null) {
            $md[] = '| фраза | оценка |';
            $md[] = '|---|---|';
            $md[] = sprintf('| %s | |', $cell($phrase->frameTarget));
        } else {
            $md[] = '| наполнение | перевод | чтение | в диалоге | собранная фраза | собранный перевод | оценка |';
            $md[] = '|---|---|---|---|---|---|---|';
            foreach ($phrase->slot->fillers as $filler) {
                $md[] = sprintf('| %s | %s | %s | %s | %s | %s | |', $cell($filler->target), $cell($filler->native), $cell($filler->pronunciationNative), $filler->inDialogue ? 'да' : '—',
                    $cell(FrameText::fill($phrase->frameTarget, $filler->target)), $cell(FrameText::fill($phrase->frameNative, $filler->native)));
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
            $options[] = ($k === $exchange->check->correctOptionIndex ? '✓ ' : '') . "{$option->textTarget} / {$option->textNative}";
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
    $md[] = '## Находки валидатора (режим наблюдения — день вышел)';
    $md[] = '';
    if ($findings === []) {
        $md[] = 'Нет.';
    } else {
        $md[] = '| код | адрес | что |';
        $md[] = '|---|---|---|';
        foreach ($findings as $f) {
            $md[] = sprintf('| `%s` | %s | %s |', $f->code, $f->address, $cell($f->detail));
        }
    }
    $md[] = '';
    file_put_contents("{$dir}/{$run['slug']}.md", implode("\n", $md));
    fwrite(STDOUT, "{$run['slug']}.md: ".count($findings)." findings\n");
}

file_put_contents("{$dir}/validator.json", json_encode($table, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
fwrite(STDOUT, "validator.json written\n");

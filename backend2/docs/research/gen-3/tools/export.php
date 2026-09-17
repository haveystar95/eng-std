<?php

declare(strict_types=1);

/**
 * GEN-3 · «БЫЛО / СТАЛО» — THE TABLE AND THE DAYS FOR A HUMAN. NO MODEL IS ASKED HERE.
 *
 * For each topic: day 1 (v4.6), day 2 as it was (v4.5, the pre-order code) and day 2 as it is (v4.6). Both days 2 are read
 * by ONE validator — the code of the order — against the SAME story so far (day 1 as the database holds it), so «было» is
 * counted by the rules it never knew: how many words and frames of day 1 it taught again, the twins, the adjacent frames,
 * the second questions, the abbreviations, the fatal codes. The spend of each day is read from `api_request_logs` — the
 * rows of the day's own calls, matched by their token counts inside the day's window (a window alone also took the judge
 * call of the topic before, logged in the second the day started), the same for both codes, cached tokens at their
 * cached rate (`ModelCost`).
 *
 * Written: `compare.json` (every number), `table.md` (the rows of the report), `<slug>.md` (the three days, final — what
 * the learner would be dealt — with the findings of the answer as the model wrote it).
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/gen-3/tools/export.php
 */

use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonGate;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Application\Service\LessonContexts;
use App\Modules\Plan\Application\Service\LessonRequests;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Shared\Domain\Service\ModelCost;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if ((string) config('database.connections.pgsql.database') !== 'wordtrainer_e2e_test') {
    fwrite(STDERR, "Refusing: only wordtrainer_e2e_test.\n");
    exit(1);
}

$dir = realpath(__DIR__.'/..');
$topics = require __DIR__.'/topics.php';
$parser = new LessonParser;
$validator = new LessonValidator;
$cost = new ModelCost;
$levels = ['beginner' => 'начальный', 'intermediate' => 'средний'];
$kinds = ['answer' => 'ответ', 'ask' => 'вопрос ученика', 'rescue' => 'переспрос'];
$json = static fn (mixed $v): string => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION)."\n";
$read = static fn (string $file): mixed => is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

/**
 * The spend of a day: its calls as the recorder wrote them (model, tokens in and out), each found in the request log
 * within the day's window give or take ten seconds, for the cached tokens the old code did not keep; calls, tokens in /
 * cached / out, price with the cache and without it, by ModelCost.
 *
 * @param  list<array<string, mixed>>  $calls
 */
$spend = static function (array $calls, string $from, string $to) use ($cost): array {
    $rows = DB::table('api_request_logs')->where('direction', 'outbound')->where('purpose', 'plan')
        ->where('occurred_at', '>=', (new DateTimeImmutable($from))->modify('-10 seconds')->format(DATE_ATOM))
        ->where('occurred_at', '<=', (new DateTimeImmutable($to))->modify('+10 seconds')->format(DATE_ATOM))
        ->orderBy('occurred_at')->get(['id', 'response_body']);
    $usages = [];
    foreach ($rows as $row) {
        $body = json_decode((string) $row->response_body, true);
        if (is_array($body['usage'] ?? null)) {
            $usages[(string) $row->id] = ['model' => (string) ($body['model'] ?? ''), 'usage' => $body['usage']];
        }
    }
    $out = ['calls' => 0, 'tokens_in' => 0, 'cached' => 0, 'tokens_out' => 0, 'cost_cached' => 0.0, 'cost_no_cache' => 0.0, 'unmatched' => 0];
    foreach ($calls as $call) {
        $match = null;
        foreach ($usages as $id => $logged) {
            if ((int) ($logged['usage']['prompt_tokens'] ?? -1) === (int) $call['tokens_in']
                && (int) ($logged['usage']['completion_tokens'] ?? -1) === (int) $call['tokens_out']) {
                $match = $id;
                break;
            }
        }
        if ($match === null) {
            $out['unmatched']++;

            continue;
        }
        $usage = $usages[$match]['usage'];
        $model = $usages[$match]['model'];
        unset($usages[$match]);
        $in = (int) $usage['prompt_tokens'];
        $cached = (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0);
        $outTokens = (int) $usage['completion_tokens'];
        $out['calls']++;
        $out['tokens_in'] += $in;
        $out['cached'] += $cached;
        $out['tokens_out'] += $outTokens;
        $out['cost_cached'] += (float) $cost->estimate($model, $in, $outTokens, $cached);
        $out['cost_no_cache'] += (float) $cost->estimate($model, $in, $outTokens);
    }

    return $out;
};

/** @return array<string, int> code → findings */
$tally = static fn (array $found): array => array_count_values(array_map(static fn (LessonViolation $v): string => $v->code, $found));

$compare = [];
$rows = [];
foreach ($topics as $slug => [$goal, $level]) {
    $run = $read("{$dir}/runs/{$slug}.json");
    if ($run === null) {
        continue;
    }
    $plan = $app->make(PlanRepository::class)->findById(PlanId::fromString($run['plan_id']));
    $sceneTwo = $plan?->scene(PlanSceneId::fromString($run['scene_ids'][1]));
    if ($plan === null || $sceneTwo === null) {
        continue;
    }
    $request = $app->make(LessonRequests::class)->for($plan, $sceneTwo);
    $pack = $app->make(\App\Modules\Plan\Domain\Check\Language\LanguagePacks::class)->for('en');

    $days = [];
    foreach (['day1' => 'day1', 'before' => 'day2-v4.5', 'after' => 'day2-v4.6'] as $key => $file) {
        $raw = $read("{$dir}/answers/{$slug}-{$file}.json");
        $final = $read("{$dir}/final/{$slug}-{$file}.json");
        $record = $key === 'day1' ? $run['day1'] : $read("{$dir}/runs/{$slug}-{$file}.json");
        if ($record === null || $raw === null) {
            continue;
        }
        // Day 1 has no story; both days 2 are read against day 1.
        $context = $key === 'day1'
            ? $app->make(LessonContexts::class)->of(new \App\Modules\Plan\Application\Dto\LessonRequest(
                $request->topic, $request->topicDescription, $request->targetLanguage, $request->nativeLanguage, $request->level,
                $request->learnerGender, $request->vocabularyCount, $request->dialogueCount, $plan->lessonRoles($plan->scene(PlanSceneId::fromString($run['scene_ids'][0]))),
                new \App\Modules\Plan\Domain\Lesson\EarlierDays, [], 'en', 'ru',
            ))
            : $app->make(LessonContexts::class)->of($request);
        $rawLesson = $parser->parse($raw);
        $found = $validator->run($rawLesson, $context);
        $finalLesson = $final === null ? null : $parser->parse($final);
        $finalFound = $finalLesson === null ? [] : $validator->run($finalLesson, $app->make(LessonContexts::class)->of($key === 'day1' ? $request : $request));
        $calls = $record['calls'] ?? [];
        $spent = $spend($calls, (string) $record['started_at'], (string) $record['finished_at']);
        $days[$key] = [
            'status' => $record['status'] === 'illustrating' || $record['status'] === 'ready' ? 'ready' : 'failed',
            'fail_reason' => $record['fail_reason'],
            'raw_codes' => $tally($found),
            'raw_fatal' => count(LessonGate::fatal($found)),
            'final_codes' => $tally($finalFound),
            'repairs' => count(array_filter($calls, static fn (array $c): bool => $c['call'] === 'repair')),
            'repair_addresses' => array_values(array_map(static fn (array $c): string => $c['asked']['address'], array_filter($calls, static fn (array $c): bool => $c['call'] === 'repair'))),
            'lesson_calls' => count(array_filter($calls, static fn (array $c): bool => $c['call'] === 'lesson')),
            'wall_s' => $record['wall_s'],
            'spend' => $spent,
            'cached_share' => $spent['tokens_in'] > 0 ? round($spent['cached'] / $spent['tokens_in'], 3) : 0,
            'raw' => $rawLesson,
            'final' => $finalLesson,
            'found' => $found,
            'story_words' => $key === 'day1' ? [] : array_values(array_filter(array_map(static fn ($v): ?string => in_array(FrameText::identity($v->termTarget), array_map(static fn (array $w): string => FrameText::identity($w['term']), $request->earlierDays->words()), true) ? $v->termTarget : null, $rawLesson->vocabulary))),
        ];
    }
    if (! isset($days['day1'], $days['before'], $days['after'])) {
        continue;
    }

    $compare[$slug] = array_map(static fn (array $d): array => array_diff_key($d, ['raw' => 0, 'final' => 0, 'found' => 0]), $days);
    $c = static fn (array $d, string $code): int => $d['raw_codes'][$code] ?? 0;
    foreach (['before' => 'было (v4.5)', 'after' => 'стало (v4.6)'] as $key => $label) {
        $d = $days[$key];
        $rows[] = sprintf('| %s | %s | %d | %d | %d | %d | %d | %d | %d | %d | %d | %s | %s | $%.4f | %.0f %% | %.0f с |',
            $slug, $label, $c($d, LessonCodes::VOCAB_KNOWN_REPEAT), $c($d, LessonCodes::FRAME_KNOWN_REPEAT), $c($d, LessonCodes::FRAME_KNOWN_NATIVE_REPEAT),
            $c($d, LessonCodes::FRAME_TWIN), $c($d, LessonCodes::FRAME_ADJACENT_REPEAT), $c($d, LessonCodes::EXCHANGE_SECOND_QUESTION),
            $c($d, LessonCodes::VOCAB_ABBREVIATION), $d['raw_fatal'], $d['repairs'],
            $d['status'].($d['fail_reason'] ? " ({$d['fail_reason']})" : ''),
            $d['story_words'] === [] ? '—' : implode(', ', $d['story_words']),
            $d['spend']['cost_cached'], $d['cached_share'] * 100, $d['wall_s']);
    }

    // ── the three days for a human ────────────────────────────────────────────────────────────────────────────────────
    $md = ["# GEN-3 · {$slug} (ru→en, {$levels[$level]})", '', "Цель плана (слова ученика): «{$goal}»", ''];
    $md[] = "Роль ученика в плане: {$run['learner_role']}. Сцена 1: «{$run['scenes'][0]['title_native']}» ({$run['scenes'][0]['partner']}); сцена 2: «{$run['scenes'][1]['title_native']}» ({$run['scenes'][1]['partner']}).";
    $md[] = '';
    $md[] = '> Каждый день — урок, каким его получил бы ученик (прошедший порог; при `failed` — ответ модели как написан). Находки — по ответу модели ДО починок, одним валидатором наряда и против одного и того же дня 1, так что «было» посчитано правилами, которых v4.5 не знал. Факты сюжета (цены, договорённости) и роли код не проверяет — их читает архитектор.';
    foreach (['day1' => 'День 1 — `lesson_day.v4.6`', 'before' => 'День 2 — БЫЛО: `lesson_day.v4.5`, код до наряда (без ролей и без EARLIER_DAYS)', 'after' => 'День 2 — СТАЛО: `lesson_day.v4.6` (роли плана, EARLIER_DAYS = день 1)'] as $key => $title) {
        $d = $days[$key];
        $lesson = $d['final'] ?? $d['raw'];
        $served = LessonAssembly::said($lesson, $pack);
        $md[] = '';
        $md[] = "## {$title}";
        $md[] = '';
        $md[] = sprintf('Итог: **%s**%s · починок P2R: %d%s · вызовов урока: %d · цена дня $%.4f (без скидки кэша $%.4f) · из кэша %.0f %% входа · %.0f с · ученик: %s · собеседник: %s (%s)',
            $d['status'], $d['fail_reason'] ? " ({$d['fail_reason']})" : '', $d['repairs'], $d['repair_addresses'] === [] ? '' : ' ('.implode(', ', $d['repair_addresses']).')',
            $d['lesson_calls'], $d['spend']['cost_cached'], $d['spend']['cost_no_cache'], $d['cached_share'] * 100, $d['wall_s'],
            $lesson->learnerRoleNative, $lesson->exchanges[0]->partner()?->roleNative ?? '—', $lesson->roleGender?->value === 'female' ? 'женщина' : 'мужчина');
        $md[] = '';
        $md[] = '### Сценарий диалога';
        $md[] = '';
        $md[] = '| # | вид обмена | кто | реплика | перевод | каркас · наполнение |';
        $md[] = '|---|---|---|---|---|---|';
        foreach ($served->exchanges as $exchange) {
            foreach ($exchange->messages as $message) {
                $md[] = sprintf('| %d | %s | %s | %s | %s | %s |', $exchange->step, $kinds[$exchange->kind->value], $message->roleNative.($message->isLearner() ? ' (ученик)' : ' (собеседник)'),
                    $message->textTarget, $message->textNative, $message->isLearner() ? (($message->phraseId ?? '—').($message->filler !== null ? " · {$message->filler}" : '')) : '');
            }
        }
        $md[] = '';
        $md[] = '### Каркасы';
        $md[] = '';
        $md[] = '| id | вид | каркас | на родном | наполнения (в диалоге — жирным) |';
        $md[] = '|---|---|---|---|---|';
        foreach ($served->phrases as $phrase) {
            $md[] = sprintf('| %s | %s | %s | %s | %s |', $phrase->id, $kinds[$phrase->kind->value], $phrase->frameTarget, $phrase->frameNative,
                implode(' · ', array_map(static fn ($f): string => ($f->inDialogue ? "**{$f->target}**" : $f->target)." / {$f->native}", $phrase->fillers())) ?: '—');
        }
        $md[] = '';
        $md[] = '### Проверки обменов';
        $md[] = '';
        $md[] = '| # | вопрос | варианты (✓ — верный) |';
        $md[] = '|---|---|---|';
        foreach ($served->exchanges as $exchange) {
            $md[] = sprintf('| %d | %s / %s | %s |', $exchange->step, $exchange->check->textTarget, $exchange->check->textNative,
                implode(' · ', array_map(static fn ($o, int $i): string => ($i === $exchange->check->correctOptionIndex ? '✓ ' : '').$o->textTarget.' / '.$o->textNative, $exchange->check->options, array_keys($exchange->check->options))));
        }
        $md[] = '';
        $md[] = '### Слушаю весь визит';
        $md[] = '';
        foreach ($served->listening as $i => $q) {
            $md[] = sprintf('- L%d. %s — %s', $i + 1, $q->textNative, implode(' · ', array_map(static fn (string $o, int $j): string => ($j === $q->correctOptionIndex ? '✓ ' : '').$o, $q->optionsNative, array_keys($q->optionsNative))));
        }
        $md[] = '';
        $md[] = '### Словарь';
        $md[] = '';
        $md[] = '| id | слово | вид | перевод | где звучит |';
        $md[] = '|---|---|---|---|---|';
        foreach ($served->vocabulary as $item) {
            $md[] = sprintf('| %s | %s | %s | %s | %s |', $item->id, $item->termTarget, $item->kind === 'chunk' ? 'связка' : 'слово', $item->translationNative, implode(', ', $item->usedIn));
        }
        $md[] = '';
        $md[] = '### Находки в ответе модели (до починок)';
        $md[] = '';
        if ($d['found'] === []) {
            $md[] = 'нет';
        } else {
            $md[] = '| код | порог | адрес | что |';
            $md[] = '|---|---|---|---|';
            foreach ($d['found'] as $f) {
                $md[] = sprintf('| `%s` | %s | %s | %s |', $f->code, LessonGate::isFatal($f->code) ? '**фатальная**' : 'предупреждение', $f->address, str_replace('|', '/', $f->detail));
            }
        }
    }
    file_put_contents("{$dir}/{$slug}.md", implode("\n", $md)."\n");
}

file_put_contents("{$dir}/compare.json", $json($compare));
file_put_contents("{$dir}/table.md", implode("\n", [
    '| тема | день 2 | термины дня 1 | каркас known | native | twin | adjacent | second_question | abbreviation | фатальных | починок | итог | повторённые слова | цена дня | из кэша | время |',
    '|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|',
    ...$rows,
])."\n");
fwrite(STDOUT, 'topics: '.count($compare)."\n");

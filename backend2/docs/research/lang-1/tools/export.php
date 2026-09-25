<?php

declare(strict_types=1);

/**
 * LANG-1 · THE DAYS FOR A HUMAN AND THE TABLE OF THE PAIRS. NO MODEL IS ASKED HERE, NO DATABASE IS READ: everything comes
 * from what `live.php` wrote — `runs/<pair>.json`, `answers/<pair>.json`, `final/<pair>.json` under
 * `docs/research/lang-1/` (or `DRY_DIR`).
 *
 * Written:
 *
 *  - `days/<native>-<target>.md` — day 1 of the pair in the GEN-3 `doctor.md` format, with the readings: the lesson the
 *    learner would be dealt (stored, as it passed the gate; when `failed` — the model's answer as written) read by the
 *    server (`LessonAssembly::said`, the target's pack) — the dialogue, the frames, every filler with its assembled native
 *    sentence and the seam judge's verdict on it (matched by the filler's id, as `LessonSeamJudge` matches it), the checks,
 *    the listening, the words; the findings of the RAW answer before any repair (as `live.php` read them in the production
 *    context of the pair), the checks not run for want of a pack key (`lang.pack_missing`), the seam judge's «нет»;
 *  - `table.md` — a row per pair and a totals row (the «на глаз» cell is left `—`, a human fills it);
 *  - `summary.json` — every number of the table and more, per pair and in total.
 *
 * THE SPEND: the plan's calls (`plan`) and the day's (`lesson` + `repair` + `judge`) as the recorder wrote them, each call
 * once — `cost_usd_lesson` of the scene (which already holds the repairs and the judge) is shown beside it as a cross-check,
 * never added. A pair whose plan threw keeps its plan calls as the plan's.
 *
 * `--packs-now`: the raw answer of every pair is ALSO read again by `LessonValidator` with the packs as they are NOW
 * (`config/lesson/lang/*.php` of the code this runs on), in the context `LessonContexts::of()` builds from the request
 * `live.php` stored (day 1 — no earlier days; the pair's language codes), and diffed against the findings of the run: a
 * section «Находки с пакетами LANG-1» in each day and a column in the table. On the packs of the run the diff is empty —
 * the reading is the run's.
 *
 * A pair whose files cannot be read is written as an error (its md and its row) and the export goes on.
 *
 *   docker exec -w /wt -e DB_DATABASE=wordtrainer_e2e_test wt_lang1 php docs/research/lang-1/tools/export.php [--packs-now]
 *
 * (`DB_DATABASE` only so the booted app never even points at the live base — nothing here queries it.)
 */

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Service\LessonContexts;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Check\Language\PackSkip;
use App\Modules\Plan\Domain\Check\LessonGate;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonAssembly;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\LessonRoles;
use App\Modules\Plan\Domain\Lesson\NativeSeams;
use App\Modules\Plan\Domain\Service\FrameText;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\Service\LanguageCatalog;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$args = array_slice($argv, 1);
$unknown = array_diff($args, ['--packs-now']);
if ($unknown !== []) {
    fwrite(STDERR, 'unknown argument(s): '.implode(' ', $unknown)."\nusage: export.php [--packs-now]\n");
    exit(1);
}
$packsNow = in_array('--packs-now', $args, true);

$dry = trim((string) getenv('DRY_DIR'));
$dir = $dry === '' ? (string) realpath(__DIR__.'/..') : (str_starts_with($dry, '/') ? $dry : base_path($dry));
if (! is_dir("{$dir}/runs")) {
    fwrite(STDERR, "no runs/ under {$dir} — run live.php first\n");
    exit(1);
}
if (! is_dir("{$dir}/days")) {
    mkdir("{$dir}/days", 0775, true);
}

$order = ['ru-pl', 'ru-ro', 'ru-es', 'ru-it', 'ru-de', 'ru-fr', 'uk-en', 'be-en', 'pl-en', 'ro-en', 'es-en', 'it-en', 'de-en', 'fr-en'];
$pairs = array_map(static fn (string $f): string => basename($f, '.json'), glob("{$dir}/runs/*.json") ?: []);
usort($pairs, static function (string $a, string $b) use ($order): int {
    $ia = array_search($a, $order, true);
    $ib = array_search($b, $order, true);

    return [$ia === false ? PHP_INT_MAX : $ia, $a] <=> [$ib === false ? PHP_INT_MAX : $ib, $b];
});

$packs = $app->make(LanguagePacks::class);
$validator = $app->make(LessonValidator::class);
$contexts = $app->make(LessonContexts::class);
$parser = new LessonParser;
$kinds = ['answer' => 'ответ', 'ask' => 'вопрос ученика', 'rescue' => 'переспрос'];
$sides = ['native' => 'родной', 'target' => 'целевой'];
$json = static fn (mixed $v): string => json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION | JSON_INVALID_UTF8_SUBSTITUTE)."\n";
$read = static fn (string $file): mixed => is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
/** A table cell: one line, its `|` escaped — every cell of every table goes through it, model text or not. */
$cell = static fn (mixed $text): string => str_replace(['|', "\r\n", "\n", "\r"], ['\\|', ' ', ' ', ' '], trim((string) $text));
/** A line of running text (a list item, the Итог): one line. */
$line = static fn (mixed $text): string => str_replace(["\r\n", "\n", "\r"], ' ', trim((string) $text));
$endonym = static fn (string $code): string => LanguageCatalog::entry($code)['endonym'] ?? $code;
$usd = static fn (float $v): string => '$'.number_format($v, 4, '.', '');
$secs = static fn (mixed $v): string => $v === null ? '—' : number_format((float) $v, 1, '.', '').' с';
$kindOf = static fn (array $c): string => (string) ($c['call'] ?? '');

/**
 * @param  list<array<string, mixed>>  $calls
 * @param  list<string>  $kindsOf
 */
$sum = static function (array $calls, array $kindsOf) use ($kindOf): float {
    $s = 0.0;
    foreach ($calls as $c) {
        if (in_array($kindOf($c), $kindsOf, true)) {
            $s += (float) ($c['cost_usd'] ?? 0);
        }
    }

    return $s;
};

/** @param list<array{code: string}> $rows  code → n, most first */
$tally = static function (array $rows): array {
    $n = array_count_values(array_map(static fn (array $r): string => (string) $r['code'], $rows));
    arsort($n);

    return $n;
};
$compact = static fn (array $tallied): string => implode(', ', array_map(static fn (string $code, int $n): string => "{$code}×{$n}", array_keys($tallied), $tallied));

/** @param list<array{code: string, side: string}> $skips  distinct codes skipped on each side */
$skipCount = static function (array $skips): array {
    $out = ['native' => [], 'target' => []];
    foreach ($skips as $s) {
        $out[$s['side']][$s['code']] = true;
    }

    return ['native' => count($out['native']), 'target' => count($out['target'])];
};

/** The request of day 1 as `live.php` stored it — what `LessonContexts::of()` needs to read the answer again. */
$requestOf = static fn (array $r): LessonRequest => new LessonRequest(
    topic: (string) $r['topic'],
    topicDescription: (string) $r['topic_description'],
    targetLanguage: (string) $r['target_language'],
    nativeLanguage: (string) $r['native_language'],
    level: PlanLevel::from((string) $r['level']),
    learnerGender: $r['learner_gender'] === null ? null : VoiceGender::from((string) $r['learner_gender']),
    vocabularyCount: (int) $r['vocabulary_count'],
    dialogueCount: (int) $r['dialogue_count'],
    roles: new LessonRoles((string) $r['roles']['learnerTarget'], (string) $r['roles']['learnerNative'], (string) $r['roles']['partnerTarget'], (string) $r['roles']['partnerNative']),
    earlierDays: new EarlierDays,
    targetLangCode: (string) $r['target_lang_code'],
    nativeLangCode: (string) $r['native_lang_code'],
);

/** A finding row as a table line. */
$findingLine = static fn (array $f, string $prefix = ''): string => sprintf('| %s`%s` | %s | %s | %s |', $prefix, $cell($f['code']), LessonGate::isFatal((string) $f['code']) ? '**фатальная**' : 'предупреждение', $cell($f['address']), $cell($f['detail']));
/** A pack skip as a table line. */
$skipLine = static fn (array $s): string => sprintf('| `%s` | %s | %s | %s |', $cell($s['code']), $sides[$s['side']] ?? $cell($s['side']), $cell($s['language']), $cell(implode(', ', (array) $s['keys'])));

$rows = [];
$summary = [];
$totals = ['pairs' => 0, 'ready' => 0, 'failed' => 0, 'other' => 0, 'errors' => 0, 'raw_fatal' => 0, 'raw_warnings' => 0, 'pack_missing_native' => 0, 'pack_missing_target' => 0,
    'pack_missing_unknown' => 0, 'judge_no' => 0, 'judge_all' => 0, 'repairs' => 0, 'plan_usd' => 0.0, 'day_usd' => 0.0, 'all_usd' => 0.0, 'plan_s' => 0.0, 'day_s' => 0.0,
    'now_fatal' => 0, 'now_warnings' => 0, 'now_pack_missing_native' => 0, 'now_pack_missing_target' => 0];

foreach ($pairs as $pair) {
    $totals['pairs']++;
    $native = explode('-', $pair)[0] ?? $pair;
    $target = explode('-', $pair)[1] ?? '?';

    try {
        $run = $read("{$dir}/runs/{$pair}.json");
        if (! is_array($run)) {
            throw new RuntimeException("runs/{$pair}.json is not a JSON object");
        }
        $native = (string) ($run['native'] ?? $native);
        $target = (string) ($run['target'] ?? $target);
        $raw = $read("{$dir}/answers/{$pair}.json");
        $final = $read("{$dir}/final/{$pair}.json");
        $day = is_array($run['day1'] ?? null) ? $run['day1'] : null;
        $error = $run['error'] ?? null;

        // ── the numbers: every call once — the plan's, then the day's ────────────────────────────────────────────────
        // A pair that threw before its day was written keeps the calls it made in `calls_at_error`: the plan's kind goes
        // to the plan (an older run file put a plan that threw there), the rest to the day.
        $errorCalls = $day === null ? (array) ($run['calls_at_error'] ?? []) : [];
        $planCalls = [...(array) ($run['plan']['calls'] ?? []), ...array_values(array_filter($errorCalls, static fn (array $c): bool => $kindOf($c) === 'plan'))];
        $dayCalls = $day !== null ? (array) ($day['calls'] ?? []) : array_values(array_filter($errorCalls, static fn (array $c): bool => $kindOf($c) !== 'plan'));
        $planUsd = $sum($planCalls, ['plan']);
        $lessonUsd = $sum($dayCalls, ['lesson']);
        $repairUsd = $sum($dayCalls, ['repair']);
        $judgeUsd = $sum($dayCalls, ['judge']);
        $dayUsd = $lessonUsd + $repairUsd + $judgeUsd;
        $tokensIn = array_sum(array_map(static fn (array $c): int => (int) ($c['tokens_in'] ?? 0), $dayCalls));
        $tokensCached = array_sum(array_map(static fn (array $c): int => (int) ($c['cached_tokens'] ?? 0), $dayCalls));
        $cachedShare = $tokensIn > 0 ? $tokensCached / $tokensIn : 0.0;
        $planS = $run['plan']['wall_s'] ?? null;
        $dayS = $day['wall_s'] ?? ($run['day_wall_s_at_error'] ?? null);
        $repairs = array_values(array_filter($dayCalls, static fn (array $c): bool => $kindOf($c) === 'repair'));
        $lessonCalls = count(array_filter($dayCalls, static fn (array $c): bool => $kindOf($c) === 'lesson'));
        // Calls that threw (a timeout, a vendor error) — recorded by live.php with their error and no payload.
        $brokenCalls = array_values(array_filter([...$planCalls, ...$dayCalls], static fn (array $c): bool => ($c['error'] ?? null) !== null));
        $status = $error !== null ? 'error' : match ($day['status'] ?? null) {
            'ready', 'illustrating' => 'ready',
            null => '—',
            default => (string) $day['status'],
        };
        $failReason = $day['fail_reason'] ?? null;

        // No `raw_check` — the pair threw before its answer was read again: nothing is known of its findings or its skips.
        $rawCheck = is_array($run['raw_check'] ?? null) ? $run['raw_check'] : ['violations' => [], 'fatal' => 0, 'skips' => [], 'skips_known' => false, 'parse_error' => null];
        $rawViolations = $rawCheck['violations'] ?? [];
        $rawFatal = array_values(array_filter($rawViolations, static fn (array $f): bool => LessonGate::isFatal((string) $f['code'])));
        $rawWarnings = array_values(array_filter($rawViolations, static fn (array $f): bool => ! LessonGate::isFatal((string) $f['code'])));
        $skips = $rawCheck['skips'] ?? [];
        // A run file without the flag (older) knew its skips when its answer parsed.
        $skipsKnown = (bool) ($rawCheck['skips_known'] ?? (($rawCheck['parse_error'] ?? null) === null && is_array($raw)));
        $skipped = $skipCount($skips);

        // The seam judge: the sentences as asked, the verdicts as the build reads them (a string id sent, a boolean, the first).
        $judgeCalls = array_values(array_filter($dayCalls, static fn (array $c): bool => $kindOf($c) === 'judge'));
        $judgeCall = $judgeCalls === [] ? null : $judgeCalls[count($judgeCalls) - 1];
        $judgeItems = [];
        foreach ((array) ($judgeCall['asked']['items'] ?? []) as $item) {
            $judgeItems[(string) $item['id']] = $item;
        }
        $verdicts = [];
        foreach ((array) ($judgeCall['payload']['verdicts'] ?? []) as $v) {
            $id = is_array($v) ? ($v['id'] ?? null) : null;
            $reads = is_array($v) ? ($v['reads'] ?? null) : null;
            if (is_string($id) && is_bool($reads) && isset($judgeItems[$id]) && ! array_key_exists($id, $verdicts)) {
                $verdicts[$id] = $reads;
            }
        }
        $judgeNo = array_keys(array_filter($verdicts, static fn (bool $r): bool => ! $r));

        // ── the lesson as the learner would be dealt it ──────────────────────────────────────────────────────────────
        $roles = isset($run['request']['roles']) ? new LessonRoles(
            (string) $run['request']['roles']['learnerTarget'], (string) $run['request']['roles']['learnerNative'],
            (string) $run['request']['roles']['partnerTarget'], (string) $run['request']['roles']['partnerNative'],
        ) : null;
        $lesson = null;
        $lessonSource = null;
        if (is_array($final)) {
            try {
                $lesson = $parser->parse($final);
                $lessonSource = 'stored';
            } catch (Throwable) {
            }
        }
        if ($lesson === null && is_array($raw)) {
            try {
                $lesson = $parser->parse($raw);
                $lesson = $roles === null ? $lesson : $lesson->withRoles($roles);
                $lessonSource = 'raw';
            } catch (Throwable) {
            }
        }
        $targetPack = $packs->for($target);
        $served = $lesson === null ? null : LessonAssembly::said($lesson, $targetPack);
        $seamSentences = [];
        if ($lesson !== null) {
            foreach (NativeSeams::of($lesson) as $item) {
                $seamSentences[$item['id']] = $item;
            }
        }

        // ── --packs-now: the raw answer read again with the packs as they are now ────────────────────────────────────
        $now = null;
        if ($packsNow) {
            $now = ['done' => false, 'note' => null, 'violations' => [], 'skips' => [], 'added' => [], 'gone' => [], 'codes_added' => [], 'codes_gone' => []];
            if (! is_array($raw)) {
                $now['note'] = 'нет ответа модели';
            } elseif (! isset($run['request'])) {
                $now['note'] = 'прогон не записал запрос урока';
            } elseif (! is_array($run['raw_check'] ?? null)) {
                $now['note'] = 'пара оборвалась до проверки ответа — сравнивать не с чем';
            } elseif ((int) ($run['request']['earlier_days'] ?? 0) !== 0) {
                $now['note'] = 'у дня есть история — повторная проверка без базы её не восстановит';
            } else {
                try {
                    $request = $requestOf($run['request']);
                    $context = $contexts->of($request);
                    $found = $validator->run($parser->parse($raw)->withRoles($request->roles), $context);
                    $now['violations'] = array_map(static fn (LessonViolation $v): array => $v->toArray(), $found);
                    $now['skips'] = array_map(static fn (PackSkip $s): array => $s->toArray(), $context->skips->all());
                    $key = static fn (array $f): string => $f['code'].'|'.$f['address'].'|'.$f['detail'];
                    $before = array_map($key, $rawViolations);
                    $after = array_map($key, $now['violations']);
                    $now['added'] = array_values(array_filter($now['violations'], static fn (array $f): bool => ! in_array($key($f), $before, true)));
                    $now['gone'] = array_values(array_filter($rawViolations, static fn (array $f): bool => ! in_array($key($f), $after, true)));
                    $tb = $tally($rawViolations);
                    $ta = $tally($now['violations']);
                    foreach (array_unique([...array_keys($tb), ...array_keys($ta)]) as $code) {
                        $d = ($ta[$code] ?? 0) - ($tb[$code] ?? 0);
                        if ($d > 0) {
                            $now['codes_added'][$code] = $d;
                        } elseif ($d < 0) {
                            $now['codes_gone'][$code] = -$d;
                        }
                    }
                    $now['done'] = true;
                } catch (Throwable $e) {
                    $now['note'] = 'не разобран: '.$e->getMessage();
                }
            }
            $now['fatal'] = count(array_filter($now['violations'], static fn (array $f): bool => LessonGate::isFatal((string) $f['code'])));
            $now['warnings'] = count($now['violations']) - $now['fatal'];
            $now['pack_missing'] = $skipCount($now['skips']);
        }

        // ── the day for a human ──────────────────────────────────────────────────────────────────────────────────────
        $md = [sprintf('# LANG-1 · %s→%s (%s→%s, начальный)', $endonym($native), $endonym($target), $native, $target), ''];
        $md[] = 'Цель плана (слова ученика): «'.$line($run['goal'] ?? '—').'»';
        $md[] = '';
        if (isset($run['learner_role'])) {
            $scenes = $run['scenes'] ?? [];
            $sceneText = static fn (array $s): string => '«'.$line($s['title_native'] ?? '—').'» ('.$line($s['partner_target'] ?? '—').' / '.$line($s['partner_native'] ?? '—').')';
            $md[] = sprintf('Роль ученика в плане: %s / %s. %s', $line($run['learner_role']['target'] ?? '—'), $line($run['learner_role']['native'] ?? '—'),
                implode('; ', array_map(static fn (array $s, int $i): string => ($i === 0 ? 'Сцена ' : 'сцена ').($s['order'] ?? $i + 1).': '.$sceneText($s), $scenes, array_keys($scenes))).'.');
            $md[] = '';
        }
        if ($error !== null) {
            $md[] = '**Прогон пары оборвался** (этап `'.$cell($run['error_phase'] ?? '?').'`): '.$line($error);
            $md[] = '';
        }
        $md[] = '> День 1 — урок, каким его получил бы ученик (прошедший порог, как записан в сцену; при `failed` — ответ модели как написан), прочитанный сервером: наполнение реплики найдено по её тексту. «Чтение» — как модель записала звучание целевого текста буквами родного. Находки — по ответу модели ДО починок, валидатором кода прогона в контексте боя этой пары. «Не проверено» — проверки, для которых у пакета языка нет ключей (`lang.pack_missing`, не находка). Цена — вызовы плана и дня по одному разу. Факты сюжета и роли код не проверяет.';
        $md[] = '';

        $repairText = $repairs === [] ? '' : ' ('.implode('; ', array_map(static fn (array $c): string => $line($c['asked']['address'] ?? '?').': '.implode(', ', array_unique(array_map(static fn (array $f): string => (string) ($f['code'] ?? '?'), (array) ($c['asked']['findings'] ?? [])))), $repairs)).')';
        $gender = match ($lesson?->roleGender) {
            VoiceGender::Female => 'женщина',
            VoiceGender::Male => 'мужчина',
            default => 'пол не указан',
        };
        $partnerRole = ($served?->exchanges[0] ?? null)?->partner()?->roleNative ?? ($run['scenes'][0]['partner_native'] ?? '—');
        $md[] = sprintf('Итог: **%s**%s · починок P2R: %d%s · вызовов урока: %d%s · план %s · %s · день %s (урок %s · починки %s · судья %s) · из кэша %.0f %% входа · %s · всего %s · ученик: %s · собеседник: %s (%s)',
            $status, $failReason ? ' ('.$line($failReason).')' : '', count($repairs), $repairText, $lessonCalls,
            $brokenCalls === [] ? '' : ' · оборвались: '.implode(', ', array_map(static fn (array $c): string => $kindOf($c), $brokenCalls)),
            $usd($planUsd), $secs($planS), $usd($dayUsd), $usd($lessonUsd), $usd($repairUsd), $usd($judgeUsd), $cachedShare * 100, $secs($dayS), $usd($planUsd + $dayUsd),
            $line($lesson?->learnerRoleNative ?? ($run['learner_role']['native'] ?? '—')), $line($partnerRole), $gender);
        $md[] = '';
        if ($day !== null) {
            $pv = $run['prompt_versions'] ?? [];
            $md[] = sprintf('Промты: план `%s` · урок `%s` · починка `%s` · судья `%s` · модель урока `%s` · попыток урока: %s · в сцене записано $%s (урок, починки и судья — сверка, не слагаемое)',
                $run['plan']['prompt_version'] ?? ($pv['plan'] ?? '—'), $day['prompt_version'] ?? ($pv['lesson'] ?? '—'), $pv['repair'] ?? '—', $pv['judge'] ?? '—',
                $day['model'] ?? '—', $day['attempts'] ?? '—', $day['cost_usd_lesson'] ?? '—');
            $md[] = '';
        }

        if ($served !== null) {
            $md[] = '### Сценарий диалога';
            $md[] = '';
            $md[] = '| # | вид обмена | кто | реплика | перевод | чтение | каркас · наполнение |';
            $md[] = '|---|---|---|---|---|---|---|';
            foreach ($served->exchanges as $exchange) {
                foreach ($exchange->messages as $message) {
                    $frame = '';
                    if ($message->isLearner()) {
                        $phrase = $message->phraseId === null ? null : $served->phrase($message->phraseId);
                        $frame = $message->phraseId === null ? '—' : $message->phraseId.' · '.($message->filler
                            ?? ($phrase !== null && FrameText::hasSlot($phrase->frameTarget) ? '**не каркас ни с одним наполнением**' : '—'));
                    }
                    $md[] = sprintf('| %d | %s | %s | %s | %s | %s | %s |', $exchange->step, $cell($kinds[$exchange->kind->value] ?? $exchange->kind->value),
                        $cell($message->roleNative.($message->isLearner() ? ' (ученик)' : ' (собеседник)')), $cell($message->textTarget), $cell($message->textNative),
                        $message->isLearner() ? $cell($message->pronunciationNative ?? '—') : '', $cell($frame));
                }
            }
            $md[] = '';
            $md[] = '### Каркасы';
            $md[] = '';
            $md[] = '| id | вид | каркас | на родном | чтение | наполнения (в диалоге — жирным) |';
            $md[] = '|---|---|---|---|---|---|';
            foreach ($served->phrases as $phrase) {
                $md[] = sprintf('| %s | %s | %s | %s | %s | %s |', $cell($phrase->id), $cell($kinds[$phrase->kind->value] ?? $phrase->kind->value), $cell($phrase->frameTarget), $cell($phrase->frameNative), $cell($phrase->pronunciationNative),
                    $cell(implode(' · ', array_map(static fn ($f): string => ($f->inDialogue ? "**{$f->target}**" : $f->target)." / {$f->native}", $phrase->fillers())) ?: '—'));
            }
            $md[] = '';
            $md[] = '### Наполнения';
            $md[] = '';
            $md[] = '| каркас | наполнение | перевод | чтение | в диалоге | собранная фраза (родной) | судья швов |';
            $md[] = '|---|---|---|---|---|---|---|';
            foreach ($served->phrases as $phrase) {
                foreach ($phrase->fillers() as $n => $filler) {
                    // The judge's id of a filler is its place in its frame (`p3.f2`), as `NativeSeams` names it.
                    $id = $phrase->id.'.f'.($n + 1);
                    $sentence = $judgeItems[$id]['sentence'] ?? ($seamSentences[$id]['sentence']
                        ?? (FrameText::hasSlot($phrase->frameNative) ? FrameText::fill($phrase->frameNative, $filler->native) : '— (в каркасе на родном нет ___)'));
                    $verdict = array_key_exists($id, $verdicts) ? ($verdicts[$id] ? 'да' : '**нет**') : '—';
                    $md[] = sprintf('| %s | %s | %s | %s | %s | %s | %s |', $cell($phrase->id), $cell($filler->target), $cell($filler->native), $cell($filler->pronunciationNative),
                        $filler->inDialogue ? 'да' : '—', $cell($sentence), $verdict);
                }
            }
            $md[] = '';
            $md[] = '### Проверки обменов';
            $md[] = '';
            $md[] = '| # | вопрос | варианты (✓ — верный) |';
            $md[] = '|---|---|---|';
            foreach ($served->exchanges as $exchange) {
                $md[] = sprintf('| %d | %s / %s | %s |', $exchange->step, $cell($exchange->check->textTarget), $cell($exchange->check->textNative),
                    $cell(implode(' · ', array_map(static fn ($o, int $i): string => ($i === $exchange->check->correctOptionIndex ? '✓ ' : '').$o->textTarget.' / '.$o->textNative, $exchange->check->options, array_keys($exchange->check->options)))));
            }
            $md[] = '';
            $md[] = '### Слушаю весь визит';
            $md[] = '';
            foreach ($served->listening as $i => $q) {
                $md[] = $line(sprintf('- L%d. %s — %s', $i + 1, $q->textNative, implode(' · ', array_map(static fn (string $o, int $j): string => ($j === $q->correctOptionIndex ? '✓ ' : '').$o, $q->optionsNative, array_keys($q->optionsNative)))));
            }
            $md[] = '';
            $md[] = '### Словарь';
            $md[] = '';
            $md[] = '| id | слово | вид | перевод | чтение | где звучит |';
            $md[] = '|---|---|---|---|---|---|';
            foreach ($served->vocabulary as $item) {
                $md[] = sprintf('| %s | %s | %s | %s | %s | %s |', $cell($item->id), $cell($item->termTarget), $item->kind === 'chunk' ? 'связка' : 'слово', $cell($item->translationNative), $cell($item->pronunciationNative), $cell(implode(', ', $item->usedIn)));
            }
            $md[] = '';
        } elseif ($day !== null) {
            $md[] = '_Урока нет: ни записанного, ни разбираемого ответа модели._';
            $md[] = '';
        }

        if ($day !== null) {
            $md[] = '### Находки в ответе модели (до починок)';
            $md[] = '';
            if (($rawCheck['parse_error'] ?? null) !== null) {
                $md[] = 'Ответ не разобран: `'.$cell($rawCheck['parse_error']).'`';
            } elseif ($rawViolations === []) {
                $md[] = 'нет';
            } else {
                $md[] = '| код | порог | адрес | что |';
                $md[] = '|---|---|---|---|';
                foreach ($rawViolations as $f) {
                    $md[] = $findingLine($f);
                }
            }
            $md[] = '';
            $md[] = '### Не проверено — нет ключей пакета (lang.pack_missing, не находка)';
            $md[] = '';
            $contextCodes = isset($rawCheck['native_code'], $rawCheck['target_code']) ? " Контекст проверки: родной `{$rawCheck['native_code']}`, целевой `{$rawCheck['target_code']}`." : '';
            if (! $skipsKnown) {
                $md[] = 'Не известно: ответ модели не дошёл до валидатора (нет ответа или он не по схеме), а пропуски записываются, пока правила идут.'.$contextCodes;
            } elseif ($skips === []) {
                $md[] = 'Всё проверено: у пакетов обоих языков пары есть все нужные ключи.'.$contextCodes;
            } else {
                $md[] = sprintf('Кодов не проверено: родной %d, целевой %d.', $skipped['native'], $skipped['target']).$contextCodes;
                $md[] = '';
                $md[] = '| код | сторона | язык | недостающие ключи |';
                $md[] = '|---|---|---|---|';
                foreach ($skips as $s) {
                    $md[] = $skipLine($s);
                }
            }
            $md[] = '';
            $md[] = '### Судья швов';
            $md[] = '';
            if ($judgeCall === null) {
                $md[] = $status === 'ready'
                    ? 'Судья не звался: в уроке нет предложения на родном с окном ___.'
                    : 'Судья не звался: урок не прошёл порог.';
            } else {
                $md[] = sprintf('Предложений: %d · с вердиктом: %d · «нет»: %d · `%s` · %s', count($judgeItems), count($verdicts), count($judgeNo), $cell($judgeCall['prompt_version'] ?? '—'), $usd((float) ($judgeCall['cost_usd'] ?? 0)));
                if ($verdicts === []) {
                    $md[] = '';
                    $md[] = 'Ответ судьи без вердиктов — в бою это `judge.unavailable`'.(($judgeCall['error'] ?? null) !== null ? ': '.$line($judgeCall['error']) : '').'.';
                }
                if ($judgeNo !== []) {
                    $md[] = '';
                }
                foreach ($judgeNo as $id) {
                    $item = $judgeItems[$id];
                    $md[] = $line(sprintf('- %s: «%s» — «%s» + «%s»', $id, $item['sentence'] ?? '', $item['pattern'] ?? '', $item['value'] ?? ''));
                }
            }
            $md[] = '';
        }

        if ($now !== null) {
            $md[] = '### Находки с пакетами LANG-1 (повторная проверка, без вызовов)';
            $md[] = '';
            if (! $now['done']) {
                $md[] = 'Не перепроверено: '.$line($now['note']).'.';
            } else {
                $md[] = sprintf('Фатальных: %d → %d · предупреждений: %d → %d · не проверено кодов (родной/целевой): %s → %d/%d · пакеты сейчас: %s',
                    count($rawFatal), $now['fatal'], count($rawWarnings), $now['warnings'], $skipsKnown ? "{$skipped['native']}/{$skipped['target']}" : '?/?',
                    $now['pack_missing']['native'], $now['pack_missing']['target'], implode(', ', $packs->codes()));
                $md[] = '';
                $md[] = 'Коды: '.($now['codes_added'] === [] && $now['codes_gone'] === [] ? 'без изменений.'
                    : trim(($now['codes_added'] === [] ? '' : 'появились '.$compact($now['codes_added'])).($now['codes_added'] !== [] && $now['codes_gone'] !== [] ? '; ' : '').($now['codes_gone'] === [] ? '' : 'ушли '.$compact($now['codes_gone']))).'.');
                if ($now['added'] !== [] || $now['gone'] !== []) {
                    $md[] = '';
                    $md[] = '| ± код | порог | адрес | что |';
                    $md[] = '|---|---|---|---|';
                    foreach ($now['added'] as $f) {
                        $md[] = $findingLine($f, '+ ');
                    }
                    foreach ($now['gone'] as $f) {
                        $md[] = $findingLine($f, '− ');
                    }
                }
                if ($now['skips'] !== []) {
                    $md[] = '';
                    $md[] = 'Всё ещё не проверено:';
                    $md[] = '';
                    $md[] = '| код | сторона | язык | недостающие ключи |';
                    $md[] = '|---|---|---|---|';
                    foreach ($now['skips'] as $s) {
                        $md[] = $skipLine($s);
                    }
                }
            }
            $md[] = '';
        }
        file_put_contents("{$dir}/days/{$pair}.md", implode("\n", $md));

        // ── the row ──────────────────────────────────────────────────────────────────────────────────────────────────
        $warnTally = $tally($rawWarnings);
        $parseError = ($rawCheck['parse_error'] ?? null) !== null;
        $row = [
            "[{$native}→{$target}](days/{$pair}.md)",
            $status.($failReason ? " ({$failReason})" : ''),
            $day === null ? '—' : ($parseError ? 'не разобран' : (string) count($rawFatal)),
            $day === null ? '—' : ($rawWarnings === [] ? '0' : count($rawWarnings).': '.$compact($warnTally)),
            $day === null ? '—' : ($skipsKnown ? "{$skipped['native']}/{$skipped['target']}" : '?'),
            $judgeCall === null ? '—' : count($judgeNo).'/'.count($judgeItems),
            $day === null ? '—' : (string) count($repairs),
            $usd($planUsd), $usd($dayUsd), $usd($planUsd + $dayUsd), $secs($planS), $secs($dayS),
        ];
        if ($now !== null) {
            $row[] = ! $now['done'] ? '—' : sprintf('%d · %d · %d/%d%s', $now['fatal'], $now['warnings'], $now['pack_missing']['native'], $now['pack_missing']['target'],
                $now['added'] === [] && $now['gone'] === [] ? ' (без изменений)' : sprintf(' (+%d/−%d)', count($now['added']), count($now['gone'])));
        }
        $row[] = '—';
        $rows[] = '| '.implode(' | ', array_map($cell, $row)).' |';

        $summary[$pair] = [
            'native' => $native, 'target' => $target, 'status' => $status, 'fail_reason' => $failReason, 'error' => $error, 'error_phase' => $run['error_phase'] ?? null,
            'lesson_source' => $lessonSource, 'prompt_version_lesson' => $day['prompt_version'] ?? null, 'model_lesson' => $day['model'] ?? null,
            'raw' => ['fatal' => count($rawFatal), 'warnings' => count($rawWarnings), 'codes' => $tally($rawViolations), 'parse_error' => $rawCheck['parse_error'] ?? null],
            'pack_missing' => ['known' => $skipsKnown, 'native' => $skipped['native'], 'target' => $skipped['target'],
                'native_code' => $rawCheck['native_code'] ?? null, 'target_code' => $rawCheck['target_code'] ?? null, 'skips' => $skips],
            'judge' => ['called' => $judgeCall !== null, 'sentences' => count($judgeItems), 'verdicts' => count($verdicts), 'no' => $judgeNo],
            'repairs' => array_map(static fn (array $c): array => ['address' => $c['asked']['address'] ?? null, 'kind' => $c['asked']['kind'] ?? null, 'codes' => array_values(array_unique(array_map(static fn (array $f): string => (string) ($f['code'] ?? '?'), (array) ($c['asked']['findings'] ?? [])))), 'cost_usd' => $c['cost_usd'] ?? null], $repairs),
            'lesson_calls' => $lessonCalls, 'attempts' => $day['attempts'] ?? null,
            'broken_calls' => array_map(static fn (array $c): array => ['call' => $kindOf($c), 'at' => $c['at'] ?? null, 'error' => $c['error']], $brokenCalls),
            'stored_findings' => ['count' => count($day['findings'] ?? []), 'codes' => $tally($day['findings'] ?? [])],
            'usd' => ['plan' => round($planUsd, 6), 'lesson' => round($lessonUsd, 6), 'repair' => round($repairUsd, 6), 'judge' => round($judgeUsd, 6), 'day' => round($dayUsd, 6), 'all' => round($planUsd + $dayUsd, 6), 'cost_usd_lesson_stored' => $day['cost_usd_lesson'] ?? null],
            'tokens_day' => ['in' => $tokensIn, 'cached' => $tokensCached, 'cached_share' => round($cachedShare, 3)],
            'seconds' => ['plan' => $planS, 'day' => $dayS],
            'packs_now' => $now === null ? null : array_diff_key($now, ['violations' => 0]),
        ];

        $totals[match ($status) {
            'ready' => 'ready',
            'failed' => 'failed',
            'error' => 'errors',
            default => 'other',
        }]++;
        $totals['raw_fatal'] += count($rawFatal);
        $totals['raw_warnings'] += count($rawWarnings);
        if ($day !== null && ! $skipsKnown) {
            $totals['pack_missing_unknown']++;
        }
        $totals['pack_missing_native'] += $skipped['native'];
        $totals['pack_missing_target'] += $skipped['target'];
        $totals['judge_no'] += count($judgeNo);
        $totals['judge_all'] += count($judgeItems);
        $totals['repairs'] += count($repairs);
        $totals['plan_usd'] += $planUsd;
        $totals['day_usd'] += $dayUsd;
        $totals['all_usd'] += $planUsd + $dayUsd;
        $totals['plan_s'] += (float) ($planS ?? 0);
        $totals['day_s'] += (float) ($dayS ?? 0);
        if ($now !== null && $now['done']) {
            $totals['now_fatal'] += $now['fatal'];
            $totals['now_warnings'] += $now['warnings'];
            $totals['now_pack_missing_native'] += $now['pack_missing']['native'];
            $totals['now_pack_missing_target'] += $now['pack_missing']['target'];
        }
        fwrite(STDOUT, sprintf("%s: %s · raw fatal %d, warnings %d · pack_missing %s · judge no %d/%d · P2R %d · %s%s\n", $pair, $status, count($rawFatal), count($rawWarnings),
            $skipsKnown ? "{$skipped['native']}/{$skipped['target']}" : '?', count($judgeNo), count($judgeItems), count($repairs), $usd($planUsd + $dayUsd),
            $now === null ? '' : ($now['done'] ? sprintf(' · now fatal %d, warnings %d, pack_missing %d/%d', $now['fatal'], $now['warnings'], $now['pack_missing']['native'], $now['pack_missing']['target']) : ' · now: '.$now['note'])));
    } catch (Throwable $e) {
        // One broken pair never costs the others their days: its md and its row say what broke, the export goes on.
        $why = $e::class.': '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine();
        file_put_contents("{$dir}/days/{$pair}.md", implode("\n", [
            sprintf('# LANG-1 · %s→%s (%s→%s, начальный)', $endonym($native), $endonym($target), $native, $target), '',
            '**Экспорт пары не удался:** '.$line($why), '',
        ]));
        $row = ["[{$native}→{$target}](days/{$pair}.md)", 'экспорт не удался', '—', '—', '—', '—', '—', '—', '—', '—', '—', '—'];
        if ($packsNow) {
            $row[] = '—';
        }
        $row[] = '—';
        $rows[] = '| '.implode(' | ', array_map($cell, $row)).' |';
        $summary[$pair] = ['native' => $native, 'target' => $target, 'status' => 'export_error', 'error' => $why];
        $totals['errors']++;
        fwrite(STDERR, "{$pair}: EXPORT ERROR {$why}\n");
    }
}

// ── the table ────────────────────────────────────────────────────────────────────────────────────────────────────────
$head = ['пара', 'итог', 'фатальных (сырой ответ)', 'предупреждений по кодам', 'lang.pack_missing (кодов: родной/целевой)', 'судья швов «нет»/всего', 'починок P2R', 'план $', 'день $', 'всего $', 'план с', 'день с'];
if ($packsNow) {
    $head[] = 'с пакетами LANG-1: фат. · пред. · pack_missing (± находок)';
}
$head[] = 'на глаз';
$total = [
    "**итого ({$totals['pairs']})**",
    "ready {$totals['ready']} · failed {$totals['failed']}".($totals['other'] > 0 ? " · другое {$totals['other']}" : '').($totals['errors'] > 0 ? " · error {$totals['errors']}" : ''),
    (string) $totals['raw_fatal'], (string) $totals['raw_warnings'],
    "{$totals['pack_missing_native']}/{$totals['pack_missing_target']}".($totals['pack_missing_unknown'] > 0 ? " (+{$totals['pack_missing_unknown']} ?)" : ''),
    "{$totals['judge_no']}/{$totals['judge_all']}", (string) $totals['repairs'],
    $usd($totals['plan_usd']), $usd($totals['day_usd']), $usd($totals['all_usd']), $secs($totals['plan_s']), $secs($totals['day_s']),
];
if ($packsNow) {
    $total[] = sprintf('%d · %d · %d/%d', $totals['now_fatal'], $totals['now_warnings'], $totals['now_pack_missing_native'], $totals['now_pack_missing_target']);
}
$total[] = '—';
file_put_contents("{$dir}/table.md", implode("\n", [
    '| '.implode(' | ', array_map($cell, $head)).' |',
    '|'.str_repeat('---|', count($head)),
    ...$rows,
    '| '.implode(' | ', array_map($cell, $total)).' |',
])."\n");

$totals['plan_usd'] = round($totals['plan_usd'], 6);
$totals['day_usd'] = round($totals['day_usd'], 6);
$totals['all_usd'] = round($totals['all_usd'], 6);
$totals['plan_s'] = round($totals['plan_s'], 1);
$totals['day_s'] = round($totals['day_s'], 1);
file_put_contents("{$dir}/summary.json", $json([
    'generated_at' => now()->toIso8601String(), 'packs_now' => $packsNow, 'packs' => $packs->codes(),
    'pairs' => $summary, 'totals' => $totals,
]));
fwrite(STDOUT, sprintf("pairs: %d · days/*.md, table.md, summary.json → %s\n", count($summary), $dir));

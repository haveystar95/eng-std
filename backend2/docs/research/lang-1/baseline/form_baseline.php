<?php

declare(strict_types=1);

/**
 * LANG-1 · BASELINE OF THE FATAL CODES ON ru→en. READ-ONLY: no model call, no database query, no file written — the
 * result goes to STDOUT as JSON.
 *
 * Every stored RAW lesson answer (the model's answer before any repair) found under docs/research/ is parsed by the
 * current `LessonParser` and read by the current `LessonValidator` in a production-like context (the pair's packs by
 * code, the level's counts, the plan's roles when the run kept them, the story so far for a day 2), and per day it
 * counts: the fatal codes (`LessonGate::fatal`), the fatal CARDS (`LessonGate::cards` — ≥ 3 is a failed day, P2R takes 2),
 * `options.form_mismatch` by sub-rule (the one the validator reported first, and every sub-rule the check trips), and
 * `pronunciation.foreign_script` with its letters. The same for the 14 LANG-1 days — their stored `raw_check` and a
 * re-run on this code, as a cross-check.
 *
 *   docker exec -w /wt -e DB_DATABASE=wordtrainer_lang1_test wt_lang1_scout php docs/research/lang-1/baseline/form_baseline.php
 */

use App\Modules\Plan\Application\Dto\PlanConfig;
use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonGate;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Entity\PlanScene;
use App\Modules\Plan\Domain\Lesson\EarlierDay;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\Lesson;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\LessonRoles;
use App\Modules\Plan\Domain\Service\Words;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$research = (string) realpath(__DIR__.'/../..');
$read = static fn (string $file): mixed => is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
$packs = $app->make(LanguagePacks::class);
$validator = $app->make(LessonValidator::class);
$config = $app->make(PlanConfig::class);
$parser = new LessonParser;

/** «Parent / Родитель» → [target, native]. */
$split = static function (?string $both): array {
    $parts = array_map('trim', explode(' / ', (string) $both, 2));

    return [$parts[0] ?? '', $parts[1] ?? ''];
};

$letters = static fn (string $text): int => mb_strlen((string) preg_replace('/[^\p{L}\p{N}]+/u', '', $text));
$contains = static function (array $haystack, array $needle): bool {
    $n = count($needle);
    for ($i = 0; $i + $n <= count($haystack); $i++) {
        if (array_slice($haystack, $i, $n) === $needle) {
            return true;
        }
    }

    return false;
};

/** How an option starts lower-case: a lower-case first character, or a lower-case first LETTER after a digit / a mark. */
$lowerKind = static function (string $text): string {
    if (preg_match('/^\p{Ll}/u', $text) === 1) {
        return 'lower_initial';
    }
    if (preg_match('/^[^\p{L}]*\p{N}[^\p{L}]*\p{Ll}/u', $text) === 1) {
        return 'lower_after_digit';
    }

    return 'lower_after_mark';
};

/**
 * Every sub-rule of `options.form_mismatch` a check trips, option by option (the validator reports only the first):
 * `length` (a wrong option outside 0.5–2× the right one's letters), `lower_*` (starts lower-case), `piece` (the option's
 * words are a run of the partner's native line).
 */
$subRules = static function (Exchange $exchange) use ($letters, $contains, $lowerKind): array {
    $right = $exchange->check->correctOption();
    $partner = $exchange->partner();
    if ($right === null || $partner === null) {
        return [];
    }
    $rightLength = $letters($right->textNative);
    $partnerWords = Words::tokens($partner->textNative);
    $hits = [];
    foreach ($exchange->check->options as $index => $option) {
        $text = trim($option->textNative);
        $length = $letters($text);
        $isRight = $index === $exchange->check->correctOptionIndex;
        if (! $isRight && $rightLength > 0 && ($length < 0.5 * $rightLength || $length > 2.0 * $rightLength)) {
            $hits[] = ['rule' => 'length', 'option' => $index, 'right' => $isRight, 'text' => $text, 'letters' => "{$length}/{$rightLength}"];
        }
        if (preg_match('/^[^\p{L}]*\p{Ll}/u', $text) === 1) {
            $hits[] = ['rule' => $lowerKind($text), 'option' => $index, 'right' => $isRight, 'text' => $text];
        }
        $optionWords = Words::tokens($text);
        if ($optionWords !== [] && $contains($partnerWords, $optionWords)) {
            $hits[] = ['rule' => 'piece', 'option' => $index, 'right' => $isRight, 'text' => $text];
        }
    }

    return $hits;
};

/** The sub-rule the validator reported first, read off its detail. */
$firstRule = static function (string $detail) use ($lowerKind): string {
    if (str_contains($detail, 'letters against')) {
        return 'length';
    }
    if (str_contains($detail, 'starts lower-case') && preg_match('/the option «(.*)» starts lower-case/u', $detail, $m) === 1) {
        return $lowerKind($m[1]);
    }
    if (str_contains($detail, "is a piece of the partner's line")) {
        return 'piece';
    }

    return 'other';
};

/** The foreign letters of a `pronunciation.foreign_script` finding, each with its code point and Unicode name. */
$foreignLetters = static function (string $detail): array {
    $out = [];
    if (preg_match('/letters of another writing: (.*)$/u', $detail, $m) === 1 && preg_match_all('/«(.)»/u', $m[1], $mm) > 0) {
        foreach ($mm[1] as $ch) {
            $out[] = sprintf('%s U+%04X %s', $ch, IntlChar::ord($ch), IntlChar::charName($ch));
        }
    }

    return $out;
};

/**
 * @param  list<LessonViolation>  $found
 */
$summarise = static function (?Lesson $lesson, array $found) use ($subRules, $firstRule, $foreignLetters): array {
    $fatal = LessonGate::fatal($found);
    $cards = LessonGate::cards($fatal);
    $without = static fn (array $codes): ?int => ($c = LessonGate::cards(array_values(array_filter($fatal, static fn (LessonViolation $v): bool => ! in_array($v->code, $codes, true))))) === null ? null : count($c);
    $exchanges = [];
    foreach ($lesson?->exchanges ?? [] as $exchange) {
        $exchanges[LessonViolation::check($exchange->step)] = $exchange;
    }
    $form = [];
    $foreign = [];
    foreach ($fatal as $v) {
        if ($v->code === LessonCodes::OPTIONS_FORM_MISMATCH) {
            $exchange = $exchanges[$v->address] ?? null;
            $form[] = [
                'address' => $v->address,
                'first_rule' => $firstRule($v->detail),
                'all_rules' => $exchange === null ? null : array_values(array_unique(array_map(static fn (array $h): string => $h['rule'], $subRules($exchange)))),
                'hits' => $exchange === null ? null : $subRules($exchange),
                'detail' => $v->detail,
                'partner_native' => $exchange?->partner()?->textNative,
                'partner_target' => $exchange?->partner()?->textTarget,
                'right_native' => $exchange?->check->correctOption()?->textNative,
                'options_native' => $exchange === null ? null : array_map(static fn ($o): string => $o->textNative, $exchange->check->options),
                'question_native' => $exchange?->check->textNative,
            ];
        }
        if ($v->code === LessonCodes::PRONUNCIATION_FOREIGN_SCRIPT) {
            $foreign[] = ['address' => $v->address, 'letters' => $foreignLetters($v->detail), 'detail' => $v->detail];
        }
    }

    return [
        'findings' => count($found),
        'fatal' => count($fatal),
        'fatal_codes' => array_count_values(array_map(static fn (LessonViolation $v): string => $v->code, $fatal)),
        'fatal_cards' => $cards === null ? 'unrepairable' : count($cards),
        'fatal_cards_list' => $cards === null ? null : array_map(static fn ($c): string => $c->address, $cards),
        'cards_without_form' => $without([LessonCodes::OPTIONS_FORM_MISMATCH]),
        'cards_without_form_and_foreign' => $without([LessonCodes::OPTIONS_FORM_MISMATCH, LessonCodes::PRONUNCIATION_FOREIGN_SCRIPT]),
        'form_mismatch' => $form,
        'foreign_script' => $foreign,
    ];
};

/**
 * Read one raw answer and count what the task asks for.
 *
 * @param  array<string, mixed>  $raw
 */
$measure = static function (array $raw, string $native, string $target, string $level, ?LessonRoles $roles, EarlierDays $earlier, ?VoiceGender $gender = null)
    use ($packs, $validator, $config, $parser, $summarise): array {
    $lesson = $parser->parse($raw);
    if ($roles !== null) {
        $lesson = $lesson->withRoles($roles);
    }
    $partnerRole = $roles?->partnerTarget ?? '';
    if ($partnerRole === '') {
        foreach ($lesson->exchanges as $exchange) {
            $partnerRole = $exchange->partner()?->roleTarget ?? '';
            if ($partnerRole !== '') {
                break;
            }
        }
    }
    $counts = $config->countsFor(PlanLevel::from($level));
    $context = new LessonValidationContext($counts['vocabulary'], $counts['dialogue'], $packs->for($native), $packs->for($target), $gender, $earlier, $partnerRole);
    $found = $validator->run($lesson, $context);

    return $summarise($lesson, $found);
};

$out = ['baseline' => [], 'lang1' => [], 'errors' => []];

// ── gen-3: six topics × (day 1, day 2 v4.5, day 2 v4.6), ru→en, the plan's roles, day 2 against the stored day 1 ─────
foreach (['doctor', 'bank', 'airport', 'restaurant', 'rent', 'interview'] as $slug) {
    $run = $read("{$research}/gen-3/runs/{$slug}.json");
    if (! is_array($run)) {
        $out['errors'][] = "gen-3/{$slug}: no run";

        continue;
    }
    [$learnerT, $learnerN] = $split($run['learner_role'] ?? null);
    $level = (string) $run['level'];
    foreach (['day1' => 0, 'day2-v4.5' => 1, 'day2-v4.6' => 1] as $file => $sceneIndex) {
        $key = "gen-3/{$slug}-{$file}";
        try {
            $raw = $read("{$research}/gen-3/answers/{$slug}-{$file}.json");
            if (! is_array($raw)) {
                throw new RuntimeException('no answer');
            }
            [$partnerT, $partnerN] = $split($run['scenes'][$sceneIndex]['partner'] ?? null);
            $roles = new LessonRoles($learnerT, $learnerN, $partnerT, $partnerN);
            $earlier = new EarlierDays;
            if ($sceneIndex === 1) {
                $dayOne = $parser->parse((array) $read("{$research}/gen-3/final/{$slug}-day1.json"));
                [$p1T] = $split($run['scenes'][0]['partner'] ?? null);
                $earlier = new EarlierDays([EarlierDay::of(1, (string) $run['scenes'][0]['title_target'], $p1T, $dayOne->roleGender ?? PlanScene::DEFAULT_PARTNER_VOICE, $dayOne)]);
            }
            $record = $file === 'day1' ? ($run['day1'] ?? []) : (array) $read("{$research}/gen-3/runs/{$slug}-{$file}.json");
            $out['baseline'][$key] = ['pair' => 'ru-en', 'prompt' => $record['prompt_version'] ?? ($record['version'] ?? null), 'level' => $level,
                'status_then' => $record['status'] ?? null, 'fail_reason_then' => $record['fail_reason'] ?? null]
                + $measure($raw, 'ru', 'en', $level, $roles, $earlier);
        } catch (Throwable $e) {
            $out['errors'][] = "{$key}: ".$e::class.': '.$e->getMessage();
        }
    }
}

// ── gen-2b: eight days 1 (six ru→en, ro→en, uk→en), v4.5, the model's own roles ───────────────────────────────────────
foreach ((array) $read("{$research}/gen-2b/runs.json") as $run) {
    $slug = (string) $run['slug'];
    $key = "gen-2b/{$slug}";
    try {
        $raw = $read("{$research}/gen-2b/answers/{$slug}.json");
        if (! is_array($raw)) {
            throw new RuntimeException('no answer');
        }
        [$native, $target] = array_map('trim', explode('→', (string) $run['pair']));
        $lessonCall = array_values(array_filter((array) $run['calls'], static fn (array $c): bool => ($c['call'] ?? null) === 'lesson'))[0] ?? [];
        $out['baseline'][$key] = ['pair' => "{$native}-{$target}", 'prompt' => $lessonCall['prompt_version'] ?? null, 'level' => (string) $run['level'],
            'status_then' => $run['status'] ?? null, 'fail_reason_then' => $run['fail_reason'] ?? null]
            + $measure($raw, $native, $target, (string) $run['level'], null, new EarlierDays);
    } catch (Throwable $e) {
        $out['errors'][] = "{$key}: ".$e::class.': '.$e->getMessage();
    }
}

// ── single stored answers: CHECK-1 (a live ru→en day 1, v4.7), GEN-2b's case of Den's interview (v4.4) ──────────────
foreach ([
    'check-1/vet-day1-attempt2' => ["{$research}/check-1/answers/vet-day1-attempt2.json", 'beginner', 'lesson_day.v4.7'],
    'gen-2b/cases/den-interview-v4.4' => ["{$research}/gen-2b/cases/den-interview-v4.4.answer.json", 'intermediate', 'lesson_day.v4.4'],
] as $key => [$file, $level, $prompt]) {
    try {
        $raw = $read($file);
        if (! is_array($raw)) {
            throw new RuntimeException('no answer');
        }
        $out['baseline'][$key] = ['pair' => 'ru-en', 'prompt' => $prompt, 'level' => $level, 'status_then' => null, 'fail_reason_then' => null]
            + $measure($raw, 'ru', 'en', $level, null, new EarlierDays);
    } catch (Throwable $e) {
        $out['errors'][] = "{$key}: ".$e::class.': '.$e->getMessage();
    }
}

// ── LANG-1: the 14 days — stored raw_check, and a re-run on this code in the context of the stored request ─────────────
foreach (glob("{$research}/lang-1/runs/*.json") ?: [] as $runFile) {
    $pair = basename($runFile, '.json');
    $key = "lang-1/{$pair}";
    try {
        $run = (array) $read($runFile);
        $raw = $read("{$research}/lang-1/answers/{$pair}.json");
        $r = (array) $run['request'];
        $roles = new LessonRoles((string) $r['roles']['learnerTarget'], (string) $r['roles']['learnerNative'], (string) $r['roles']['partnerTarget'], (string) $r['roles']['partnerNative']);
        $lesson = $parser->parse((array) $raw)->withRoles($roles);
        $stored = array_map(static fn (array $f): LessonViolation => new LessonViolation((string) $f['code'], (string) $f['address'], (string) $f['detail']), (array) ($run['raw_check']['violations'] ?? []));
        $rerun = $measure((array) $raw, (string) $r['native_lang_code'], (string) $r['target_lang_code'], (string) $r['level'], $roles, new EarlierDays,
            $r['learner_gender'] === null ? null : VoiceGender::from((string) $r['learner_gender']));
        $out['lang1'][$key] = ['pair' => $pair, 'prompt' => $run['day1']['prompt_version'] ?? null, 'level' => (string) $r['level'],
            'status_then' => $run['day1']['status'] ?? null, 'fail_reason_then' => $run['day1']['fail_reason'] ?? null,
            'rerun_same_as_stored' => $rerun['findings'] === count($stored) && $rerun['fatal'] === count(LessonGate::fatal($stored)),
            'rerun_fatal' => $rerun['fatal'], 'rerun_fatal_cards' => $rerun['fatal_cards']]
            + $summarise($lesson, $stored);
    } catch (Throwable $e) {
        $out['errors'][] = "{$key}: ".$e::class.': '.$e->getMessage();
    }
}

fwrite(STDOUT, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

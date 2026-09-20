<?php

declare(strict_types=1);

/**
 * CHECK-1 · THE VALIDATOR OVER SAVED ANSWERS — NO MODEL IS ASKED, NOTHING IS WRITTEN TO THE DATABASE.
 *
 * Every file named is a lesson as the model wrote it (`docs/research/gen-3/answers/*.json`, or the answer of the live day
 * read out of `api_request_logs`). Each is parsed and read by the validator of the code this script runs under — so the
 * same files run before and after the order give «было» and «стало» — with the deployment's packs (ru the learner's,
 * en the target), the answer's own counts, no story so far and no partner role: what this measures is the reading of
 * one answer's text, not the story codes of GEN-3.
 *
 * Prints JSON: per file — the tally by code, the fatal findings, and every finding as `code@address: detail`.
 *
 *   docker compose exec -T app php docs/research/check-1/tools/tally.php docs/research/gen-3/answers/*.json > docs/research/check-1/before.json
 */

use App\Modules\Plan\Domain\Check\Language\LanguagePacks;
use App\Modules\Plan\Domain\Check\LessonGate;
use App\Modules\Plan\Domain\Check\LessonValidationContext;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$packs = $app->make(LanguagePacks::class);
$parser = new LessonParser;
$validator = new LessonValidator;

$out = [];
foreach (array_slice($argv, 1) as $file) {
    $payload = json_decode((string) file_get_contents($file), true);
    if (! is_array($payload)) {
        fwrite(STDERR, "{$file}: not a JSON object\n");

        continue;
    }
    $lesson = $parser->parse($payload);
    $context = new LessonValidationContext(
        count($payload['vocabulary'] ?? []),
        count($payload['dialogue'] ?? []),
        $packs->for('ru'),
        $packs->for('en'),
        null,
        new EarlierDays,
        '',
    );
    $found = $validator->run($lesson, $context);
    $codes = array_filter(LessonValidator::tally($found));
    ksort($codes);
    $out[basename($file, '.json')] = [
        'codes' => $codes,
        'fatal' => count(LessonGate::fatal($found)),
        'findings' => array_map(static fn (LessonViolation $v): string => "{$v->code}@{$v->address}: {$v->detail}", $found),
    ];
}

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";

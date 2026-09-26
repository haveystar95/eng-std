<?php

declare(strict_types=1);

/**
 * LANG-1b §10.4 · EVERY ANSWER OF A PAIR READ AGAIN — no model call: `answers/<pair>.all.json` (every lesson answer the live
 * run got, the first build's and the rebuild's) through the parser and the validator of THIS code, with the request of
 * `runs/<pair>.json`. `live.php` keeps the raw check of the LAST answer only; a day rebuilt once needs the first one too —
 * what failed it at the gate.
 *
 * Per answer: every finding (`*` — fatal), the count of `vocab.definition_language`, the language of the checks'
 * `text_target` by the target's pack, and the letters of other Cyrillic alphabets the model wrote in its readings (the
 * parser mends them — §10.3 — so they are read off the raw JSON).
 *
 *   docker exec -w /wt -e DB_DATABASE=wordtrainer_lang1b10_test wt_lang1b10 php docs/research/lang-1b/tools/recheck.php ru-ro
 */

use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Service\LessonContexts;
use App\Modules\Plan\Domain\Check\Language\TextLanguage;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonGate;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\LessonRoles;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$pair = $argv[1] ?? '';
$dir = realpath(__DIR__.'/../live');
$run = json_decode((string) @file_get_contents("{$dir}/runs/{$pair}.json"), true);
$answers = json_decode((string) @file_get_contents("{$dir}/answers/{$pair}.all.json"), true);
if (! is_array($run) || ! is_array($answers) || ! isset($run['request'])) {
    fwrite(STDERR, "usage: recheck.php <pair> — runs/<pair>.json and answers/<pair>.all.json of a live run\n");
    exit(1);
}

$r = $run['request'];
$request = new LessonRequest(
    $r['topic'], $r['topic_description'], $r['target_language'], $r['native_language'], PlanLevel::from($r['level']),
    $r['learner_gender'] === null ? null : VoiceGender::from($r['learner_gender']), $r['vocabulary_count'], $r['dialogue_count'],
    new LessonRoles($r['roles']['learnerTarget'], $r['roles']['learnerNative'], $r['roles']['partnerTarget'], $r['roles']['partnerNative']),
    new EarlierDays, [], $r['target_lang_code'], $r['native_lang_code'],
);
$context = $app->make(LessonContexts::class)->of($request);
$aliens = '/[җғқәүұңһөҖҒҚӘҮҰҢҺӨ]/u';

foreach ($answers as $n => $payload) {
    echo '── answer '.($n + 1).' of '.count($answers)."\n";
    if (! is_array($payload)) {
        echo "   no answer\n";

        continue;
    }
    $lesson = (new LessonParser)->parse($payload)->withRoles($request->roles);
    $found = $app->make(LessonValidator::class)->run($lesson, $context);
    $fatal = LessonGate::fatal($found);
    $cards = LessonGate::cards($fatal);
    echo '   fatal '.count($fatal).' on '.($cards === null ? '? (a finding at no card)' : count($cards)).' card(s) · findings '.count($found)."\n";
    foreach ($found as $v) {
        echo '   '.(LessonGate::isFatal($v->code) ? '* ' : '  ')."{$v->code} {$v->address} — ".mb_substr($v->detail, 0, 150)."\n";
    }
    $definitions = count(array_filter($found, static fn ($v): bool => $v->code === LessonCodes::VOCAB_DEFINITION_LANGUAGE));
    $checks = [];
    foreach ($payload['dialogue'] ?? [] as $exchange) {
        $texts = [(string) ($exchange['check']['text_target'] ?? '')];
        foreach ($exchange['check']['options'] ?? [] as $option) {
            $texts[] = (string) ($option['text_target'] ?? '');
        }
        foreach ($texts as $text) {
            $foreign = TextLanguage::outOfScript($text, $context->target) === true;
            foreach (TextLanguage::tellingWords($text, $context->target) as $row) {
                $foreign = $foreign || $row['theirs'] > $row['mine'];
            }
            $checks[$foreign ? 'foreign' : 'target']= ($checks[$foreign ? 'foreign' : 'target'] ?? 0) + 1;
        }
    }
    $readings = [];
    array_walk_recursive($payload, static function (mixed $value, string|int $key) use (&$readings, $aliens): void {
        if ($key === 'pronunciation_native' && is_string($value) && preg_match($aliens, $value) === 1) {
            $readings[] = $value;
        }
    });
    echo "   vocab.definition_language: {$definitions} · checks' text_target read as the target: ".($checks['target'] ?? 0).', as another language: '.($checks['foreign'] ?? 0)
        .' · readings with letters of other Cyrillic alphabets: '.($readings === [] ? 'none' : implode(' | ', $readings))."\n";
}

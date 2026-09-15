<?php

declare(strict_types=1);

/**
 * GEN-2b · THE REPAIR COMPARISON RE-READ BY THE VALIDATOR OF THE HAND-OVER — no model call.
 *
 * `repair-compare.php` ran before two heuristics were refined on the live days (a closing question known by its word
 * order without its mark; a particle before a preposition of the same spelling not a doubled word). Every card it
 * brought back is put into its answer again (`LessonCard::replace` / `replaceExchange`) and the lesson is validated with
 * the code as it is now: the findings at the card and the fatal findings of the lesson after → `repair-compare.json`
 * gets `at_card_after_now` and `fatal_after_now` beside what it had.
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/gen-2b/tools/rescore.php
 */

use App\Modules\Plan\Application\Command\BuildLessonHandler;
use App\Modules\Plan\Application\Dto\LessonRequest;
use App\Modules\Plan\Application\Service\LessonContexts;
use App\Modules\Plan\Domain\Check\LessonGate;
use App\Modules\Plan\Domain\Check\LessonValidator;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Lesson\Exchange;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Shared\Domain\Service\LanguageName;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$dir = realpath(__DIR__.'/..');
$rows = json_decode((string) file_get_contents("{$dir}/repair-compare.json"), true);
$gen2a = array_column(json_decode((string) file_get_contents("{$dir}/../gen-2a/runs.json"), true), null, 'slug');
$gen2b = array_column(json_decode((string) file_get_contents("{$dir}/runs.json"), true), null, 'slug');
$parser = new LessonParser;
$validator = $app->make(LessonValidator::class);

foreach ($rows as $i => $row) {
    preg_match('/^(GEN-2a|GEN-2b|Ден) (\S+) (\S+)/u', $row['case'], $m);
    [$source, $slug, $address] = [$m[1], $m[2], $m[3]];
    if ($source === 'GEN-2a') {
        $scene = DB::table('plan_scenes')->where('id', $gen2a[$slug]['scene_id'])->first();
        $raw = json_decode((string) $scene->lesson_json, true);
        $request = new LessonRequest((string) $scene->title_native, BuildLessonHandler::topicDescription((string) $scene->topic_description, $gen2a[$slug]['goal']), 'English', 'Russian', PlanLevel::from($gen2a[$slug]['level']), null, 8, 8, [], 'en', 'ru');
    } elseif ($source === 'Ден') {
        [$slug, $address] = ['interview', 'p1'];
        $raw = json_decode((string) file_get_contents("{$dir}/cases/den-interview-v4.4.answer.json"), true);
        $request = new LessonRequest('Собеседование', 'x', 'English', 'Russian', PlanLevel::Intermediate, null, 8, 8, [], 'en', 'ru');
    } else {
        $raw = json_decode((string) file_get_contents("{$dir}/answers/{$slug}.json"), true);
        $native = explode('→', $gen2b[$slug]['pair'])[0];
        $request = new LessonRequest($gen2b[$slug]['topic'], $gen2b[$slug]['topic_description'], 'English', LanguageName::of($native), PlanLevel::from($gen2b[$slug]['level']), null, 8, 8, [], 'en', $native);
    }
    $answer = $parser->parse($raw);
    $card = LessonCard::at($address);
    $rows[$i]['fatal_before_now'] = array_map(static fn (LessonViolation $v): string => "{$v->code}@{$v->address}", LessonGate::fatal($validator->run($answer, $app->make(LessonContexts::class)->of($request))));
    if ($row['status'] !== 'repaired' || $row['card_after'] === null || $card === null) {
        continue;
    }
    $repairedCard = $parser->card($card->kind, $row['card_after']);
    $repaired = $repairedCard instanceof Exchange
        ? $card->replaceExchange($answer, $repairedCard, $parser->frameUpdate($row['frame_update']))
        : $card->replace($answer, $repairedCard);
    if ($repaired === null) {
        $rows[$i]['fatal_after_now'] = null;
        continue;
    }
    $after = $validator->run($repaired, $app->make(LessonContexts::class)->of($request));
    $rows[$i]['at_card_after_now'] = array_values(array_map(static fn (LessonViolation $v): string => "{$v->code}@{$v->address}", array_filter($after, static fn (LessonViolation $v): bool => $card->covers($v))));
    $rows[$i]['fatal_after_now'] = array_map(static fn (LessonViolation $v): string => "{$v->code}@{$v->address}", LessonGate::fatal($after));
    fwrite(STDOUT, sprintf("%-44s %-13s fatal %d → %d (then %s) · card now: %s\n", $row['case'], $row['model'], count($rows[$i]['fatal_before_now']), count($rows[$i]['fatal_after_now']),
        $row['fatal_after'] === null ? '—' : (string) count($row['fatal_after']), implode(', ', $rows[$i]['at_card_after_now']) ?: '—'));
}
file_put_contents("{$dir}/repair-compare.json", json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");

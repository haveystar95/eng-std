<?php

declare(strict_types=1);

/**
 * GEN-4c · THE REPAIR OF A LINE TOLD WHICH WORDS TO KEEP (PAID, gpt-5.6-luna): the e2e ru→en day 1 (`e2e-c/en`) — its skeleton as
 * the model wrote it, the line a6 sent to a repair for `partner.yes_no_missing` and `partner.names_filler_meaning` (the judge's
 * verdict of the run) — repaired again, now with the note of the words only a6 says; `--trials=N` times. The run refused the
 * repair it bought (the line lost «cooking» and «keep clean»: `vocab.not_found`). Writes `e2e-c/en/carried-replay.json`.
 *
 *   docker exec wt_gen4c php docs/research/gen-4b/tools/gen4c-carried.php --trials=3
 */

require __DIR__.'/gate.php';

use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Application\Service\LessonCardRepairer;
use App\Modules\Plan\Application\Service\LessonContexts;
use App\Modules\Plan\Domain\Check\LessonCodes;
use App\Modules\Plan\Domain\Check\LessonViolation;
use App\Modules\Plan\Domain\Check\Skeleton\CarriedWords;
use App\Modules\Plan\Domain\Check\Skeleton\SkeletonCheck;
use App\Modules\Plan\Domain\Lesson\LessonCard;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use Illuminate\Support\Facades\DB;

if (! str_starts_with((string) DB::connection()->getDatabaseName(), 'wordtrainer_gen4')) {
    fwrite(STDERR, "Refused: not a stand's database.\n");
    exit(1);
}
$trials = (int) (option($argv, 'trials') ?? 1);
$plan = readJson(OUT.'/e2e-c/en/plan.json');
$bodies = readJson(OUT.'/e2e-c/en/calls-bodies.json')['calls'];
$raw = json_decode((string) array_values(array_filter($bodies, static fn ($c) => str_starts_with((string) $c['prompt'], 'LESSON SKELETON')))[0]['answer'], true);
$base = e2eRequest($plan);
$request = new App\Modules\Plan\Application\Dto\LessonRequest($base->topic, $base->topicDescription, $base->survival, 'English', 'Russian', $base->level, $base->learnerGender, 8, 12, $base->roles, $base->earlierDays, 'en', 'ru', $base->sceneId);
$context = app(LessonContexts::class)->skeleton($request);
$parser = new LessonParser;
$skeleton = $parser->skeleton($raw);
$card = LessonCard::at('a6');
$found = array_values(array_filter((new SkeletonCheck)->run($skeleton, $context), static fn (LessonViolation $v): bool => $card->covers($v)));
$found[] = new LessonViolation(LessonCodes::NAMES_FILLER_MEANING, 'a6', 'the reply names or paraphrases a filler of the frame: one general fact about the matter of the scene instead, true whatever was asked (the seam judge says, as in the run)');
$carried = CarriedWords::of($skeleton, $card, $context->targetReading('function_words', 'word_forms'));
$note = new LessonViolation(LessonCodes::REPAIR_NOTE_CARRIED, 'a6', 'not a finding: this card alone says the words of the day '.implode(', ', array_map(static fn ($v): string => "«{$v->termTarget}» ({$v->id})", $carried)).' — keep each of them in the card you write, or the day loses it');
$model = new RecordingPlanModel(builder(), 'carried-c');
app()->instance(PlanModelPort::class, $model);
app()->forgetInstance(LessonCardRepairer::class);
$out = ['carried' => array_map(static fn ($v): string => $v->termTarget, $carried), 'sent' => array_map(static fn ($v) => $v->toArray(), [...$found, $note]), 'trials' => []];
for ($i = 1; $i <= $trials && affordable("carried trial {$i}", 0.02); $i++) {
    $outcome = app(LessonCardRepairer::class)->repair($skeleton, null, $card, [...$found, $note], $request);
    $after = $outcome->skeleton === null ? [] : (new SkeletonCheck)->run($outcome->skeleton, $context);
    $fatal = array_values(array_unique(array_map(static fn ($v) => "{$v->code}@{$v->address}", LessonCodes::fatalOf($after))));
    $out['trials'][] = ['status' => $outcome->status, 'line' => $outcome->skeleton?->partnerLine('a6')?->textTarget, 'fatal' => $fatal,
        'at_a6' => array_values(array_map(static fn ($v) => $v->code, array_filter($after, static fn ($v) => $card->covers($v)))), 'cost_usd' => $outcome->costUsd];
    fwrite(STDERR, "trial {$i}: ".json_encode(end($out['trials']), JSON_UNESCAPED_UNICODE)."\n");
}
write(OUT.'/e2e-c/en/carried-replay.json', $out);

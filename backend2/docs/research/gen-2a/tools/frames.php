<?php

declare(strict_types=1);

/**
 * GEN-2a · FRAMES A DAY — the counter the v4.4 rule is judged by.
 *
 * `lesson_day.v4.4` lets the model decide how many frames a day has: every answer/ask line stands on a frame,
 * one frame may carry two exchanges with different fillers, and the count stays between half the answer/ask
 * exchanges and all of them. For every run in `runs.json` (or the scene ids given) this prints the exchanges
 * by kind, the frames, how many frames carry two exchanges or more, the frames without a slot, and the
 * learner lines without a frame (expected 0).
 *
 *   docker compose exec -T -e DB_DATABASE=wordtrainer_e2e_test app php docs/research/gen-2a/tools/frames.php
 *   docker compose exec -T app php docs/research/gen-2a/tools/frames.php <scene_id> …
 */

use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\Lesson\Message;
use App\Modules\Plan\Domain\ValueObject\ExchangeKind;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$scenes = array_slice($argv, 1);
$labels = [];
if ($scenes === []) {
    foreach (json_decode((string) file_get_contents(__DIR__.'/../runs.json'), true) as $run) {
        $scenes[] = $run['scene_id'];
        $labels[$run['scene_id']] = $run['slug'];
    }
}

printf("| день | answer | ask | rescue | каркасов | границы v4.4 | каркас в 2+ обменах | без окна | реплик answer/ask без каркаса |\n|---|---|---|---|---|---|---|---|---|\n");
foreach ($scenes as $sceneId) {
    $scene = DB::table('plan_scenes')->where('id', $sceneId)->first();
    if ($scene === null) {
        fwrite(STDERR, "no scene {$sceneId}\n");
        continue;
    }
    $lesson = (new LessonParser)->parse(json_decode((string) $scene->lesson_json, true));
    $kinds = ['answer' => 0, 'ask' => 0, 'rescue' => 0];
    $uses = [];
    $unframed = 0;
    foreach ($lesson->exchanges as $exchange) {
        $kinds[$exchange->kind->value]++;
        if (! $exchange->kind->takesFrame()) {
            continue;
        }
        foreach ($exchange->messages as $message) {
            if ($message->speaker !== Message::SPEAKER_LEARNER) {
                continue;
            }
            if ($message->phraseId === null || $lesson->phrase($message->phraseId) === null) {
                $unframed++;
            } else {
                $uses[$message->phraseId] = ($uses[$message->phraseId] ?? 0) + 1;
            }
        }
    }
    $framed = $kinds[ExchangeKind::Answer->value] + $kinds[ExchangeKind::Ask->value];
    $frames = count($lesson->phrases);
    $reused = count(array_filter($uses, static fn (int $n): bool => $n >= 2));
    $slotless = count(array_filter($lesson->phrases, static fn ($p): bool => $p->slot === null));
    printf(
        "| %s | %d | %d | %d | %d | %d…%d | %d | %d | %d |\n",
        $labels[$sceneId] ?? $scene->title_native, $kinds['answer'], $kinds['ask'], $kinds['rescue'], $frames,
        (int) ceil($framed / 2), $framed, $reused, $slotless, $unframed,
    );
}

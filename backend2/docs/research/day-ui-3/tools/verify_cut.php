<?php

declare(strict_types=1);

/*
 * DOES EVERY CUT PIECE SAY ITS OWN LINE? (DAY-UI-3)
 *
 *   docker compose exec -T app php docs/research/day-ui-3/tools/verify_cut.php <scene_id>
 *
 * The voice of a scene is one vendor sound per script, cut by pauses — a wrong cut plays another
 * line's words under this line's text, and nothing on the phone would say so. This reads every file
 * stored for the scene, has it transcribed (OpenAI `gpt-4o-mini-transcribe`, the project's key;
 * ≈ $0.003 a minute of sound) and prints the transcript beside the line it should be, with the share
 * of the line's words heard (`Words::coverage`). A cut is right when every piece hears its own line.
 */

use App\Modules\Plan\Domain\Service\SpokenLines;
use App\Modules\Plan\Domain\Service\Words;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../../../../vendor/autoload.php';
$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$sceneId = $argv[1] ?? '';
$scene = DB::table('plan_scenes')->where('id', $sceneId)->first();
if ($scene === null) {
    fwrite(STDERR, "no scene {$sceneId}\n");
    exit(1);
}
$lesson = (new App\Modules\Plan\Domain\Lesson\LessonParser())->parse(json_decode((string) $scene->lesson_json, true));
$expected = [];
foreach (SpokenLines::dialogue($lesson) as $line) {
    $expected[$line['ref']] = $line['text'];
}
foreach (DB::table('plan_terms')->where('scene_id', $sceneId)->get() as $term) {
    $expected[$term->ref] = $term->text_target;
}

$rows = DB::table('plan_line_audios')->where('scene_id', $sceneId)->orderBy('created_at')->get();
$disk = Storage::disk((string) config('plan.audio_disk', 'local'));
$worst = 1.0;
foreach ($rows as $row) {
    $bytes = $disk->get($row->path);
    $response = Http::withToken((string) config('services.openai.api_key'))
        ->timeout(60)
        ->attach('file', (string) $bytes, basename($row->path))
        ->post('https://api.openai.com/v1/audio/transcriptions', ['model' => 'gpt-4o-mini-transcribe', 'language' => 'en']);
    $heard = trim((string) $response->json('text'));
    $want = $expected[$row->line_ref] ?? '?';
    $coverage = Words::coverage($want, $heard);
    $worst = min($worst, $coverage);
    printf("%-4s %4d ms  %3d%%  %-60s | %s\n", $row->line_ref, $row->duration_ms, (int) round($coverage * 100), mb_strimwidth($want, 0, 60), $heard);
}
printf("pieces: %d · the worst piece hears %d%% of its line\n", count($rows), (int) round($worst * 100));

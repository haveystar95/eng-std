<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake((string) config('plan.audio_disk'));
});

/** A stored line of the first scene of a fresh plan; `$bytes` null — no file on the disk. `bytes` in the row lies on purpose. */
function audioDurationsLine(object $scene, string $ref, ?int $bytes, ?int $durationMs = null, string $format = 'mp3'): string
{
    $id = Ulid::generate();
    $path = "plan-audio/{$scene->id}/{$ref}-voice.{$format}";
    DB::table('plan_line_audios')->insert([
        'id' => $id, 'scene_id' => $scene->id, 'user_id' => $scene->user_id, 'line_ref' => $ref,
        'voice_key' => 'elevenlabs:eleven_v3:woman:s50', 'format' => $format, 'path' => $path, 'bytes' => 5,
        'duration_ms' => $durationMs, 'characters' => null, 'cost_usd' => null, 'credits' => null, 'request_id' => null, 'created_at' => now(),
    ]);
    if ($bytes !== null) {
        Storage::disk((string) config('plan.audio_disk'))->put($path, str_repeat('a', $bytes));
    }

    return $id;
}

function audioDurationsScene(object $ctx): object
{
    [, $token] = planLearner();
    $plan = planCreate($ctx, $token, ['days_total' => 2])['id'];

    return DB::table('plan_scenes')->where('plan_id', $plan)->orderBy('order')->first(['id', 'user_id']);
}

/** @return array<string, int|null> */
function audioDurationsOf(string ...$ids): array
{
    $out = [];
    foreach ($ids as $id) {
        $value = DB::table('plan_line_audios')->where('id', $id)->value('duration_ms');
        $out[$id] = $value === null ? null : (int) $value;
    }

    return $out;
}

/**
 * THE LENGTH OF A STORED VOICE FILE (наряд SESSION-1a, разд. 5): the backfill measures the FILE with the writer's own
 * estimate — mp3 at 128 kbit/s, 16 000 bytes a second — and never the row's `bytes` column. Catches a backfill that
 * trusts the column, rewrites a length already stored, counts wrong, or is not idempotent.
 */
it('fills the lines without a length from their files, leaves a stored length, and changes nothing on a second run', function () {
    $scene = audioDurationsScene($this);
    $partner = audioDurationsLine($scene, 'x1', 16000);
    $learner = audioDurationsLine($scene, 'x1b', 25539);
    $phrase = audioDurationsLine($scene, 'p1', 48000, 900);

    expect(Artisan::call('plan:audio-durations'))->toBe(0)
        ->and(Artisan::output())->toContain('без длительности: было 2 / стало 0')
        ->and(audioDurationsOf($partner, $learner, $phrase))->toBe([$partner => 1000, $learner => 1596, $phrase => 900]);

    expect(Artisan::call('plan:audio-durations'))->toBe(0)
        ->and(Artisan::output())->toContain('без длительности: было 0 / стало 0')
        ->and(audioDurationsOf($partner, $learner, $phrase))->toBe([$partner => 1000, $learner => 1596, $phrase => 900]);
});

// Catches a backfill that writes a length for a file it could not read (a zero, or the row's `bytes`), or estimates a
// format the mp3 rate does not describe — and one that leaves such a line out of «стало».
it('keeps null for a line whose file is gone, empty or not an mp3, and counts it in стало', function () {
    $scene = audioDurationsScene($this);
    $gone = audioDurationsLine($scene, 'x1', null);
    $empty = audioDurationsLine($scene, 'x2', 0);
    $wav = audioDurationsLine($scene, 'x3', 32000, null, 'wav');
    $kept = audioDurationsLine($scene, 'v1', 32000);

    expect(Artisan::call('plan:audio-durations'))->toBe(0);
    $output = Artisan::output();

    expect($output)->toContain('без длительности: было 4 / стало 3')
        ->and($output)->toContain('файла нет или он пуст — длительность не записана: 2')
        ->and($output)->toContain('не mp3 — длительность по весу не оценивается: 1')
        ->and(audioDurationsOf($gone, $empty, $wav, $kept))->toBe([$gone => null, $empty => null, $wav => null, $kept => 2000]);

    expect(Artisan::call('plan:audio-durations'))->toBe(0)
        ->and(Artisan::output())->toContain('без длительности: было 3 / стало 3');
});

// Catches a --dry that writes.
it('counts without writing under --dry', function () {
    $scene = audioDurationsScene($this);
    $partner = audioDurationsLine($scene, 'x1', 16000);
    $gone = audioDurationsLine($scene, 'x2', null);

    expect(Artisan::call('plan:audio-durations', ['--dry' => true]))->toBe(0);
    $output = Artisan::output();

    expect($output)->toContain('без длительности: было 2 / стало 2')
        ->and($output)->toContain('--dry: ничего не записано; длительность нашлась бы у 1')
        ->and(audioDurationsOf($partner, $gone))->toBe([$partner => null, $gone => null]);

    expect(Artisan::call('plan:audio-durations'))->toBe(0)
        ->and(Artisan::output())->toContain('без длительности: было 2 / стало 1')
        ->and(audioDurationsOf($partner, $gone))->toBe([$partner => 1000, $gone => null]);
});

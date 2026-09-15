<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
});

/**
 * ONE VOICE IN THE PRODUCT (наряд TTS-2, `2026_09_15_200100_drop_voice_of_other_vendors`): every line another vendor
 * said goes — its row and its file — and ElevenLabs' stays. Catches a purge that leaves a file on the disk, a row a
 * reader could never find, or one that takes the new voice with it.
 */
it('drops every line another vendor said, row and file, and keeps the ElevenLabs voice', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $scene = DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->first(['id', 'user_id']);
    $row = static fn (string $ref, string $voice, string $path): array => [
        'id' => Ulid::generate(), 'scene_id' => $scene->id, 'user_id' => $scene->user_id, 'line_ref' => $ref, 'voice_key' => $voice,
        'format' => 'mp3', 'path' => $path, 'bytes' => 5, 'duration_ms' => 900, 'characters' => null, 'cost_usd' => '0.000100', 'credits' => null, 'request_id' => null, 'created_at' => now(),
    ];
    DB::table('plan_line_audios')->insert([
        $row('x1', 'othervendor:tts-model:woman:p90', "plan-audio/{$scene->id}/x1-old.wav"),
        $row('x1b', 'othervendor:tts-model:man:p90', "plan-audio/{$scene->id}/x1b-old.mp3"),
        $row('x1', 'elevenlabs:eleven_v3:woman:s50', "plan-audio/{$scene->id}/x1-new.mp3"),
    ]);
    foreach (['x1-old.wav', 'x1b-old.mp3', 'x1-new.mp3'] as $file) {
        Storage::disk('local')->put("plan-audio/{$scene->id}/{$file}", 'voice');
    }

    (require base_path('app/Modules/Plan/Infrastructure/Migration/2026_09_15_200100_drop_voice_of_other_vendors.php'))->up();

    expect(DB::table('plan_line_audios')->pluck('voice_key')->all())->toBe(['elevenlabs:eleven_v3:woman:s50'])
        ->and(Storage::disk('local')->exists("plan-audio/{$scene->id}/x1-old.wav"))->toBeFalse()
        ->and(Storage::disk('local')->exists("plan-audio/{$scene->id}/x1b-old.mp3"))->toBeFalse()
        ->and(Storage::disk('local')->exists("plan-audio/{$scene->id}/x1-new.mp3"))->toBeTrue();
});

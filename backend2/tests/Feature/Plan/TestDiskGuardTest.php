<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Infrastructure\Adapter\FakeSpeechSynthesizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * THE TREE'S STORAGE IS NOT A TEST'S (наряд ACC-1 §4) — the guard of the test disk `tests/Pest.php` puts under every
 * Feature test.
 *
 * The fake synthesizer answers every line with a real mp3, and the plan files it where the plan files everything it
 * says: on `plan.audio_disk`. The tests that never faked that disk — the talk's voice among them — wrote their files
 * into `storage/app/private/plan-audio` of the tree they ran in: a serial run of the Plan folder left 337 of them. Here a
 * scene is voiced and a talk is said aloud by the fake vendor WITHOUT this file faking anything, and every file must be
 * on the test disk and none under the tree's own storage. Catches the global fake taken down, a disk configured past it,
 * and a store that writes by a path of its own instead of through the disk.
 */

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

it('puts the plan\'s voice and photos on a test disk in every Feature test', function () {
    foreach (testDisks() as $disk) {
        expect(Storage::disk($disk)->path(''))->toStartWith(storage_path('framework/testing/disks/'));
    }
});

it('files a voiced scene and a voiced talk on the test disk and nothing under the tree\'s storage', function () {
    config(['generation.speech.enabled' => true, 'generation.speech.driver' => 'fake']);
    $vendor = new FakeSpeechSynthesizer;
    app()->instance(SpeechSynthesizerPort::class, $vendor);

    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    planOpenDay($this, $token, $id, 1);
    $talk = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$id}/days/1/conversation")->assertOk()->json('data');

    $disk = Storage::disk((string) config('plan.audio_disk'));
    $real = storage_path('app/private');
    $sceneFiles = DB::table('plan_line_audios')->pluck('path')->all();
    $turnFile = (string) DB::table('conversation_turns')->where('conversation_id', $talk['id'])->whereNotNull('audio_path')->value('audio_path');

    expect($vendor->calls)->toBeGreaterThan(1)
        ->and($sceneFiles)->not->toBeEmpty()
        ->and($turnFile)->not->toBe('');
    foreach ([...$sceneFiles, $turnFile] as $path) {
        expect($disk->exists($path))->toBeTrue($path)
            ->and(file_exists($real.'/'.$path))->toBeFalse($path);
    }
});

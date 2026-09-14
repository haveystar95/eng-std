<?php

declare(strict_types=1);

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
 * THE CUT TO FRAMES (наряд GEN-2a, `2026_09_15_110000_drop_plans_built_before_frames`): every plan that exists when
 * the migration runs goes with everything that hangs on it — days, cards, terms, voice rows, the journal and the
 * letters by their foreign keys, the counters of the old lesson versions, the plan's collection as a tombstone, the
 * files of its scenes. Catches a cut that leaves a row, a file or a live collection behind — «снос без остатка».
 */
it('drops every plan with all that hangs on it, tombstones its collection and deletes its scenes\' files', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 1])['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    planWalkDay($this, $token, $id, 1);
    $sceneId = (string) DB::table('plan_scenes')->where('plan_id', $id)->value('id');
    $collectionId = (string) DB::table('plans')->where('id', $id)->value('collection_id');
    Storage::disk('local')->put("plan-audio/{$sceneId}/x1-abc.wav", 'voice');
    Storage::disk('local')->put("plan-images/{$sceneId}/112.jpg", 'photo');
    DB::table('plan_check_counters')->insert([
        ['id' => '01M2PURGECOUNTER0000000001', 'prompt_version' => 'retired-lesson', 'check_name' => 'retired_check', 'action' => 'counted', 'hits' => 4, 'updated_at' => now()],
        ['id' => '01M2PURGECOUNTER0000000002', 'prompt_version' => 'plan-builder-v2', 'check_name' => 'char_limits', 'action' => 'counted', 'hits' => 2, 'updated_at' => now()],
        ['id' => '01M2PURGECOUNTER0000000003', 'prompt_version' => 'lesson_day.v4.4', 'check_name' => 'line.ne_frame', 'action' => 'counted', 'hits' => 1, 'updated_at' => now()],
    ]);
    expect(DB::table('day_cards')->count())->toBeGreaterThan(0)
        ->and(DB::table('plan_events')->where('plan_id', $id)->count())->toBeGreaterThan(0);

    (require base_path('app/Modules/Plan/Infrastructure/Migration/2026_09_15_110000_drop_plans_built_before_frames.php'))->up();

    foreach (['plans', 'plan_scenes', 'plan_days', 'day_cards', 'plan_terms', 'plan_line_audios', 'plan_events', 'plan_notifications'] as $table) {
        expect(DB::table($table)->count())->toBe(0, $table);
    }
    expect(DB::table('plan_check_counters')->orderBy('prompt_version')->pluck('prompt_version')->all())->toBe(['lesson_day.v4.4', 'plan-builder-v2'])
        ->and(DB::table('collections')->where('id', $collectionId)->value('deleted_at'))->not->toBeNull()
        ->and(DB::table('collection_items')->where('collection_id', $collectionId)->count())->toBeGreaterThan(0)
        ->and(Storage::disk('local')->directoryExists("plan-audio/{$sceneId}"))->toBeFalse()
        ->and(Storage::disk('local')->directoryExists("plan-images/{$sceneId}"))->toBeFalse()
        ->and($this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/plans/current')->assertOk()->json('data'))->toBeNull();
});

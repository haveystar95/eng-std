<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Command\AttachPlanImages;
use App\Modules\Plan\Application\Command\AttachPlanImagesHandler;
use App\Modules\Plan\Application\Port\PlanImageFinder;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
});

/**
 * SEAMLESS SCENE PHOTOS (PLAN-UI-3): the photo's tone rides with the photo, the server keeps two
 * square copies and serves them `immutable` under a versioned address, and a copy that is not
 * there is fetched on first request.
 */

/** @return array{0: string, 1: string, 2: string} [token, plan id, first scene id] */
function imgPlan(object $ctx): array
{
    [, $token] = planLearner();
    $build = planCreate($ctx, $token, ['days_total' => 2]);
    $plan = planRead($ctx, $token, $build['id']);

    return [$token, $build['id'], $plan['scenes'][0]['id']];
}

function imgGet(object $ctx, string $token, string $sceneId, int $size, array $headers = []): Illuminate\Testing\TestResponse
{
    return $ctx->withHeaders(['Authorization' => "Bearer {$token}", ...$headers])->get("/api/v1/plans/images/{$sceneId}/{$size}");
}

it('carries the photo’s tone and the two square copies’ versioned addresses on the plan', function () {
    [$token, $id, $sceneId] = imgPlan($this);
    $scene = planRead($this, $token, $id)['scenes'][0];
    $url = (string) DB::table('plan_scenes')->where('id', $sceneId)->value('image_url');
    $version = substr(sha1($url), 0, 12);

    expect($scene['image']['tone'])->toMatch('/^#[0-9A-F]{6}$/')
        ->and($scene['image']['tone'])->toBe(DB::table('plan_scenes')->where('id', $sceneId)->value('image_tone'))
        ->and($scene['image']['url'])->toBe($url)
        ->and($scene['image']['url_112'])->toEndWith("/api/v1/plans/images/{$sceneId}/112?v={$version}")
        ->and($scene['image']['url_448'])->toEndWith("/api/v1/plans/images/{$sceneId}/448?v={$version}")
        ->and($scene['image']['url_112'])->toStartWith('http')
        ->and(planRead($this, $token, $id)['cover_image']['tone'])->toMatch('/^#[0-9A-F]{6}$/');
});

it('serves a stored copy as an immutable jpeg with an ETag', function () {
    [$token, , $sceneId] = imgPlan($this);
    $bytes = 'jpeg-bytes-448';
    Storage::disk('local')->put("plan-images/{$sceneId}/448.jpg", $bytes);

    $response = imgGet($this, $token, $sceneId, 448)->assertOk();

    expect($response->getContent())->toBe($bytes)
        ->and($response->headers->get('Content-Type'))->toBe('image/jpeg')
        ->and($response->headers->get('ETag'))->toBe('"'.sha1($bytes).'"');
    $cache = array_map('trim', explode(',', (string) $response->headers->get('Cache-Control')));
    expect($cache)->toContain('public')->toContain('max-age=31536000')->toContain('immutable')->not->toContain('private');
});

it('keeps the photo copies outside the API throttle — catches a ten-photo route eating the 120/min limit', function () {
    [$token, , $sceneId] = imgPlan($this);
    Storage::disk('local')->put("plan-images/{$sceneId}/112.jpg", 'jpeg-bytes-112');

    $response = imgGet($this, $token, $sceneId, 112)->assertOk();

    // A throttled route answers with its limit headers; this one carries none, and its route has no
    // throttle middleware at all — while the rest of the plan API keeps its 120/min.
    expect($response->headers->has('X-RateLimit-Limit'))->toBeFalse();
    $middleware = static fn (string $uri): array => collect(Illuminate\Support\Facades\Route::getRoutes()->getRoutes())
        ->first(static fn ($r): bool => $r->uri() === $uri)?->gatherMiddleware() ?? [];
    expect(collect($middleware('api/v1/plans/images/{sceneId}/{size}'))->contains(static fn (string $m): bool => str_starts_with($m, 'throttle')))->toBeFalse()
        ->and($middleware('api/v1/plans/versions'))->toContain('throttle:120,1');
});

it('answers 304 to a client that already holds the bytes', function () {
    [$token, , $sceneId] = imgPlan($this);
    Storage::disk('local')->put("plan-images/{$sceneId}/112.jpg", 'jpeg-bytes-112');

    $response = imgGet($this, $token, $sceneId, 112, ['If-None-Match' => '"'.sha1('jpeg-bytes-112').'"']);

    $response->assertStatus(304);
    expect($response->getContent())->toBe('')
        ->and((string) $response->headers->get('Cache-Control'))->toContain('immutable');
    imgGet($this, $token, $sceneId, 112, ['If-None-Match' => '"stale"'])->assertOk();
});

it('hides another learner’s scene photo behind a 404', function () {
    [, , $sceneId] = imgPlan($this);
    Storage::disk('local')->put("plan-images/{$sceneId}/112.jpg", 'jpeg-bytes-112');

    app('auth')->forgetGuards();
    [, $stranger] = planLearner();
    imgGet($this, $stranger, $sceneId, 112)->assertNotFound()->assertJsonPath('code', 'plan_scene_image_not_found');
});

it('answers 404 for a scene without a photo, a size it does not keep, and an id that is not one', function () {
    [$token, , $sceneId] = imgPlan($this);
    DB::table('plan_scenes')->where('id', $sceneId)->update(['image_url' => null, 'image_tone' => null]);

    imgGet($this, $token, $sceneId, 112)->assertNotFound()->assertJsonPath('code', 'plan_scene_image_not_found');
    imgGet($this, $token, $sceneId, 200)->assertNotFound();
    imgGet($this, $token, 'not-a-ulid', 112)->assertNotFound();
});

it('fetches, stores and serves a copy that is missing — the endpoint heals itself', function () {
    [$token, , $sceneId] = imgPlan($this);
    // An address the way the vendor writes it: with its own crop query, which the copy replaces.
    $source = 'https://images.pexels.com/photos/2034335/pexels-photo-2034335.jpeg?auto=compress&cs=tinysrgb&h=627&w=1200';
    DB::table('plan_scenes')->where('id', $sceneId)->update(['image_url' => $source]);

    config(['services.generation.image_driver' => 'pexels']);
    Http::preventStrayRequests();
    Http::fake(['images.pexels.com/*' => Http::response('fetched-448', 200, ['Content-Type' => 'image/jpeg'])]);

    $response = imgGet($this, $token, $sceneId, 448)->assertOk();

    expect($response->getContent())->toBe('fetched-448')
        ->and(Storage::disk('local')->get("plan-images/{$sceneId}/448.jpg"))->toBe('fetched-448');
    Http::assertSent(static fn ($request): bool => $request->url() === 'https://images.pexels.com/photos/2034335/pexels-photo-2034335.jpeg?auto=compress&cs=tinysrgb&fit=crop&w=448&h=448');

    // The second request is the stored file: nothing more on the wire.
    imgGet($this, $token, $sceneId, 448)->assertOk();
    Http::assertSentCount(1);
});

it('answers 503 when the copy cannot be fetched now, and fetches it on a later request', function () {
    [$token, , $sceneId] = imgPlan($this);
    config(['services.generation.image_driver' => 'pexels']);
    Http::preventStrayRequests();
    Http::fakeSequence()->push('', 502)->push('late-bytes', 200, ['Content-Type' => 'image/jpeg']);

    imgGet($this, $token, $sceneId, 112)->assertStatus(503)->assertJsonPath('code', 'plan_scene_image_unavailable');
    expect(imgGet($this, $token, $sceneId, 112)->assertOk()->getContent())->toBe('late-bytes');
});

it('keeps both copies from the photo job once the scene has its photo', function () {
    config(['services.generation.image_driver' => 'pexels']);
    Http::preventStrayRequests();
    Http::fake(['images.pexels.test/*' => Http::response('copy', 200, ['Content-Type' => 'image/jpeg'])]);

    [, , $sceneId] = imgPlan($this);

    expect(Storage::disk('local')->exists("plan-images/{$sceneId}/112.jpg"))->toBeTrue()
        ->and(Storage::disk('local')->exists("plan-images/{$sceneId}/448.jpg"))->toBeTrue();
});

it('writes the tone with the photo and never overwrites it', function () {
    [, $id, $sceneId] = imgPlan($this);
    $tone = DB::table('plan_scenes')->where('id', $sceneId)->value('image_tone');
    $url = DB::table('plan_scenes')->where('id', $sceneId)->value('image_url');
    expect($tone)->not->toBeNull();

    $plans = app(PlanRepository::class);
    expect($plans->attachSceneImage(PlanSceneId::fromString($sceneId), new Image('https://other/x.jpg', null, null, '#000000')))->toBeFalse()
        ->and($plans->attachSceneImageTone(PlanSceneId::fromString($sceneId), (string) $url, '#FFFFFF'))->toBeFalse();

    // The photo job again, with a finder that would bring another photo in another tone.
    app()->instance(PlanImageFinder::class, new class implements PlanImageFinder
    {
        public function find(string $prompt): ?Image
        {
            return new Image('https://other/y.jpg', null, null, '#111111');
        }

        public function tone(string $imageUrl): ?string
        {
            return '#222222';
        }
    });
    app(AttachPlanImagesHandler::class)(new AttachPlanImages(PlanId::fromString($id)));

    $row = DB::table('plan_scenes')->where('id', $sceneId)->first();
    expect($row->image_tone)->toBe($tone)->and($row->image_url)->toBe($url);
});

it('backfills the tone of a photo stored without one, once', function () {
    [, $id, $sceneId] = imgPlan($this);
    DB::table('plan_scenes')->where('plan_id', $id)->update([
        'image_url' => 'https://images.pexels.com/photos/2034335/pexels-photo-2034335.jpeg?auto=compress&cs=tinysrgb&h=627&w=1200',
        'image_tone' => null,
    ]);

    expect(Artisan::call('plan:images-backfill', ['--plan' => $id]))->toBe(0)
        ->and(DB::table('plan_scenes')->where('id', $sceneId)->value('image_tone'))->toBe('#978E82')
        ->and(Artisan::output())->toContain('tones written: 2');

    Artisan::call('plan:images-backfill', ['--plan' => $id]);
    expect(Artisan::output())->toContain('tones written: 0');
});

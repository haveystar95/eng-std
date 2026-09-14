<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Command\AttachPlanImages;
use App\Modules\Plan\Application\Command\AttachPlanImagesHandler;
use App\Modules\Plan\Application\Dto\SpokenAudio;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Port\PlanImageFinder;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
});

/**
 * «ОКНО ДНЯ» OVER HTTP (DAY-UI-2): `window` beside the room — one word for the day, stage rows with
 * a number on the current one only, the programme with the counts the brows are worded from, the
 * one action; photos for every word card and the voice of every phrase. Helpers: `tests/Pest.php`.
 */

/** @return array<string, mixed> */
function windowOf(object $ctx, string $token, string $id, int $number): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/{$number}")->assertOk()->json('data.window');
}

/** Answer every open card of one stage; the choose card of `$failRef` fails twice (it returns tomorrow). */
function windowWalkStage(object $ctx, string $token, string $id, string $stage, ?string $failRef = null): void
{
    $queue = array_values(array_filter(
        planOpenDay($ctx, $token, $id, 1)['cards'],
        static fn (array $c): bool => $c['stage'] === $stage && $c['result'] === null,
    ));
    while ($queue !== []) {
        $card = array_shift($queue);
        $fail = $card['unit_ref'] === $failRef && $card['kind'] === 'word_choose';
        $outcome = planAnswer($ctx, $token, $id, 1, $card['id'], $fail ? 'failed' : 'passed', $fail ? 2 : 1);
        if ($outcome['requeued'] !== null) {
            $queue[] = $outcome['requeued'];
        }
    }
}

/** A finder that finds a toned photo for every query except the ones named. */
function windowFinder(array $nothingFor = []): PlanImageFinder
{
    return new class($nothingFor) implements PlanImageFinder
    {
        /** @var list<string> */
        public array $asked = [];

        /** @param list<string> $nothingFor */
        public function __construct(private readonly array $nothingFor) {}

        public function find(string $prompt): ?Image
        {
            $this->asked[] = $prompt;

            return in_array($prompt, $this->nothingFor, true)
                ? null
                : new Image('https://images.pexels.test/'.md5($prompt).'.jpg', 'Fake', null, '#'.strtoupper(substr(md5($prompt), 0, 6)));
        }

        public function tone(string $imageUrl): ?string
        {
            return null;
        }
    };
}

it('opens a day not started with five rows «впереди» and no number, the programme counted and «Начать» (23-0a)', function () {
    [, $token] = planLearner();
    $build = planCreate($this, $token, ['days_total' => 2]);

    $window = windowOf($this, $token, $build['id'], 1);

    expect($window['day']['status'])->toBe('not_started')
        ->and($window['day']['index'])->toBe(1)
        ->and($window['day']['title_native'])->not->toBeEmpty()
        ->and($window['day']['image']['url_448'])->toContain('/api/v1/plans/images/')
        ->and($window['day']['image_tone'])->toMatch('/^#[0-9A-F]{6}$/')
        ->and($window['day']['minutes_estimate'])->toBeGreaterThan(10)
        ->and($window['day']['minutes_spent'])->toBeNull()
        ->and(array_column($window['day']['goals'], 'passed'))->each->toBeFalse()
        ->and(array_column($window['stages'], 'stage'))->toBe(['words', 'phrases', 'dialogue', 'listen', 'speak'])
        ->and(array_unique(array_column($window['stages'], 'state')))->toBe(['locked'])
        ->and(array_filter(array_column($window['stages'], 'done_count'), static fn ($v): bool => $v !== null))->toBe([])
        ->and(array_filter(array_column($window['stages'], 'total'), static fn ($v): bool => $v !== null))->toBe([])
        ->and($window['day_progress'])->toEqual(0)
        ->and($window['program']['words']['summary'])->toBe(['total' => 8, 'done' => 0, 'returns' => 0])
        ->and($window['program']['words']['items'])->toHaveCount(8)
        ->and($window['program']['phrases']['summary']['total'])->toBe(6)
        ->and($window['program']['dialogue']['items'])->toHaveCount(8)
        ->and($window['allowed_action'])->toBe('start');

    foreach ($window['program']['words']['items'] as $word) {
        expect($word['image_tone'])->toMatch('/^#[0-9A-F]{6}$/')->and($word['state'])->toBe('pending');
    }
});

it('numbers the current row only while the day is walked, and words the brow from the server’s counts (23-0b)', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    windowWalkStage($this, $token, $id, 'words', failRef: 'v2');

    $window = windowOf($this, $token, $id, 1);
    $rows = array_map(static fn (array $s): array => [$s['stage'], $s['state'], $s['done_count'], $s['total']], $window['stages']);

    expect($window['day']['status'])->toBe('in_progress')
        ->and($rows)->toBe([
            ['words', 'done', null, null], ['phrases', 'current', 0, 18], ['dialogue', 'locked', null, null],
            ['listen', 'locked', null, null], ['speak', 'locked', null, null],
        ])
        ->and($window['stages'][1]['minutes_left'])->toBeGreaterThan(0)
        ->and($window['stages'][0]['share'])->toEqual(1)
        ->and($window['day_progress'])->toBe(0.2)
        ->and($window['program']['words']['summary'])->toBe(['total' => 8, 'done' => 7, 'returns' => 1])
        ->and(array_column($window['program']['words']['items'], 'state', 'ref')['v2'])->toBe('returns_tomorrow')
        ->and($window['allowed_action'])->toBe('continue');
});

it('closes a passed day with its minutes, every goal checked, every row full, the returns named and «Ещё раз» (23-0c)', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    foreach (['words', 'phrases', 'dialogue', 'listen', 'speak'] as $stage) {
        windowWalkStage($this, $token, $id, $stage, failRef: $stage === 'words' ? 'v4' : null);
        $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/stages/{$stage}/close")->assertOk();
    }
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/close")->assertOk();

    $window = windowOf($this, $token, $id, 1);

    expect($window['day']['status'])->toBe('passed')
        ->and($window['day']['minutes_spent'])->toBeGreaterThanOrEqual(1)
        ->and($window['day']['minutes_estimate'])->toBeNull()
        ->and(array_column($window['day']['goals'], 'passed'))->each->toBeTrue()
        ->and(array_unique(array_column($window['stages'], 'state')))->toBe(['done'])
        ->and($window['day_progress'])->toEqual(1)
        ->and($window['program']['words']['summary'])->toBe(['total' => 8, 'done' => 7, 'returns' => 1])
        ->and($window['program']['dialogue']['summary'])->toBe(['total' => 8, 'done' => 8, 'returns' => 0])
        ->and($window['allowed_action'])->toBe('again');
});

it('refuses the window of a locked day: no «не начат», no button — catches a start drawn over a day that may not open', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();

    $window = windowOf($this, $token, $id, 2);

    expect($window['day']['status'])->toBe('locked')->and($window['allowed_action'])->toBeNull();
});

it('voices every phrase and the partner’s line, and marks the learner’s line instead — catches a marker on the partner’s bubble', function () {
    $speaker = new class implements LineSpeaker
    {
        /** @var list<string> */
        public array $said = [];

        public function speak(string $text, string $lang): ?SpokenAudio
        {
            $this->said[] = $text;

            return new SpokenAudio('mp3:'.$text, 'mp3', 'test:aoede:p90', 900, '0.001000');
        }

        public function voiceKeyFor(string $lang): ?string
        {
            return 'test:aoede:p90';
        }
    };
    app()->instance(LineSpeaker::class, $speaker);
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];

    $window = windowOf($this, $token, $id, 1);
    $phrases = $window['program']['phrases']['items'];
    $pair = $window['program']['dialogue']['items'][0];

    expect(array_filter(array_column($phrases, 'audio_url'), static fn ($u): bool => ! is_string($u) || ! str_contains($u, '/api/v1/plans/audio/')))->toBe([])
        ->and($pair['partner'])->toHaveKeys(['text', 'translation', 'audio_url'])->not->toHaveKey('state')
        ->and($pair['partner']['audio_url'])->toContain('/api/v1/plans/audio/')
        ->and($pair['learner'])->toHaveKeys(['text', 'translation', 'state'])->not->toHaveKey('audio_url')
        ->and(DB::table('plan_line_audios')->where('line_ref', 'like', 'p%')->count())->toBe(6)
        ->and(DB::table('plan_line_audios')->where('line_ref', 'like', 'x%')->count())->toBe(8);

    // The phrase's file is the one the address names.
    $path = parse_url((string) $phrases[0]['audio_url'], PHP_URL_PATH);
    $bytes = $this->withHeader('Authorization', "Bearer {$token}")->get((string) $path)->assertOk()->getContent();
    expect($bytes)->toBe('mp3:'.$phrases[0]['text']);
});

it('finds a photo for a word the lesson gave no description — by the word itself (находка PHONE-RUN-1 №4)', function () {
    $finder = windowFinder();
    app()->instance(PlanImageFinder::class, $finder);
    [, $token] = planLearner();
    planCreate($this, $token, ['days_total' => 2]);

    // «sharp» has no image_prompt in the lesson: before the ladder it was never searched at all.
    expect(DB::table('plan_terms')->where('text_target', 'sharp')->value('image_url'))->not->toBeNull()
        ->and($finder->asked)->toContain('sharp')
        ->and(DB::table('plan_terms')->whereIn('kind', ['word', 'chunk'])->whereNull('image_url')->count())->toBe(0);
});

it('paints a word nothing was found for with its scene’s tone, counts image_missing once, and does not search it again', function () {
    [, $token] = planLearner();
    $plan = planRead($this, $token, planCreate($this, $token, ['days_total' => 2])['id']);
    $scene = $plan['scenes'][0];
    $titleTarget = (string) DB::table('plan_scenes')->where('id', $scene['id'])->value('title_target');

    // Forget the word's photo, as a lesson written before the ladder left it, and find nothing for it.
    DB::table('plan_terms')->where('text_target', 'numbness')->update(['image_url' => null, 'image_tone' => null]);
    $finder = windowFinder(['numbness', $titleTarget]);
    app()->instance(PlanImageFinder::class, $finder);

    app(AttachPlanImagesHandler::class)(new AttachPlanImages(PlanId::fromString($plan['id'])));
    app(AttachPlanImagesHandler::class)(new AttachPlanImages(PlanId::fromString($plan['id'])));

    $row = DB::table('plan_terms')->where('text_target', 'numbness')->first();
    $word = collect(windowOf($this, $token, $plan['id'], 1)['program']['words']['items'])->firstWhere('term', 'numbness');
    expect($row->image_url)->toBeNull()
        ->and($row->image_tone)->toBe($scene['image']['tone'])
        ->and(array_count_values($finder->asked)['numbness'] ?? 0)->toBe(1)
        ->and(DB::table('plan_check_counters')->where('check_name', 'image_missing')->where('prompt_version', 'lesson-v3')->value('hits'))->toBe(1)
        ->and($word['image'])->toBeNull()
        ->and($word['image_tone'])->toBe($scene['image']['tone']);
});

it('backfills what the plans still lack and says «было пусто / стало»', function () {
    [, $token] = planLearner();
    planCreate($this, $token, ['days_total' => 2]);
    DB::table('plan_terms')->whereIn('text_target', ['sharp', 'numbness'])->update(['image_url' => null, 'image_tone' => '#978E82']);
    app()->instance(PlanImageFinder::class, windowFinder());

    Artisan::call('plan:images-backfill');

    expect(Artisan::output())->toContain('before: scenes 0, words and chunks 2 · after: scenes 0, words and chunks 0')
        ->and(DB::table('plan_terms')->where('text_target', 'sharp')->value('image_url'))->not->toBeNull();
});

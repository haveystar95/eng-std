<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Application\Port\TransientSpeechError;
use App\Modules\Generation\Infrastructure\Adapter\FakeSpeechSynthesizer;
use App\Modules\Plan\Application\Command\FinishIllustration;
use App\Modules\Plan\Application\Command\FinishIllustrationHandler;
use App\Modules\Plan\Application\Command\IllustrateScene;
use App\Modules\Plan\Application\Command\IllustrateSceneHandler;
use App\Modules\Plan\Application\Command\VoiceScene;
use App\Modules\Plan\Application\Command\VoiceSceneHandler;
use App\Modules\Plan\Application\Port\LineAudioStore;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Application\Port\PlanImageFinder;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\ImageQuery;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Infrastructure\Job\VoiceSceneJob;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use Illuminate\Contracts\Queue\Job;
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
 * «ОКНО ДНЯ» OVER HTTP (DAY-UI-2, DAY-UI-3): `window` beside the room — one word for the day, stage
 * rows with a number on the current one only, the programme with the counts the brows are worded from,
 * the one action; every word with its photo, reading, definition, voice and its line of the day, every
 * phrase and every line with its voice. Helpers: `tests/Pest.php`.
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

/**
 * A finder that finds a toned photo for every query except the ones named; it remembers every query
 * (a page past the first as «text #page»), every batch, and the lesson status of the scene at the
 * moment it was asked.
 */
function windowFinder(array $nothingFor = []): PlanImageFinder
{
    return new class($nothingFor) implements PlanImageFinder
    {
        /** @var list<string> */
        public array $asked = [];

        public int $batches = 0;

        /** @var list<string> */
        public array $statusesWhenAsked = [];

        /** @param list<string> $nothingFor */
        public function __construct(private readonly array $nothingFor) {}

        public function find(string $prompt): ?Image
        {
            $this->asked[] = $prompt;

            return in_array($prompt, $this->nothingFor, true)
                ? null
                : new Image('https://images.pexels.test/'.md5($prompt).'.jpg', 'Fake', null, '#'.strtoupper(substr(md5($prompt), 0, 6)));
        }

        public function findMany(array $queries): array
        {
            $this->batches++;
            $this->statusesWhenAsked = [...$this->statusesWhenAsked, ...DB::table('plan_scenes')->whereNotNull('lesson_json')->pluck('lesson_status')->all()];

            return array_map(function (ImageQuery $q): ?Image {
                $found = $this->find($q->text);
                if ($q->page > 1) {
                    $this->asked[count($this->asked) - 1] = "{$q->text} #{$q->page}";
                }

                return $found;
            }, $queries);
        }

        public function tone(string $imageUrl): ?string
        {
            return null;
        }
    };
}

/** The real voice pipe over the fake vendor: speech on, both of the pack's voices, every script remembered. */
function windowVoice(string $mode = FakeSpeechSynthesizer::OK): FakeSpeechSynthesizer
{
    config(['generation.speech.enabled' => true, 'generation.speech.driver' => 'fake']);
    $vendor = new FakeSpeechSynthesizer($mode);
    app()->instance(SpeechSynthesizerPort::class, $vendor);

    return $vendor;
}

/** The voice key a line of this gender is stored under, as the pack configures it. */
function windowVoiceKey(string $gender): string
{
    return (string) app(LineSpeaker::class)->voiceKeyFor('en', VoiceGender::from($gender));
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


// ── DAY-UI-3 · the word card (23-0e) reads off the window: the phone derives nothing ─────────────────

// Catches a sheet that has to guess how a word reads, what it means or where it stands in its line —
// and a line shown for a word the dialogue never says.
it('gives every word its reading, its definition and the line of the day it is said in, with the word’s place in it (23-0e)', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];

    $words = collect(windowOf($this, $token, $id, 1)['program']['words']['items'])->keyBy('term');
    $painkiller = $words['painkiller'];
    $fever = $words['fever'];
    $phrase = windowOf($this, $token, $id, 1)['program']['phrases']['items'][0];

    expect($painkiller['pronunciation'])->toBe('пэйнкилер')
        ->and($painkiller['definition'])->toBe('a medicine that reduces pain')
        ->and($painkiller['usage']['text'])->toBe('Give him a painkiller twice a day after meals.')
        ->and(mb_substr($painkiller['usage']['text'], $painkiller['usage']['offset'], $painkiller['usage']['length']))->toBe('painkiller')
        ->and($painkiller['usage']['translation'])->toBe('Давайте ему обезболивающее два раза в день после еды.')
        ->and(mb_substr($fever['usage']['text'], $fever['usage']['offset'], $fever['usage']['length']))->toBe('fever')
        ->and($words['prescription']['usage'])->toBeNull()
        ->and($phrase['pronunciation'])->toBe('фраза 1');
});

// Catches «вернётся в день N» the phone would have to count.
it('names the day a word that failed twice comes back on (23-0e)', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    windowWalkStage($this, $token, $id, 'words', failRef: 'v2');

    $words = collect(windowOf($this, $token, $id, 1)['program']['words']['items'])->keyBy('ref');

    expect($words['v2']['state'])->toBe('returns_tomorrow')
        ->and($words['v2']['returns_day'])->toBe(2)
        ->and($words['v1']['returns_day'])->toBeNull();
});

// ── DAY-UI-3 · the voice: everything, two voices, a few calls, the vendor's window waited out ────────

// Canon (owner, DAY-UI-3): «озвучиваются все реплики собеседника, все реплики ученика, все фразы, слова».
// Catches the DAY-UI-2 rule: a learner's line, a phrase or a word left to the phone's voice.
it('voices everything a day says: both speakers’ lines, every phrase and every word, each with its own address', function () {
    windowVoice();
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];

    $program = windowOf($this, $token, $id, 1)['program'];
    $lines = $program['dialogue']['items'];
    $urls = [
        ...array_column($program['words']['items'], 'audio_url'),
        ...array_column($program['phrases']['items'], 'audio_url'),
        ...array_map(static fn (array $p): mixed => $p['partner']['audio_url'] ?? null, $lines),
        ...array_map(static fn (array $p): mixed => $p['learner']['audio_url'] ?? null, $lines),
    ];

    expect($urls)->toHaveCount(8 + 6 + 8 + 8)
        ->and(array_filter($urls, static fn (mixed $u): bool => ! is_string($u) || ! str_contains($u, '/api/v1/plans/audio/')))->toBe([])
        ->and(array_unique($urls))->toHaveCount(30)
        ->and($lines[0]['learner'])->toHaveKeys(['text', 'translation', 'audio_url', 'state'])
        ->and($lines[0]['partner'])->not->toHaveKey('state')
        ->and(collect($program['words']['items'])->firstWhere('term', 'painkiller')['usage']['audio_url'])->toBe($lines[5]['partner']['audio_url']);

    // The file behind the learner's address is that line in that voice.
    $path = parse_url((string) $lines[0]['learner']['audio_url'], PHP_URL_PATH);
    $bytes = $this->withHeader('Authorization', "Bearer {$token}")->get((string) $path)->assertOk()->getContent();
    expect($bytes)->toBe('FAKEMP3:'.md5(str_replace(':p90', '', windowVoiceKey('female')).'|p90|en|'.$lines[0]['learner']['text']));
});

// Canon (owner, DAY-UI-3): «собеседник и ученик разного пола; пол собеседника задаёт роль; голос ученика = тот же
// голос для его реплик и фраз». Catches one voice for both people, and a phrase read by the partner's voice.
it('casts a scene’s two voices by the role’s gender: the partner’s lines in one, the learner’s lines, phrases and words in the other', function () {
    windowVoice();
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $sceneId = (string) DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->value('id');
    $keys = DB::table('plan_line_audios')->where('scene_id', $sceneId)->pluck('voice_key', 'line_ref');

    expect(DB::table('plan_scenes')->where('id', $sceneId)->value('partner_voice_gender'))->toBe('male')
        ->and(windowVoiceKey('male'))->not->toBe(windowVoiceKey('female'))
        ->and($keys['x1'])->toBe(windowVoiceKey('male'))
        ->and($keys['x1b'])->toBe(windowVoiceKey('female'))
        ->and($keys['p1'])->toBe(windowVoiceKey('female'))
        ->and($keys['v1'])->toBe(windowVoiceKey('female'));
});

// Canon (DAY-UI-3): «диалог дня — ОДНИМ вызовом с двумя говорящими; слова и фразы — пачками; ≤ 4 вызовов на день».
// Catches a call per line: thirty requests of a vendor that allows a hundred a day.
it('buys a day’s voice in at most four calls — the whole dialogue in one with both voices, the phrases in one, the words in one', function () {
    $vendor = windowVoice();
    [, $token] = planLearner();
    planCreate($this, $token, ['days_total' => 2]);

    $dialogue = array_values(array_filter($vendor->scripts, static fn ($s): bool => count($s->voices) === 2));

    expect($vendor->calls)->toBeLessThanOrEqual(4)
        ->and($vendor->calls)->toBe(3)
        ->and($dialogue)->toHaveCount(1)
        ->and($dialogue[0]->turns)->toHaveCount(16)
        ->and(array_map(static fn ($t): string => $t->speaker, array_slice($dialogue[0]->turns, 0, 2)))->toBe(['male', 'female'])
        ->and(array_sum(array_map(static fn ($s): int => count($s->turns), $vendor->scripts)))->toBe(16 + 6 + 8);
});

// Catches a backfill that buys again what is stored (or swaps a file behind an address a phone cached), and one
// that cannot say what is left by kind.
it('backfills what scenes still lack by kind — the dialogue whole in one call, only its missing lines stored', function () {
    $vendor = windowVoice();
    [, $token] = planLearner();
    planCreate($this, $token, ['days_total' => 2]);
    DB::table('plan_line_audios')->whereIn('line_ref', ['x2', 'x2b', 'p3', 'v5'])->delete();
    $kept = DB::table('plan_line_audios')->where('line_ref', 'x1')->first();
    $vendor->calls = 0;
    $vendor->scripts = [];

    Artisan::call('plan:speak-backfill', ['--count' => true]);
    expect(Artisan::output())->toContain('partner lines 1, learner lines 1, phrases 1, words 1')
        ->and($vendor->calls)->toBe(0);

    Artisan::call('plan:speak-backfill');
    expect(Artisan::output())->toContain('after: partner lines 0, learner lines 0, phrases 0, words 0')
        ->and($vendor->calls)->toBe(3)
        ->and($vendor->scripts[0]->turns)->toHaveCount(16)
        ->and($vendor->scripts[1]->turns)->toHaveCount(1)
        ->and(DB::table('plan_line_audios')->count())->toBe(30)
        ->and(DB::table('plan_line_audios')->where('line_ref', 'x1')->first())->toEqual($kept);
});

// Owner, DAY-UI-3: «51 купленная фраза используется». Catches the phrases bought before the two voices bought
// again in the default learner voice — and a learner whose lines and phrases are two different people.
it('casts a scene written before voices had genders so the phrases already bought stay the learner’s voice', function () {
    $vendor = windowVoice();
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $sceneId = (string) DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->value('id');
    // Back to 14.09: a lesson without role_gender, no cast, only the phrases voiced — in the female voice.
    $lesson = json_decode((string) DB::table('plan_scenes')->where('id', $sceneId)->value('lesson_json'), true);
    unset($lesson['role_gender']);
    DB::table('plan_scenes')->where('id', $sceneId)->update(['lesson_json' => json_encode($lesson), 'partner_voice_gender' => null]);
    DB::table('plan_line_audios')->where('line_ref', 'not like', 'p%')->delete();
    expect(DB::table('plan_line_audios')->distinct()->pluck('voice_key')->all())->toBe([windowVoiceKey('female')]);
    $vendor->calls = 0;
    $vendor->scripts = [];

    app(VoiceSceneHandler::class)(new VoiceScene(PlanSceneId::fromString($sceneId)));

    expect(DB::table('plan_scenes')->where('id', $sceneId)->value('partner_voice_gender'))->toBe('male')
        ->and(array_map(static fn ($s): int => count($s->voices), $vendor->scripts))->toBe([2, 1])
        ->and(DB::table('plan_line_audios')->where('line_ref', 'x1b')->value('voice_key'))->toBe(windowVoiceKey('female'))
        ->and(DB::table('plan_line_audios')->where('line_ref', 'x1')->value('voice_key'))->toBe(windowVoiceKey('male'));
});

// Canon (DAY-UI-3): «при 429 — очередь ждёт до следующего окна, не падает; телефон тем временем читает своим
// голосом». Catches a daily refusal knocked on every minute and failed when the tries run out.
it('waits for the vendor’s next window on a daily refusal and fails nothing — the phone reads meanwhile', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $sceneId = (string) DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->value('id');
    windowVoice(FakeSpeechSynthesizer::DAILY_LIMIT);
    $released = [];
    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('release')->andReturnUsing(function (int $delay) use (&$released): void {
        $released[] = $delay;
    });
    $queueJob->shouldNotReceive('fail');

    $job = new VoiceSceneJob($sceneId);
    $job->setJob($queueJob);
    $job->handle(app(VoiceSceneHandler::class));

    expect($released)->toHaveCount(1)
        ->and($released[0])->toBeGreaterThanOrEqual(3600)->toBeLessThanOrEqual(3620)
        ->and(VoiceSceneJob::waitFor(TransientSpeechError::rateLimited('gemini', 49480, true)))->toBeGreaterThanOrEqual(49480)
        ->and(VoiceSceneJob::waitFor(TransientSpeechError::rateLimited('gemini', null)))->toBeLessThan(90)
        ->and(windowOf($this, $token, $id, 1)['program']['dialogue']['items'][0]['learner']['audio_url'])->toBeNull();
});

// ── DAY-UI-3 · the photos: with the lesson, all at once, never by the bare word ─────────────────────

// Canon (DAY-UI-3): «картинки ко всем словам и сцене дня — в момент генерации дня; у дня в статусе ready картинки
// уже на месте; image_missing = 0 на свежем дне; промпт не по голому слову». Catches photos asked on the first
// open, a day made ready before its pictures, and «sharp» searched alone.
it('finds every photo of a day while its lesson is written — the day is ready with its pictures on it, image_missing 0', function () {
    $finder = windowFinder();
    app()->instance(PlanImageFinder::class, $finder);
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $sceneId = (string) DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->value('id');

    // Nothing is read and nothing is opened yet: the pictures are there.
    expect(DB::table('plan_scenes')->where('id', $sceneId)->value('lesson_status'))->toBe('ready')
        ->and(DB::table('plan_scenes')->where('id', $sceneId)->value('image_url'))->not->toBeNull()
        ->and(DB::table('plan_terms')->where('scene_id', $sceneId)->whereIn('kind', ['word', 'chunk'])->count())->toBe(8)
        ->and(DB::table('plan_terms')->where('scene_id', $sceneId)->whereIn('kind', ['word', 'chunk'])->whereNull('image_url')->count())->toBe(0)
        ->and(DB::table('plan_check_counters')->where('check_name', 'image_missing')->count())->toBe(0)
        ->and($finder->statusesWhenAsked)->not->toBeEmpty()
        ->and(array_unique($finder->statusesWhenAsked))->toBe(['illustrating'])
        ->and(collect($finder->asked)->contains(static fn (string $q): bool => str_starts_with($q, 'sharp, ')))->toBeTrue()
        ->and($finder->asked)->not->toContain('sharp')
        ->and($finder->asked)->not->toContain('numbness')
        ->and(DB::table('plan_events')->where('kind', 'day_ready')->count())->toBe(1);
});

// Invariant review, DAY-UI-3: the photo job and the lesson job start together, and the scene photo often lands while
// the model is answering. Catches the lesson job writing back the scene it read before the call — the photo found
// meanwhile put back as none, its tone lost, and the vendor asked for the same photo again.
it('keeps a scene photo found while the lesson was being written', function () {
    $finder = new class implements PlanImageFinder
    {
        public bool $open = false;

        /** @var list<string> */
        public array $asked = [];

        public function find(string $prompt): ?Image
        {
            $this->asked[] = $prompt;

            return $this->open ? new Image('https://images.pexels.test/'.md5($prompt).'.jpg', 'Fake', null, '#978E82') : null;
        }

        public function findMany(array $queries): array
        {
            return array_map(fn (ImageQuery $q): ?Image => $this->find($q->text), $queries);
        }

        public function tone(string $imageUrl): ?string
        {
            return null;
        }
    };
    app()->instance(PlanImageFinder::class, $finder);
    $midCall = new Image('https://images.pexels.test/found-mid-call.jpg', 'Fake', null, '#123456');
    $landed = null;
    app()->instance(PlanModelPort::class, new FakePlanModel(lesson: function (object $request) use ($finder, $midCall, &$landed): array {
        if ($landed === null) {
            // The model is «answering»; the photo job, running beside the lesson job, finds the scene photo.
            $landed = (string) DB::table('plan_scenes')->where('lesson_status', 'building')->value('id');
            app(PlanRepository::class)->attachSceneImage(PlanSceneId::fromString($landed), $midCall);
            $finder->open = true;
        }

        return FakePlanModel::lessonPayload($request);
    }));
    [, $token] = planLearner();
    planCreate($this, $token, ['days_total' => 2]);

    $scene = DB::table('plan_scenes')->where('id', $landed)->first();
    expect($scene->image_url)->toBe($midCall->url)
        ->and($scene->image_tone)->toBe('#123456')
        ->and($scene->lesson_status)->toBe('ready')
        ->and(DB::table('plan_terms')->where('scene_id', $landed)->whereIn('kind', ['word', 'chunk'])->whereNull('image_url')->count())->toBe(0);
});

// Catches a day left «building» for good when the photo job gives up.
it('makes the day ready without the photos it did not get when the photo job gives up', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $sceneId = (string) DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->value('id');
    DB::table('plan_scenes')->where('id', $sceneId)->update(['lesson_status' => 'illustrating']);
    DB::table('plan_events')->where('kind', 'day_ready')->delete();
    expect(planRead($this, $token, $id)['days'][0]['lesson_status'])->toBe('building');

    app(FinishIllustrationHandler::class)(new FinishIllustration(PlanSceneId::fromString($sceneId)));

    expect(DB::table('plan_scenes')->where('id', $sceneId)->value('lesson_status'))->toBe('ready')
        ->and(DB::table('plan_events')->where('kind', 'day_ready')->count())->toBe(1)
        ->and(planRead($this, $token, $id)['days'][0]['lesson_status'])->toBe('ready');
});

it('paints a word nothing was found for with its scene’s tone, counts image_missing once, and does not search it again', function () {
    [, $token] = planLearner();
    $plan = planRead($this, $token, planCreate($this, $token, ['days_total' => 2])['id']);
    $scene = $plan['scenes'][0];
    $theme = (string) DB::table('plan_scenes')->where('id', $scene['id'])->value('title_target');

    // Forget the word's photo, as a lesson written before the ladder left it, and find nothing for it.
    DB::table('plan_terms')->where('text_target', 'numbness')->update(['image_url' => null, 'image_tone' => null]);
    $finder = windowFinder(["numbness, {$theme}", $theme]);
    app()->instance(PlanImageFinder::class, $finder);

    app(IllustrateSceneHandler::class)(new IllustrateScene(PlanSceneId::fromString($scene['id'])));
    app(IllustrateSceneHandler::class)(new IllustrateScene(PlanSceneId::fromString($scene['id'])));

    $row = DB::table('plan_terms')->where('text_target', 'numbness')->first();
    $word = collect(windowOf($this, $token, $plan['id'], 1)['program']['words']['items'])->firstWhere('term', 'numbness');
    expect($row->image_url)->toBeNull()
        ->and($row->image_tone)->toBe($scene['image']['tone'])
        ->and(array_count_values($finder->asked)["numbness, {$theme}"] ?? 0)->toBe(1)
        ->and(DB::table('plan_check_counters')->where('check_name', 'image_missing')->where('prompt_version', 'lesson-v4')->value('hits'))->toBe(1)
        ->and($word['image'])->toBeNull()
        ->and($word['image_tone'])->toBe($scene['image']['tone']);
});

// Canon (DAY-UI-3): «бэкфилл существующих планов командой; «было пусто / стало»». Catches old days left with a
// supermarket for «marketing», and with the same picture on two words or on a word and its plate.
it('backfills what the plans still lack and says «было пусто / стало»; --requery re-asks the bare word’s photos and a day’s repeats', function () {
    [, $token] = planLearner();
    planCreate($this, $token, ['days_total' => 2]);
    DB::table('plan_terms')->whereIn('text_target', ['sharp', 'numbness'])->update(['image_url' => null, 'image_tone' => '#978E82']);
    app()->instance(PlanImageFinder::class, windowFinder());

    Artisan::call('plan:images-backfill');
    expect(Artisan::output())->toContain('before: scenes 0, words and chunks 2 · after: scenes 0, words and chunks 0')
        ->and(DB::table('plan_terms')->where('text_target', 'sharp')->value('image_url'))->not->toBeNull();

    // A photo the bare word found before DAY-UI-3 («marketing» → a supermarket): asked the new ladder, replaced.
    DB::table('plan_terms')->where('text_target', 'sharp')->update(['image_url' => 'https://images.pexels.test/supermarket.jpg']);
    // Two words the old ladder gave one picture, and a word showing the day's plate: the first holder keeps its
    // picture, the others are asked again.
    $sceneId = (string) DB::table('plan_terms')->where('text_target', 'sharp')->value('scene_id');
    $plate = (string) DB::table('plan_scenes')->where('id', $sceneId)->value('image_url');
    $words = DB::table('plan_terms')->where('scene_id', $sceneId)->whereIn('kind', ['word', 'chunk'])->whereNotNull('image_prompt')
        ->where('image_prompt', '<>', '')->orderBy('position')->pluck('text_target')->all();
    DB::table('plan_terms')->where('scene_id', $sceneId)->whereIn('text_target', [$words[0], $words[1]])->update(['image_url' => 'https://images.pexels.test/same.jpg']);
    DB::table('plan_terms')->where('scene_id', $sceneId)->where('text_target', $words[2])->update(['image_url' => $plate]);
    Artisan::call('plan:images-backfill', ['--requery' => true]);
    expect(Artisan::output())->toContain('photographed by the bare word: 2, repeating a picture of their day: 2')
        ->and(DB::table('plan_terms')->where('text_target', 'sharp')->value('image_url'))->not->toBe('https://images.pexels.test/supermarket.jpg')
        ->and(DB::table('plan_terms')->where('scene_id', $sceneId)->where('text_target', $words[0])->value('image_url'))->toBe('https://images.pexels.test/same.jpg')
        ->and(DB::table('plan_terms')->where('scene_id', $sceneId)->where('text_target', $words[1])->value('image_url'))->not->toBe('https://images.pexels.test/same.jpg')
        ->and(DB::table('plan_terms')->where('scene_id', $sceneId)->where('text_target', $words[2])->value('image_url'))->not->toBe($plate);
});

// Live check 14.09: the vendor answers «appointment, doctor's office» and «dizzy, doctor's office» with one photo.
// Catches a day's grid that shows the same picture on two words.
it('shows no picture twice in a day: a word whose photo repeats another asks its query for the next page', function () {
    app()->instance(PlanImageFinder::class, new class implements PlanImageFinder
    {
        public function find(string $prompt): ?Image
        {
            return new Image('https://images.pexels.test/'.md5($prompt).'.jpg', null, null, '#978E82');
        }

        public function findMany(array $queries): array
        {
            // Every «word, theme» question gets the same first photo; a later page gets another.
            return array_map(static fn (ImageQuery $q): Image => new Image(
                'https://images.pexels.test/'.(str_contains($q->text, ', ') ? 'theme' : md5($q->text)).'-p'.$q->page.'.jpg', null, null, '#978E82',
            ), $queries);
        }

        public function tone(string $imageUrl): ?string
        {
            return null;
        }
    });
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $sceneId = (string) DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->value('id');

    $urls = DB::table('plan_terms')->where('scene_id', $sceneId)->whereIn('kind', ['word', 'chunk'])->pluck('image_url')->all();

    expect($urls)->toHaveCount(8)
        ->and(array_unique($urls))->toHaveCount(8)
        ->and($urls)->toContain('https://images.pexels.test/theme-p1.jpg')
        ->and($urls)->toContain('https://images.pexels.test/theme-p2.jpg');
});

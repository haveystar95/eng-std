<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\SpeechBalance;
use App\Modules\Generation\Application\Port\SpeechAccountError;
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
use App\Modules\Plan\Application\Service\SceneVoiceQueue;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\Image;
use App\Modules\Plan\Domain\ValueObject\ImageQuery;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Plan\Infrastructure\Job\VoiceSceneJob;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\Service\VoiceCatalog;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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

/** The real voice pipe over the fake vendor: speech on, the pack's voices, every call remembered. */
function windowVoice(string $mode = FakeSpeechSynthesizer::OK, ?SpeechBalance $balance = null): FakeSpeechSynthesizer
{
    config(['generation.speech.enabled' => true, 'generation.speech.driver' => 'fake']);
    $vendor = new FakeSpeechSynthesizer($mode, $balance);
    app()->instance(SpeechSynthesizerPort::class, $vendor);

    return $vendor;
}

/** The voice key a line of this speaker and gender is stored under, as the pack configures it. */
function windowVoiceKey(string $speaker, string $gender): string
{
    return (string) app(LineSpeaker::class)->voiceKeyFor('en', Speaker::from($speaker), VoiceGender::from($gender));
}

/**
 * Every address a day's window gives its voice: words, the lines they are said in, phrases, every filler of every
 * frame, both lines of every exchange.
 *
 * @param  array<string, mixed>  $program
 * @return array{words: list<mixed>, usages: list<mixed>, phrases: list<mixed>, fillers: list<mixed>, partner: list<mixed>, learner: list<mixed>}
 */
function windowVoiceUrls(array $program): array
{
    $phrases = $program['phrases']['items'];
    $lines = $program['dialogue']['items'];

    return [
        'words' => array_column($program['words']['items'], 'audio_url'),
        'usages' => array_values(array_filter(array_map(static fn (array $w): mixed => $w['usage']['audio_url'] ?? false, $program['words']['items']), static fn (mixed $u): bool => $u !== false)),
        'phrases' => array_column($phrases, 'audio_url'),
        'fillers' => array_column(array_merge(...array_map(static fn (array $p): array => $p['frame']['slot']['fillers'] ?? [], $phrases)), 'audio_url'),
        'partner' => array_map(static fn (array $pair): mixed => $pair['partner']['audio_url'] ?? null, $lines),
        'learner' => array_map(static fn (array $pair): mixed => $pair['learner']['audio_url'] ?? null, $lines),
    ];
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
    $pad = $words['heating pad'];
    $fever = $words['fever'];
    $phrase = windowOf($this, $token, $id, 1)['program']['phrases']['items'][0];

    expect($pad['pronunciation'])->toBe('хитинг пэд')
        ->and($pad['definition'])->toBe('a warm pad you put on a painful place')
        ->and($pad['usage']['text'])->toBe('It looks like a muscle strain, so he should rest and use a heating pad.')
        ->and(mb_substr($pad['usage']['text'], $pad['usage']['offset'], $pad['usage']['length']))->toBe('heating pad')
        ->and($pad['usage']['translation'])->toBe('Похоже на растяжение мышцы, так что ему нужен покой и грелка.')
        ->and(mb_substr($fever['usage']['text'], $fever['usage']['offset'], $fever['usage']['length']))->toBe('fever')
        // A filler the dialogue never says: the word has no line of the day.
        ->and($words['sick note']['usage'])->toBeNull()
        // A phrase reads as its frame said with the dialogue's filler.
        ->and($phrase['pronunciation'])->toBe('ит хёртс ин хиз лоуэр бэк');
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

// ── TTS-2 · the voice: ElevenLabs, everything a day says, a line a call, the account's refusals ────────────────────

// Canon (owner, DAY-UI-3; TTS-2): «озвучиваются все реплики собеседника, все реплики ученика, все фразы, фразы с
// наполнениями, слова». Catches a learner's line, a phrase, a filler or a word left to the phone's voice, and a filler
// bought again though its phrase already says it.
it('voices everything a day says: both speakers’ lines, every phrase with each of its fillers and every word, each at its own address', function () {
    windowVoice();
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];

    $program = windowOf($this, $token, $id, 1)['program'];
    $urls = windowVoiceUrls($program);
    $all = [...$urls['words'], ...$urls['phrases'], ...$urls['partner'], ...$urls['learner'], ...$urls['fillers']];

    expect($urls['fillers'])->toHaveCount(15)
        ->and(array_filter($all, static fn (mixed $u): bool => ! is_string($u) || ! str_contains($u, '/api/v1/plans/audio/')))->toBe([])
        ->and(array_unique([...$urls['words'], ...$urls['phrases'], ...$urls['partner'], ...$urls['learner']]))->toHaveCount(30)
        ->and(array_unique($urls['fillers']))->toHaveCount(15)
        // The filler each phrase is said with IS the phrase: one file, not two.
        ->and(array_intersect($urls['fillers'], $urls['phrases']))->toHaveCount(5)
        ->and(collect($program['words']['items'])->firstWhere('term', 'heating pad')['usage']['audio_url'])->toBe($program['dialogue']['items'][4]['partner']['audio_url']);

    // The file behind the learner's address is that line in the learner's voice.
    $line = $program['dialogue']['items'][0]['learner'];
    $bytes = $this->withHeader('Authorization', "Bearer {$token}")->get((string) parse_url((string) $line['audio_url'], PHP_URL_PATH))->assertOk()->getContent();
    expect($bytes)->toBe('FAKEMP3:'.md5(preg_replace('/:(s\d+)$/', '|$1', windowVoiceKey('learner', 'male')).'|'.$line['text']));
});

// Canon (TTS-2): «собеседник-женщина, ученик-мужчина; для сцен с мужским собеседником — мужской голос собеседника, ученик
// тогда женским». Catches one voice for both people, a phrase read by the partner, and a man partner in the learner's voice.
it('casts a scene’s voices by role and gender: a man partner has a voice of his own, and the learner is then the woman', function () {
    windowVoice();
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $sceneId = (string) DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->value('id');
    $keys = static fn (): array => DB::table('plan_line_audios')->where('scene_id', $sceneId)->pluck('voice_key', 'line_ref')->all();

    expect(DB::table('plan_scenes')->where('id', $sceneId)->value('partner_voice_gender'))->toBe('female')
        ->and($keys())->toMatchArray([
            'x1' => windowVoiceKey('partner', 'female'),
            'x1b' => windowVoiceKey('learner', 'male'),
            'p1' => windowVoiceKey('learner', 'male'),
            'p1.f2' => windowVoiceKey('learner', 'male'),
            'v1' => windowVoiceKey('learner', 'male'),
        ]);

    DB::table('plan_line_audios')->where('scene_id', $sceneId)->delete();
    DB::table('plan_scenes')->where('id', $sceneId)->update(['partner_voice_gender' => 'male']);
    app(VoiceSceneHandler::class)(new VoiceScene(PlanSceneId::fromString($sceneId)));

    expect(windowVoiceKey('partner', 'male'))->not->toBe(windowVoiceKey('learner', 'male'))
        ->and($keys())->toMatchArray([
            'x1' => windowVoiceKey('partner', 'male'),
            'x1b' => windowVoiceKey('learner', 'female'),
            'p1' => windowVoiceKey('learner', 'female'),
            'v1' => windowVoiceKey('learner', 'female'),
        ]);
});

// Canon (TTS-2, architect's clarification): «Text to Dialogue не использовать: каждая реплика собеседника и ученика —
// отдельный вызов своим голосом; модель везде — v3 Conversational». Catches a dialogue said as one sound, a learner's line
// in the partner's voice, and a line bought on another model.
it('says every line of a day on a call of its own — each dialogue line in its speaker’s voice — on v3 Conversational', function () {
    $vendor = windowVoice();
    [, $token] = planLearner();
    planCreate($this, $token, ['days_total' => 2]);

    $voiceOf = static fn (string $text): array => array_values(array_unique(array_map(
        static fn ($l): string => $l->voice->voice,
        array_filter($vendor->lines, static fn ($l): bool => $l->text === $text),
    )));
    $partner = (string) config('generation.speech.voices.en.partner.female.voice');
    $learner = (string) config('generation.speech.voices.en.learner.male.voice');

    expect($vendor->calls)->toBe(16 + 6 + 10 + 8)
        ->and($vendor->lines)->toHaveCount(40)
        ->and(array_unique(array_map(static fn ($l): string => $l->voice->model, $vendor->lines)))->toBe(['eleven_v3_conversational'])
        ->and($voiceOf('Where does it hurt: his upper back or his lower back?'))->toBe([$partner])
        ->and($voiceOf('It hurts in his lower back.'))->toBe([$learner])
        ->and($partner)->not->toBe($learner);
});

// Canon (TTS-2): «у каждой строки урока есть audio_url после бэкфилла». Catches a backfill that leaves a kind behind (the
// fillers are new), counts by the old four kinds, or buys again what is stored.
it('backfills a lesson whole: after plan:speak-backfill every word, line, phrase, filler and both speakers have an audio_url', function () {
    [, $token] = planLearner();
    // Built while speech was off: the lesson is there, its voice is not.
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $vendor = windowVoice();
    $before = windowVoiceUrls(windowOf($this, $token, $id, 1)['program']);
    expect(array_filter(array_merge(...array_values($before))))->toBe([]);

    Artisan::call('plan:speak-backfill', ['--count' => true]);
    $counted = Artisan::output();
    expect($counted)->toContain('partner lines 8, learner lines 8, phrases 6, fillers 10, words 8')
        ->and($vendor->calls)->toBe(0);

    Artisan::call('plan:speak-backfill');
    $output = Artisan::output();
    $after = windowVoiceUrls(windowOf($this, $token, $id, 1)['program']);

    // TTS-2: «цену назвать до покупки» — the price --count names is the bill the run then brings, in all three units.
    $credits = (int) DB::table('plan_line_audios')->sum('credits');
    expect($output)->toContain('after: partner lines 0, learner lines 0, phrases 0, fillers 0, words 0')
        ->and($output)->toContain('bought: 40 lines')
        ->and($counted)->toContain(sprintf('Would cost about %d credits · %d characters · $%s', $credits, (int) DB::table('plan_line_audios')->sum('characters'), number_format($credits / 1000 * 0.20, 4, '.', '')))
        ->and($after['usages'])->not->toBeEmpty();
    foreach ($after as $kind => $urls) {
        expect($urls)->not->toBeEmpty()
            ->and(array_filter($urls, static fn (mixed $u): bool => ! is_string($u)))->toBe([], "{$kind} without a voice");
    }

    $vendor->calls = 0;
    Artisan::call('plan:speak-backfill');
    expect($vendor->calls)->toBe(0)->and(DB::table('plan_line_audios')->count())->toBe(40);
});

// Canon (TTS-2): «в plan_line_audios — символы и стоимость по тарифу модели; сводка на день: символы, $, число вызовов».
// Catches a row without its bill, a price that is not the vendor's charge, and calls miscounted.
it('writes the characters, their price, the credits debited and the call at every line, and plan:speak-report adds up a day by kind', function () {
    windowVoice();
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $rows = DB::table('plan_line_audios')->get();

    expect($rows)->toHaveCount(40);
    foreach ($rows as $row) {
        expect($row->characters)->toBeGreaterThan(0, $row->line_ref)
            ->and($row->credits)->toBeGreaterThan(0, $row->line_ref)
            // The price is the vendor's charge — credits at the account's credit price — not the characters.
            ->and($row->cost_usd)->toBe(number_format($row->credits * 0.20 / 1000, 6, '.', ''), $row->line_ref)
            ->and($row->request_id)->not->toBeNull();
    }
    expect($rows->pluck('request_id')->unique())->toHaveCount(40);

    Artisan::call('plan:speak-report', ['--plan' => $id, '--day' => '1']);
    $report = Artisan::output();
    $characters = (int) $rows->sum('characters');
    $credits = (int) $rows->sum('credits');
    expect($report)->toContain('partner lines')->toContain('learner lines')->toContain('fillers')->toContain('words')
        ->and($report)->toMatch('/total\s*\|\s*40\s*\|\s*'.$characters.'\s*\|\s*\$'.preg_quote(number_format($credits * 0.20 / 1000, 4, '.', ''), '/').'\s*\|\s*'.$credits.'\s*\|\s*40\s*\|/');

    Artisan::call('plan:speak-report');
    expect(Artisan::output())->toContain($id);
});

// Canon (TTS-2): «401/402 (нет баланса) → job в failed с кодом, письмо в лог, окно дня читает голосом телефона; при 402
// очередь не падает». Catches a refusal of the account retried as if it were the concurrency limit, one that throws past
// the job and takes the day's photos down with it, and a day that does not open.
it('fails the voice job with the vendor’s code when the account refuses (402), goes on with the queue, and the day reads with the phone’s voice', function () {
    $vendor = windowVoice(FakeSpeechSynthesizer::NO_CREDITS);
    Log::spy();
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $sceneId = (string) DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->value('id');

    // The queue went on: the photo job after the voice job ran, the day is ready and opens.
    expect(DB::table('plan_scenes')->where('id', $sceneId)->value('lesson_status'))->toBe('ready')
        ->and(DB::table('plan_scenes')->where('id', $sceneId)->value('image_url'))->not->toBeNull()
        ->and($vendor->calls)->toBe(1)
        ->and(DB::table('plan_line_audios')->count())->toBe(0);
    $urls = windowVoiceUrls(windowOf($this, $token, $id, 1)['program']);
    expect(array_filter(array_merge(...array_values($urls))))->toBe([]);
    Log::shouldHaveReceived('error')->withArgs(static fn (string $message, array $context): bool => str_contains($message, 'quota_exceeded') && $context['code'] === 'quota_exceeded' && $context['scene_id'] === $sceneId);

    // The job itself: failed with the code, never released to knock again.
    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldReceive('fail')->once()->withArgs(static fn ($e): bool => $e instanceof SpeechAccountError && $e->vendorCode === 'quota_exceeded');
    $queueJob->shouldNotReceive('release');
    $job = new VoiceSceneJob($sceneId);
    $job->setJob($queueJob);
    $job->handle(app(VoiceSceneHandler::class));
});

// Canon (TTS-2): «429 / лимит одновременности → повтор с задержкой, очередь не падает». Catches a transient refusal that
// fails the job.
it('waits out the vendor’s concurrency limit — the job goes back on the queue and fails nothing', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $sceneId = (string) DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->value('id');
    windowVoice(FakeSpeechSynthesizer::RATE_LIMITED);
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
        ->and($released[0])->toBeGreaterThanOrEqual(30)->toBeLessThanOrEqual(40)
        ->and(VoiceSceneJob::waitFor(TransientSpeechError::rateLimited('vendor', 7200)))->toBeLessThanOrEqual(3610);
});

// Canon (TTS-2): «при остатке кредитов плана < 10 % — стоп очереди и письмо в лог (баланс — по API аккаунта, если отдаёт;
// иначе по накопленному счётчику)». Catches a fuse that does not stop the queue, and one blind when the vendor says nothing.
it('stops buying below a tenth of the account left — by the vendor’s count, else by the lines stored this month — and says so', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $sceneId = (string) DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->value('id');
    $vendor = windowVoice(balance: new SpeechBalance(used: 9100, limit: 10000, resetsAtUnix: null));
    Log::spy();

    (new VoiceSceneJob($sceneId))->handle(app(VoiceSceneHandler::class));
    Artisan::call('plan:speak-backfill');

    expect($vendor->calls)->toBe(0)
        ->and(Artisan::output())->toContain('Stopped by the voice fuse')->toContain('900 of 10000 credits left')
        ->and(DB::table('plan_line_audios')->count())->toBe(0);
    Log::shouldHaveReceived('error')->withArgs(static fn (string $message, array $context): bool => str_contains($message, 'voice fuse') && $context['remaining'] === 900);

    // The vendor would not say: the credits of the lines stored since the start of the month count against the plan size.
    $vendor->balance = null;
    config(['generation.speech.monthly_credits' => 1000]);
    app()->forgetInstance(VoiceSceneHandler::class);
    DB::table('plan_line_audios')->insert([
        'id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(), 'scene_id' => $sceneId, 'user_id' => (string) DB::table('plan_scenes')->where('id', $sceneId)->value('user_id'),
        'line_ref' => 'v12', 'voice_key' => windowVoiceKey('learner', 'male'), 'format' => 'mp3', 'path' => 'plan-audio/x.mp3', 'bytes' => 1,
        'characters' => 1900, 'cost_usd' => '0.190000', 'credits' => 950, 'request_id' => 'earlier', 'created_at' => now(),
    ]);
    Artisan::call('plan:speak-backfill');
    expect($vendor->calls)->toBe(0)->and(Artisan::output())->toContain('50 of 1000 credits left');
});

// Canon (TTS-2): «порядок: план Дена → сцены симулятора → остальное». Catches a backfill that serves a QA account before a
// real learner, and a --plan that buys anything but the plans named.
it('backfills real learners before QA accounts, and --plan only the plans named', function () {
    config(['qa.dev_login' => true]);
    [$qaUser, $qaToken] = planLearner();
    DB::table('users')->where('id', $qaUser->id)->update(['is_qa' => true]);
    $qaPlan = planCreate($this, $qaToken, ['days_total' => 2])['id'];
    [, $token] = planLearner();
    // The guard remembers the first bearer's user between requests of one test.
    app('auth')->forgetGuards();
    $plan = planCreate($this, $token, ['days_total' => 2])['id'];
    // The QA plan is the newer one — and still comes second.
    DB::table('plans')->where('id', $qaPlan)->update(['created_at' => now()->addMinute()]);
    $sceneOf = static fn (string $id): string => (string) DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->value('id');
    windowVoice();
    $store = new class(app(LineAudioStore::class)) implements LineAudioStore
    {
        /** @var list<string> the scene of every line stored, in the order stored */
        public array $scenes = [];

        public function __construct(private readonly LineAudioStore $inner) {}

        public function forScenes(array $sceneIds, array $voiceKeys): array
        {
            return $this->inner->forScenes($sceneIds, $voiceKeys);
        }

        public function find(string $audioId): ?\App\Modules\Plan\Application\Dto\LineAudioRow
        {
            return $this->inner->find($audioId);
        }

        public function put(PlanSceneId $sceneId, string $lineRef, \App\Modules\Plan\Application\Dto\SpokenAudio $audio): ?\App\Modules\Plan\Application\Dto\LineAudioRow
        {
            $this->scenes[] = $sceneId->value;

            return $this->inner->put($sceneId, $lineRef, $audio);
        }

        public function read(\App\Modules\Plan\Application\Dto\LineAudioRow $row): ?string
        {
            return $this->inner->read($row);
        }

        public function ofScene(PlanSceneId $sceneId): array
        {
            return $this->inner->ofScene($sceneId);
        }

        public function drop(\App\Modules\Plan\Application\Dto\LineAudioRow $row): void
        {
            $this->inner->drop($row);
        }

        public function creditsSince(\DateTimeImmutable $since): int
        {
            return $this->inner->creditsSince($since);
        }
    };
    app()->instance(LineAudioStore::class, $store);

    Artisan::call('plan:speak-backfill', ['--plan' => [$qaPlan]]);
    expect(array_values(array_unique($store->scenes)))->toBe([$sceneOf($qaPlan)]);

    DB::table('plan_line_audios')->delete();
    $store->scenes = [];
    Artisan::call('plan:speak-backfill');

    expect(array_values(array_unique($store->scenes)))->toBe([$sceneOf($plan), $sceneOf($qaPlan)]);
});

// Canon (TTS-2, доработка): a voice of the pack changed — «переозвучить строки ученика, остальное не трогать». The voice is
// a key of the file (DECISIONS п. 248), so the speaker's old files are read by nobody. Catches a re-voicing that leaves the
// dead files behind, one that buys the partner's lines again, and — the dangerous one — speech switched off read as «every
// file is stale».
it('re-voices with --drop-unread only the lines whose speaker’s voice changed, and drops nothing while speech is off', function () {
    $vendor = windowVoice();
    [, $token] = planLearner();
    planCreate($this, $token, ['days_total' => 2]);
    $partnerLine = DB::table('plan_line_audios')->where('line_ref', 'x1')->first();
    $learnerPath = (string) DB::table('plan_line_audios')->where('line_ref', 'x1b')->value('path');
    config(['generation.speech.voices.en.learner.male.voice' => 'another-learner-man']);
    app()->forgetInstance(VoiceCatalog::class);
    $vendor->calls = 0;

    Artisan::call('plan:speak-backfill', ['--count' => true]);
    expect(Artisan::output())->toContain('partner lines 0, learner lines 8, phrases 6, fillers 10, words 8');

    Artisan::call('plan:speak-backfill', ['--drop-unread' => true]);
    $output = Artisan::output();
    $learnerKeys = DB::table('plan_line_audios')->where('line_ref', 'not like', 'x%')->orWhere('line_ref', 'like', 'x%b')->pluck('voice_key')->unique()->values()->all();

    expect($output)->toContain('dropped: 32')
        ->and($output)->toContain('after: partner lines 0, learner lines 0, phrases 0, fillers 0, words 0')
        ->and($vendor->calls)->toBe(32)
        ->and(DB::table('plan_line_audios')->count())->toBe(40)
        ->and(DB::table('plan_line_audios')->where('line_ref', 'x1')->first())->toEqual($partnerLine)
        ->and(Storage::disk('local')->exists($learnerPath))->toBeFalse()
        ->and($learnerKeys)->toHaveCount(1)
        ->and($learnerKeys[0])->toContain('another-learner-man');

    config(['generation.speech.enabled' => false]);
    Artisan::call('plan:speak-backfill', ['--drop-unread' => true]);
    expect(Artisan::output())->toContain('dropped: 0')
        ->and(DB::table('plan_line_audios')->count())->toBe(40);
});

// Canon (TTS-2, the architect): «мёртвые файлы замедленного голоса — удалить, строки под старым ключом снести, без
// переозвучки». Catches a drop that buys what it dropped, one that leaves the files on the disk, and one that takes the
// lines of a voice the pack still has.
it('deletes with --drop-only the lines of a voice their speaker no longer has, rows and files, and buys nothing', function () {
    $vendor = windowVoice();
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $partnerLines = DB::table('plan_line_audios')->where('line_ref', 'like', 'x%')->where('line_ref', 'not like', 'x%b')->orderBy('line_ref')->get()->all();
    $learnerPath = (string) DB::table('plan_line_audios')->where('line_ref', 'x1b')->value('path');
    config(['generation.speech.voices.en.learner.male.voice' => 'another-learner-man']);
    app()->forgetInstance(VoiceCatalog::class);
    $vendor->calls = 0;

    Artisan::call('plan:speak-backfill', ['--plan' => [$id], '--drop-only' => true]);

    expect(Artisan::output())->toContain('dropped: 32 in 1 scenes; nothing bought')
        ->and($vendor->calls)->toBe(0)
        ->and(DB::table('plan_line_audios')->orderBy('line_ref')->get()->all())->toEqual($partnerLines)
        ->and(Storage::disk('local')->exists($learnerPath))->toBeFalse();
    Artisan::call('plan:speak-backfill', ['--count' => true]);
    expect(Artisan::output())->toContain('partner lines 0, learner lines 8, phrases 6, fillers 10, words 8');
});

// Canon (TTS-2, the architect): «кап на наряд SPEECH_JOB_CREDITS_CAP, по умолчанию 3 000; превышение — стоп и письмо в
// лог». One run of purchases — a voice job, a whole plan:speak-backfill — never buys past the cap: before each scene what
// the run has bought and what the scene would cost are added up. Catches a cap checked scene by scene alone (a backfill of
// many scenes walks past it), a cap checked after the purchase, and a stop without the letter.
it('never lets one run buy past the credits cap: the scene that would go over is not bought, and the letter goes to the log', function () {
    [, $token] = planLearner();
    $first = planCreate($this, $token, ['days_total' => 2])['id'];
    [, $otherToken] = planLearner();
    app('auth')->forgetGuards();
    $second = planCreate($this, $otherToken, ['days_total' => 2])['id'];
    $sceneOf = static fn (string $id): string => (string) DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->value('id');
    $vendor = windowVoice();
    $costOf = static function (string $sceneId): int {
        $debt = app(SceneVoiceQueue::class)->owed(PlanSceneId::fromString($sceneId));

        return $debt === null ? 0 : app(LineSpeaker::class)->creditsFor($debt->lang, $debt->lines);
    };
    $cost = $costOf($sceneOf($first));
    expect($cost)->toBeGreaterThan(0)->and($costOf($sceneOf($second)))->toBe($cost);
    Log::spy();

    // Room for a scene and a half: one scene is bought whole, the next would go over and is not touched.
    $cap = $cost + intdiv($cost, 2);
    config(['generation.speech.job_credits_cap' => $cap]);
    Artisan::call('plan:speak-backfill');

    expect(Artisan::output())->toContain('Stopped by the credits cap')
        ->and($vendor->calls)->toBe(40)
        ->and((int) DB::table('plan_line_audios')->sum('credits'))->toBe($cost)
        ->and(DB::table('plan_line_audios')->distinct()->count('scene_id'))->toBe(1);
    Log::shouldHaveReceived('error')->withArgs(static fn (string $message, array $context): bool => str_contains($message, 'credits cap') && $context['cap'] === $cap);

    // A voice job is a run of its own: a scene dearer than the cap is not bought, and the job neither fails nor waits.
    config(['generation.speech.job_credits_cap' => $cost - 1]);
    $owed = DB::table('plan_line_audios')->where('scene_id', $sceneOf($first))->exists() ? $sceneOf($second) : $sceneOf($first);
    $queueJob = Mockery::mock(Job::class);
    $queueJob->shouldNotReceive('fail');
    $queueJob->shouldNotReceive('release');
    $job = new VoiceSceneJob($owed);
    $job->setJob($queueJob);
    $job->handle(app(VoiceSceneHandler::class));

    expect($vendor->calls)->toBe(40);
    Log::shouldHaveReceived('error')->withArgs(static fn (string $message, array $context): bool => str_contains($message, 'credits cap') && ($context['scene_id'] ?? null) === $owed && $context['scene_credits'] === $cost);
});

// Canon (TTS-2, the architect): «wordtrainer_e2e_test из автоозвучки и бэкфилла исключить навсегда (озвучивать только по
// явному --plan)». Catches a new day voiced on its own on such a database, a backfill of every scene there, and a --plan
// refused along with them.
it('voices nothing on its own on a database kept for named plans: no voice for a new day, no backfill without --plan', function () {
    config(['generation.speech.named_plans_only_databases' => [(string) config('database.connections.'.config('database.default').'.database')]]);
    $vendor = windowVoice();
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    expect($vendor->calls)->toBe(0);

    $refused = Artisan::call('plan:speak-backfill');
    expect($refused)->toBe(1)
        ->and(Artisan::output())->toContain('voiced only by --plan');
    $refusedDrop = Artisan::call('plan:speak-backfill', ['--drop-unread' => true]);
    expect($refusedDrop)->toBe(1)->and($vendor->calls)->toBe(0);

    Artisan::call('plan:speak-backfill', ['--count' => true]);
    expect(Artisan::output())->toContain('partner lines 8, learner lines 8, phrases 6, fillers 10, words 8');

    Artisan::call('plan:speak-backfill', ['--plan' => [$id]]);
    expect(Artisan::output())->toContain('bought: 40 lines')
        ->and($vendor->calls)->toBe(40);
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
        ->and($finder->asked)->not->toContain('fever')
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

    // Forget the photo of a word without an image prompt, and find nothing for it.
    DB::table('plan_terms')->where('text_target', 'sharp')->update(['image_url' => null, 'image_tone' => null]);
    $finder = windowFinder(["sharp, {$theme}", $theme]);
    app()->instance(PlanImageFinder::class, $finder);

    app(IllustrateSceneHandler::class)(new IllustrateScene(PlanSceneId::fromString($scene['id'])));
    app(IllustrateSceneHandler::class)(new IllustrateScene(PlanSceneId::fromString($scene['id'])));

    $row = DB::table('plan_terms')->where('text_target', 'sharp')->first();
    $word = collect(windowOf($this, $token, $plan['id'], 1)['program']['words']['items'])->firstWhere('term', 'sharp');
    expect($row->image_url)->toBeNull()
        ->and($row->image_tone)->toBe($scene['image']['tone'])
        ->and(array_count_values($finder->asked)["sharp, {$theme}"] ?? 0)->toBe(1)
        ->and(DB::table('plan_check_counters')->where('check_name', 'image_missing')->where('prompt_version', 'lesson_day.v4.5')->value('hits'))->toBe(1)
        ->and($word['image'])->toBeNull()
        ->and($word['image_tone'])->toBe($scene['image']['tone']);
});

// Canon (DAY-UI-3): «бэкфилл существующих планов командой; «было пусто / стало»». Catches old days left with a
// supermarket for «marketing», and with the same picture on two words or on a word and its plate.
it('backfills what the plans still lack and says «было пусто / стало»; --requery re-asks the bare word’s photos and a day’s repeats', function () {
    [, $token] = planLearner();
    planCreate($this, $token, ['days_total' => 2]);
    DB::table('plan_terms')->whereIn('text_target', ['sharp', 'fever'])->update(['image_url' => null, 'image_tone' => '#978E82']);
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
    expect(Artisan::output())->toContain('photographed by the bare word: 1, repeating a picture of their day: 2')
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
    // Two words without an image prompt ask the same «word, theme» question.
    app()->instance(PlanModelPort::class, new FakePlanModel(lesson: static function (object $request): array {
        $p = FakePlanModel::lessonPayload($request);
        $p['vocabulary'][2]['image_prompt'] = null;

        return $p;
    }));
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $sceneId = (string) DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->value('id');

    $urls = DB::table('plan_terms')->where('scene_id', $sceneId)->whereIn('kind', ['word', 'chunk'])->pluck('image_url')->all();

    $plate = DB::table('plan_scenes')->where('id', $sceneId)->value('image_url');

    // The plate took the theme's first page; the two words asking the same question get the next two.
    expect($urls)->toHaveCount(8)
        ->and(array_unique([...$urls, $plate]))->toHaveCount(9)
        ->and($plate)->toBe('https://images.pexels.test/theme-p1.jpg')
        ->and($urls)->toContain('https://images.pexels.test/theme-p2.jpg')
        ->and($urls)->toContain('https://images.pexels.test/theme-p3.jpg');
});

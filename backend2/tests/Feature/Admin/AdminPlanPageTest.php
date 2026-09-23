<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * THE LEARNER'S PLAN PAGE (наряд ADM-1) on the owner's gym plan as the server held it (GYM-DUMP-2: day 1 «Ресепшен зала»
 * closed, day 2 «С тренером» walked, day 3 the rehearsal). Canon: the page is found by the plan's code or id; every
 * section answers for the whole plan or one day; every figure is a stored one; the journal pages without losing or
 * repeating a row; the page writes nothing and plays only the plan's own sounds.
 */
uses(RefreshDatabase::class);

const GYM_CODE = '2DX8QC';

beforeEach(function () {
    config(['generation.speech.enabled' => true, 'generation.speech.driver' => 'fake']);
    [$this->learner] = planLearner();
    $this->planId = planGymLoad((string) $this->learner->id);
    [, $token] = adminActor();
    $this->withHeader('Authorization', "Bearer {$token}");
});

/** @return array<string, mixed> */
function planPage(object $ctx, string $path): array
{
    return $ctx->getJson('/admin/api/plans/'.GYM_CODE.$path)->assertOk()->json();
}

it('lists the learner\'s plans with the code, the progress and the stored money', function () {
    $rows = $this->getJson("/admin/api/users/{$this->learner->id}/plans")->assertOk()->json('data');

    $stored = (float) DB::table('plans')->where('id', $this->planId)->value('cost_usd_plan')
        + (float) DB::table('plan_scenes')->where('plan_id', $this->planId)->sum('cost_usd_lesson')
        + (float) DB::table('plan_line_audios')->whereIn('scene_id', DB::table('plan_scenes')->where('plan_id', $this->planId)->pluck('id'))->sum('cost_usd')
        + (float) DB::table('day_cards')->whereIn('day_id', DB::table('plan_days')->where('plan_id', $this->planId)->pluck('id'))
            ->whereRaw("response->'judge'->>'by' = 'model'")->sum(DB::raw("(response->'judge'->>'cost_usd')::numeric"));

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['code'])->toBe(GYM_CODE)
        ->and($rows[0]['id'])->toBe($this->planId)
        ->and($rows[0]['progress'])->toBe(['current_day' => 2, 'days_total' => 3, 'days_closed' => 1, 'days_opened' => 2])
        ->and($rows[0]['cost_usd'])->toEqualWithDelta($stored, 0.000001);
});

it('opens the page by the code and by the full id, and names the learner', function () {
    $byCode = planPage($this, '');
    $byId = $this->getJson("/admin/api/plans/{$this->planId}")->assertOk()->json();

    expect($byCode['id'])->toBe($this->planId)
        ->and($byId['code'])->toBe(GYM_CODE)
        ->and($byCode['user']['id'])->toBe((string) $this->learner->id)
        ->and($byCode['versions']['lesson'])->toBe(DB::table('plan_scenes')->where('plan_id', $this->planId)->distinct()->pluck('prompt_version_lesson')->all())
        ->and($byCode['versions']['repair'])->toBeNull()
        ->and($byCode['build_versions']['plan'])->toBe(DB::table('plans')->where('id', $this->planId)->value('build_version'));
});

it('answers 404 for an unknown code, 409 for a code two plans share, and leaves «checks» to the check counters', function () {
    $this->getJson('/admin/api/plans/ZZZZZZ')->assertNotFound();
    $this->getJson('/admin/api/plans/'.strtolower(GYM_CODE))->assertNotFound();
    $this->getJson('/admin/api/plans/checks')->assertOk()->assertJsonStructure(['data']);

    $twin = DB::table('plans')->where('id', $this->planId)->first();
    DB::table('plans')->insert(['id' => '01M4'.GYM_CODE.'0000000000000000', 'status' => 'deleted'] + (array) $twin);

    $this->getJson('/admin/api/plans/'.GYM_CODE)->assertStatus(409);
    $this->getJson("/admin/api/plans/{$this->planId}")->assertOk();
});

it('narrows every section to one day, and refuses a day that cannot be', function () {
    expect(array_column(planPage($this, '/days')['data'], 'number'))->toBe([1, 2, 3])
        ->and(array_column(planPage($this, '/days?day=2')['data'], 'number'))->toBe([2])
        ->and(array_column(planPage($this, '/pipeline?day=2')['days'], 'number'))->toBe([2])
        ->and(array_column(planPage($this, '/lesson?day=1')['days'], 'number'))->toBe([1])
        ->and(array_column(planPage($this, '/passage?day=1')['days'], 'number'))->toBe([1])
        ->and(array_column(planPage($this, '/money?day=3')['days'], 'number'))->toBe([3]);

    $this->getJson('/admin/api/plans/'.GYM_CODE.'/days?day=11')->assertStatus(422);
    $this->getJson('/admin/api/plans/'.GYM_CODE.'/days?day=x')->assertStatus(422);
});

it('shows the days as the client is told them, with their stored money', function () {
    $days = planPage($this, '/days')['data'];
    $scene1 = DB::table('plan_scenes')->where('plan_id', $this->planId)->orderBy('order')->first();

    expect($days[0])->toMatchArray(['number' => 1, 'type' => 'scene', 'stored_status' => 'closed', 'lesson_status' => 'ready'])
        ->and($days[2])->toMatchArray(['number' => 3, 'type' => 'rehearsal', 'scene_id' => null])
        ->and($days[0]['cost_usd']['generation'])->toEqualWithDelta((float) $scene1->cost_usd_lesson, 0.000001)
        ->and($days[0]['cost_usd']['voice'])->toEqualWithDelta((float) DB::table('plan_line_audios')->where('scene_id', $scene1->id)->sum('cost_usd'), 0.000001);
});

it('lays the conveyor out stage by stage, the validator\'s findings marked fatal by the gate\'s own list', function () {
    $day = planPage($this, '/pipeline?day=1')['days'][0];

    expect(array_column($day['stages'], 'key'))->toBe(['lesson', 'validator', 'repair', 'seam_judge', 'images', 'voice', 'served']);
    $validator = $day['stages'][1]['facts'];
    foreach ($validator['findings'] as $finding) {
        expect($finding['fatal'])->toBe(App\Modules\Plan\Domain\Check\LessonGate::isFatal($finding['code']));
    }
    expect($day['stages'][5]['facts']['lines'])->toBe(DB::table('plan_line_audios')->where('scene_id', $day['scene_id'])->count())
        ->and($day['stages'][2]['not_stored'])->not->toBeEmpty();
});

it('gives the lesson as served and every line with the voice its cast gives it', function () {
    $day = planPage($this, '/lesson?day=1')['days'][0];
    $lines = collect($day['voice']['lines'])->keyBy('ref');

    expect($day['lesson'])->toHaveKeys(['dialogue', 'phrases', 'vocabulary'])
        ->and($lines['x1']['speaker'])->toBe('partner')
        ->and($lines['x1b']['speaker'])->toBe('learner')
        ->and($lines['x1b']['voice']['identity'])->toBe('learner:male')
        ->and($lines['x1b']['rule'])->toContain('по умолчанию');

    $voiced = $lines->filter(fn (array $l): bool => $l['status'] === 'voiced');
    expect($voiced)->not->toBeEmpty();
    foreach ($voiced as $line) {
        expect(DB::table('plan_line_audios')->where('id', $line['audio']['id'])->value('line_ref'))->toBe($line['ref']);
    }
});

it('walks a day card by card in the order it was answered', function () {
    $day = planPage($this, '/passage?day=1')['days'][0];
    $answered = array_values(array_filter(array_column($day['cards'], 'answered_at')));
    $sorted = $answered;
    sort($sorted);

    expect($day['cards'])->toHaveCount(DB::table('day_cards')->where('day_id', DB::table('plan_days')->where('plan_id', $this->planId)->where('number', 1)->value('id'))->count())
        ->and($answered)->toBe($sorted)
        ->and($day['summary'])->toHaveKeys(['cards_total', 'cards_done', 'minutes_spent', 'results', 'stages_passed']);
});

it('pages the journal without losing or repeating a row, and keeps a day to its own calls', function () {
    $ids = [];
    $cursor = null;
    do {
        $page = $this->getJson('/admin/api/plans/'.GYM_CODE.'/calls?source=voice&limit=7'.($cursor === null ? '' : "&cursor={$cursor}"))->assertOk()->json();
        array_push($ids, ...array_column($page['data'], 'id'));
        $cursor = $page['meta']['next_cursor'];
    } while ($cursor !== null);

    $audios = DB::table('plan_line_audios')->whereIn('scene_id', DB::table('plan_scenes')->where('plan_id', $this->planId)->pluck('id'))->count();
    expect($ids)->toHaveCount($audios)->and(array_unique($ids))->toHaveCount($audios)
        ->and($page['meta']['total'])->toBe($audios);

    $log = static fn (string $path): array => ['id' => Ulid::generate(), 'direction' => 'inbound', 'method' => 'GET', 'path' => $path, 'status' => 200, 'occurred_at' => now(), 'user_id' => null];
    DB::table('api_request_logs')->insert([
        $log("api/v1/plans/{$this->planId}/days/1"),
        $log("api/v1/plans/{$this->planId}/days/1/cards"),
        $log("api/v1/plans/{$this->planId}/days/2"),
        $log('api/v1/plans/'.Ulid::generate().'/days/1'),
    ]);

    $client = planPage($this, '/calls?source=client&day=1');
    expect(array_column($client['data'], 'path'))->toEqualCanonicalizing(["api/v1/plans/{$this->planId}/days/1", "api/v1/plans/{$this->planId}/days/1/cards"])
        ->and(planPage($this, '/calls?source=client')['meta']['total'])->toBe(3);

    $this->getJson('/admin/api/plans/'.GYM_CODE.'/calls?source=nope')->assertStatus(422);
    $this->getJson('/admin/api/plans/'.GYM_CODE.'/calls?limit=500')->assertStatus(422);
});

it('reads a model call in the window of a scene\'s build as that day\'s, and flags it when lost', function () {
    $scene = DB::table('plan_scenes')->where('plan_id', $this->planId)->orderBy('order')->first();
    DB::table('plan_scenes')->where('id', $scene->id)->update(['generated_at' => (new DateTimeImmutable((string) $scene->build_started_at))->modify('+2 minutes')]);
    DB::table('model_calls')->insert([
        'id' => Ulid::generate(), 'status' => 'lost', 'provider' => 'openai', 'model' => 'gpt-5.4', 'purpose' => 'lesson',
        'estimated_tokens_in' => 9000, 'timeout_seconds' => 180, 'error' => 'cURL error 28',
        'started_at' => (new DateTimeImmutable((string) $scene->build_started_at))->modify('+30 seconds'),
    ]);

    $issues = collect(planPage($this, '/issues?day=1')['data']);
    $lesson = collect(planPage($this, '/pipeline?day=1')['days'][0]['stages'])->firstWhere('key', 'lesson');

    expect($issues->where('check', 'lost_model_call')->pluck('day')->all())->toBe([1])
        ->and($lesson['calls'])->toHaveCount(1)
        ->and($lesson['calls'][0]['status'])->toBe('lost')
        ->and($lesson['calls'][0]['certain'])->toBeTrue();
});

it('flags a closed day without its day_passed line, and stops once the line is there', function () {
    $checks = static fn (array $page): array => array_column($page['data'], 'check');

    expect($checks(planPage($this, '/issues?day=1')))->toContain('passed_without_summary');

    DB::table('plan_events')->insert([
        'id' => Ulid::generate(), 'user_id' => (string) $this->learner->id, 'plan_id' => $this->planId,
        'day_id' => DB::table('plan_days')->where('plan_id', $this->planId)->where('number', 1)->value('id'),
        'day_number' => 1, 'kind' => 'day_passed', 'payload' => '{}', 'occurred_at' => now(), 'created_at' => now(),
    ]);

    expect($checks(planPage($this, '/issues?day=1')))->not->toContain('passed_without_summary');
});

it('plays only the plan\'s own sounds', function () {
    $disk = (string) config('plan.audio_disk');
    Storage::fake($disk);
    $audio = DB::table('plan_line_audios')->whereIn('scene_id', DB::table('plan_scenes')->where('plan_id', $this->planId)->pluck('id'))->first();
    Storage::disk($disk)->put($audio->path, 'ID3-bytes');

    $this->get('/admin/api/plans/'.GYM_CODE."/audio/{$audio->id}")->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
    $this->get('/admin/api/plans/'.GYM_CODE.'/audio/'.Ulid::generate())->assertNotFound();
});

it('writes nothing — every section read leaves every table as it was', function () {
    $tables = ['plans', 'plan_scenes', 'plan_days', 'day_cards', 'plan_terms', 'plan_line_audios', 'plan_events', 'conversations', 'conversation_turns', 'plan_stage_passages', 'model_calls'];
    // Every row of every table, whole — an update is caught as surely as an insert.
    $snapshot = static fn (): array => array_map(static fn (string $t): ?string => DB::selectOne("SELECT md5(string_agg(x::text, '|' ORDER BY x.id)) AS h FROM {$t} x")->h, $tables);
    $before = $snapshot();

    $this->getJson("/admin/api/users/{$this->learner->id}/plans")->assertOk();
    foreach (['', '/issues', '/days', '/pipeline', '/lesson', '/passage', '/conversations', '/money', '/calls'] as $section) {
        planPage($this, $section);
    }

    expect($snapshot())->toBe($before);
});

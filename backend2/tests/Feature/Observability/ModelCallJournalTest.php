<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Domain\ValueObject\PromptShape;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiCompatibleContentModel;
use App\Modules\Generation\Infrastructure\Adapter\VendorCall;
use App\Modules\Observability\Application\Port\ModelCallJournal;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Plan\Application\Dto\DialogueRequest;
use App\Modules\Plan\Application\Dto\LessonCardRepairRequest;
use App\Modules\Plan\Application\Dto\NativeSeamJudgeRequest;
use App\Modules\Plan\Application\Dto\PlanLineRepairRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Lesson\EarlierDays;
use App\Modules\Plan\Domain\Lesson\LessonParser;
use App\Modules\Plan\Domain\ValueObject\PlanLevel;
use App\Modules\Plan\Infrastructure\Job\BuildLessonJob;
use App\Modules\Plan\Infrastructure\Job\BuildPlanJob;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\Service\ModelCost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

// This file is ABOUT the real adapter and its journal — with `Http::fake()` underneath every case, nothing reaches the wire.
beforeEach(fn () => allowLiveAdapters());

/**
 * THE JOURNAL OF MODEL CALLS AND THE CALL POLICY (наряд GEN-3, §6 and its addendum): a row before the call, finished after
 * it — usage, cached tokens, price; a call with no answer stays `lost`; the plan's calls wait 180 s for the answer and ten
 * for the connection; nothing that got no answer is sent again; a job outlives every call it makes.
 */

function mcjAdapter(int $timeout = 180, int $retries = 4): OpenAiCompatibleContentModel
{
    return new OpenAiCompatibleContentModel(app(OutboundCallContext::class), app(VendorCall::class), ProviderId::OpenAi, 'key', 'gpt-5.4', 'https://api.openai.com/v1', new ModelCost, $timeout, 'plan', $retries);
}

function mcjPrompt(): RenderedPrompt
{
    return new RenderedPrompt(str_repeat('RULES ', 400), 'lesson_day.v4.10', PromptShape::Full, 'x');
}

/** @return array<string, mixed> */
function mcjAnswer(int $in = 7400, int $cached = 6912, int $out = 4100): array
{
    return [
        'model' => 'gpt-5.4-2026-03-05',
        'choices' => [['message' => ['content' => '{"ok":true}']]],
        'usage' => ['prompt_tokens' => $in, 'completion_tokens' => $out, 'prompt_tokens_details' => ['cached_tokens' => $cached]],
    ];
}

/** @return array<string, mixed> the one journal row */
function mcjRow(): array
{
    $rows = DB::table('model_calls')->get()->map(static fn (object $r): array => (array) $r)->all();
    expect($rows)->toHaveCount(1);

    return $rows[0];
}

// Addendum C: «запись в журнал расходов создаётся ДО вызова (статус started, модель, назначение, оценка входных токенов) и
// дополняется после ответа (usage, стоимость, cached_tokens)». Catches a row written only after the answer (a call whose
// process dies leaves no trace of its money), cached tokens not recorded, and a price that ignores the cache.
it('writes a model call down before it is made and finishes it with the usage, the cached tokens and the price', function () {
    $seenBefore = null;
    Http::fake(function (Request $request) use (&$seenBefore) {
        $seenBefore = DB::table('model_calls')->first();

        return Http::response(mcjAnswer(), 200);
    });

    $answer = mcjAdapter()->complete(mcjPrompt(), 'TOPIC: Просмотр', ['type' => 'object']);
    $row = mcjRow();

    expect($seenBefore?->status)->toBe('started')
        ->and($seenBefore?->model)->toBe('gpt-5.4')
        ->and($seenBefore?->purpose)->toBe('plan')
        ->and($seenBefore?->estimated_tokens_in)->toBeGreaterThan(500)
        ->and($row['status'])->toBe('completed')
        ->and($row['answered_model'])->toBe('gpt-5.4-2026-03-05')
        ->and([$row['tokens_in'], $row['cached_tokens'], $row['tokens_out']])->toBe([7400, 6912, 4100])
        // (7400 − 6912) × $2.5/M + 6912 × $0.25/M + 4100 × $15/M
        ->and($row['cost_usd'])->toBe('0.064448')
        ->and($row['http_status'])->toBe(200)
        ->and($row['finished_at'])->not->toBeNull()
        ->and($answer->cachedTokensIn)->toBe(6912)
        ->and($answer->costUsd)->toBe('0.064448')
        // The answer names its row (наряд FIX-4 §6): a caller that keeps something of the call beside it — the talk's
        // journal of refusals — points at the call, never copies it.
        ->and($answer->callId)->toBe($row['id']);
});

// Addendum C: «найденный дефект: адаптер OpenAI обрывает ожидание на 60 с (cURL error 28, 0 bytes received), модель досчитывает,
// OpenAI списывает; вызов без ответа остаётся со статусом lost; автоповтора нет». Catches «вызов оборван на 60 с, модель
// досчитала, деньги списаны, ответа нет» — a call our client dropped that vanishes from the journal, and one sent again
// (bought twice).
it('keeps a call our client gave up on as lost, and never sends it again', function () {
    Http::fake(fn () => Http::failedConnection('cURL error 28: Operation timed out after 60000 milliseconds with 0 bytes received'));

    expect(fn () => mcjAdapter(retries: 4)->complete(mcjPrompt(), 'TOPIC: x', ['type' => 'object']))->toThrow(ConnectionException::class);

    $row = mcjRow();
    Http::assertSentCount(1);
    expect($row['status'])->toBe('lost')
        ->and($row['error'])->toContain('cURL error 28')
        ->and($row['tokens_in'])->toBeNull();
});

// A vendor that answered with a status that clears by itself is worth another attempt; the journal keeps the call, not the
// attempts. Catches a rate window turned into a failed lesson, and an answered error left `started` forever.
it('tries again after a rate window and writes one call; a refused call is failed, not lost', function () {
    Sleep::fake();
    Http::fake(['*' => Http::sequence()->push(['error' => 'rate'], 429)->push(mcjAnswer(), 200)->push(['error' => 'no credits'], 403)]);

    mcjAdapter()->complete(mcjPrompt(), 'TOPIC: x', ['type' => 'object']);
    $completed = mcjRow();
    DB::table('model_calls')->delete();

    expect(fn () => mcjAdapter()->complete(mcjPrompt(), 'TOPIC: x', ['type' => 'object']))->toThrow(RuntimeException::class);

    Http::assertSentCount(3);
    expect($completed['status'])->toBe('completed')
        ->and(mcjRow()['status'])->toBe('failed')
        ->and(mcjRow()['http_status'])->toBe(403);
});

// Addendum C: «вызов без ответа остаётся со статусом lost» — also a process killed while it waited (a worker at its job's
// timeout runs no `finally`). Catches a started row that stays `started` for ever, and a sweep that marks lost a call still
// within its wait.
it('marks lost the calls still started past their caller\'s wait, and leaves the rest', function () {
    $insert = static fn (string $id, string $status, string $startedAt, int $timeout): bool => DB::table('model_calls')->insert([
        'id' => $id, 'status' => $status, 'provider' => 'openai', 'model' => 'gpt-5.4', 'purpose' => 'plan',
        'estimated_tokens_in' => 7000, 'timeout_seconds' => $timeout, 'started_at' => $startedAt,
    ]);
    $insert('01M2GEN3JOURNAL00000000001', 'started', now()->subSeconds(180 + 61)->toIso8601String(), 180);
    $insert('01M2GEN3JOURNAL00000000002', 'started', now()->subSeconds(180 + 30)->toIso8601String(), 180);
    $insert('01M2GEN3JOURNAL00000000003', 'completed', now()->subHour()->toIso8601String(), 180);

    $marked = app(ModelCallJournal::class)->sweepLost(new DateTimeImmutable, 60);

    expect($marked)->toBe(1)
        ->and(DB::table('model_calls')->orderBy('id')->pluck('status')->all())->toBe(['lost', 'started', 'completed']);
});

// Addendum C: «таймаут ожидания ответа в адаптере — 180 с для всех вызовов (план, урок, P2R, судья), connect timeout 10 с»;
// наряд GEN-4: the day's two stages and their repairs the same, a line repair of the plan — a line in, a line out — 30 s.
// Catches «вызов оборван на 60 с, модель досчитала, деньги списаны, ответа нет»: a plan call built with a wait shorter than
// the slowest lesson (51 s) three times over, or a connection wait left to the library's default.
it('waits 180 seconds for the answer of every plan call, 30 for a line repair, and 10 seconds for the connection', function () {
    config(['plan.model.driver' => 'openai', 'services.openai.api_key' => 'test-key']);
    app()->forgetInstance(PlanModelPort::class);
    $options = [];
    Http::fake(function (Request $request, array $sent) use (&$options) {
        $options[] = [$sent['timeout'] ?? null, $sent['connect_timeout'] ?? null];

        return Http::response(mcjAnswer(), 200);
    });
    $model = app(PlanModelPort::class);
    $lesson = FakePlanModel::lessonRequest('x');
    $skeleton = (new LessonParser)->skeleton(FakePlanModel::skeletonPayload($lesson));

    $model->buildPlan(new PlanRequest('врач', 'English', 'Russian', PlanLevel::Beginner, 2));
    $model->repairPlanLine(new PlanLineRepairRequest('teaches_native', 'Russian', 34, 'рассказать про опыт работы и понять обязанности'));
    $model->buildSkeleton($lesson);
    $model->buildDialogue(new DialogueRequest($lesson, $skeleton));
    $model->repairLessonCard(new LessonCardRepairRequest('p1', 'frame', [], [], $skeleton->toArray(), null, null, new EarlierDays, 'English', 'Russian', PlanLevel::Beginner, null));
    $model->judgeNativeSeams(new NativeSeamJudgeRequest('Russian', [['id' => 'p1.f1', 'pattern' => 'x', 'value' => 'y', 'sentence' => 'z']]));

    expect($options)->toBe([[180, 10], [30, 10], [180, 10], [180, 10], [180, 10], [180, 10]]);
});

// Canon (наряд BACK-TAILS-1 §3.3; наряд GEN-4 — a name per purpose): «назначение вызова различать, а не «plan» на всё».
// The MONEY is one budget and stays `plan` in the request log — the cost screen must not split in two — but the journal
// names each call for what it is: plan, plan_line_repair, skeleton, dialogue, repair, seam_judge, slot_judge. Catches a
// journal that says «plan» on every row, where a lost call cannot be told from the stage it was, the two judges under one
// name, and the finer name leaking into `api_request_logs`, whose CHECK constraint would drop the row and lose the spend
// (the same hole the `term_reading` whitelist migration was written to close).
it('names each plan call in the journal by its purpose while the spend stays one purpose', function () {
    config(['plan.model.driver' => 'openai', 'services.openai.api_key' => 'test-key']);
    app()->forgetInstance(PlanModelPort::class);
    Http::fake(fn () => Http::response(mcjAnswer(), 200));
    $model = app(PlanModelPort::class);
    $lesson = FakePlanModel::lessonRequest('x');
    $skeleton = (new LessonParser)->skeleton(FakePlanModel::skeletonPayload($lesson));

    $model->buildPlan(new PlanRequest('врач', 'English', 'Russian', PlanLevel::Beginner, 2));
    $model->repairPlanLine(new PlanLineRepairRequest('teaches_native', 'Russian', 34, 'рассказать про опыт работы и понять обязанности'));
    $model->buildSkeleton($lesson);
    $model->buildDialogue(new DialogueRequest($lesson, $skeleton));
    $model->repairLessonCard(new LessonCardRepairRequest('p1', 'frame', [], [], $skeleton->toArray(), null, null, new EarlierDays, 'English', 'Russian', PlanLevel::Beginner, null));
    $model->judgeNativeSeams(new NativeSeamJudgeRequest('Russian', [['id' => 'p1.f1', 'pattern' => 'x', 'value' => 'y', 'sentence' => 'z']]));
    $model->judgeSlot(new SlotJudgeRequest(SlotJudgeRequest::MODE_ANSWER, 'English', 'Russian', 'beginner', 'Where?', 'Где?', 'It hurts ___.', 'Болит ___.', 'где', 'neck', 'it hurts here'));

    expect(DB::table('model_calls')->orderBy('started_at')->pluck('purpose')->all())
        ->toBe(['plan', 'plan_line_repair', 'skeleton', 'dialogue', 'repair', 'seam_judge', 'slot_judge'])
        // The request log knows one purpose, the one its CHECK constraint allows — and it recorded ALL SEVEN calls.
        // A finer name reaching this column does not raise: the writer swallows the CHECK violation and the spend of
        // that call is simply not recorded, which is the hole the `term_reading` whitelist migration was written for.
        ->and(DB::table('api_request_logs')->where('direction', 'outbound')->pluck('purpose')->all())
        ->toBe(['plan', 'plan', 'plan', 'plan', 'plan', 'plan', 'plan']);
});

// Наряд GEN-4, 4: «модель и reasoning_effort на каждое назначение». Catches an effort named in the config that never reaches
// the vendor, an effort sent with every call once one purpose names it, and a model of one purpose leaking into another's.
it('asks every purpose with its own model and reasoning effort, and sends no effort where none is named', function () {
    config([
        'plan.model.driver' => 'openai', 'services.openai.api_key' => 'test-key',
        'plan.model.purposes.skeleton' => ['model' => 'gpt-5.6-luna', 'reasoning_effort' => 'high'],
        'plan.model.purposes.dialogue' => ['model' => 'gpt-5.4', 'reasoning_effort' => ''],
    ]);
    app()->forgetInstance(PlanModelPort::class);
    $bodies = [];
    Http::fake(function (Request $request) use (&$bodies) {
        $bodies[] = $request->data();

        return Http::response(mcjAnswer(), 200);
    });
    $model = app(PlanModelPort::class);
    $lesson = FakePlanModel::lessonRequest('x');
    $skeleton = (new LessonParser)->skeleton(FakePlanModel::skeletonPayload($lesson));

    $model->buildSkeleton($lesson);
    $model->buildDialogue(new DialogueRequest($lesson, $skeleton));

    expect([$bodies[0]['model'], $bodies[0]['reasoning_effort'] ?? null])->toBe(['gpt-5.6-luna', 'high'])
        ->and([$bodies[1]['model'], array_key_exists('reasoning_effort', $bodies[1])])->toBe(['gpt-5.4', false]);
});

// Addendum C: «таймаут job'а выше таймаута клиента с запасом; автоповтора job'а после таймаута нет». Catches a job killed
// between two paid calls of one build (наряд GEN-4: each stage and its one repeat, two repairs a stage, the seam judge and
// its second read), a plan job killed amid its line repairs, a job handed to a second worker while the first still waits
// for its answer (the queue's retry_after under the job's timeout), a stale window a learner's retry can open under a live
// job, and a job the queue runs again.
it('lets a lesson job outlive every call it makes, and never runs it twice', function () {
    $lesson = new BuildLessonJob('01M2GEN3SCENE0000000000001');
    $plan = new BuildPlanJob('01M2GEN3PLAN00000000000001');

    expect(BuildLessonJob::timeoutSeconds(180))->toBe((2 * (2 + 2) + 2) * 180 + 60)
        ->and($lesson->timeout)->toBe(1860)
        ->and($plan->timeout)->toBe(2 * 180 + 12 * 30 + 60)
        ->and([$lesson->tries, $plan->tries])->toBe([1, 1])
        ->and((int) config('queue.connections.redis.retry_after'))->toBeGreaterThan($lesson->timeout)
        ->and((int) config('plan.build_stale_seconds'))->toBeGreaterThan($lesson->timeout)
        ->and((int) config('plan.model.lesson_timeout'))->toBeGreaterThanOrEqual(3 * 51);
});

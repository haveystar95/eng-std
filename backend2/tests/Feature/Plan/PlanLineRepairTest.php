<?php

declare(strict_types=1);

use App\Modules\Plan\Application\Dto\PlanLineRepairRequest;
use App\Modules\Plan\Application\Dto\PlanRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * A SCREEN LINE OVER ITS LIMIT (наряд GEN-4, §2: «лимиты знаков — не перегон плана, а починка ОДНОЙ строки дешёвой моделью …
 * проверка лимита после него; в журнал как отдельное назначение»): the plan is built once, each line over its limit goes to
 * the line repair alone, and what comes back is taken only within the limit.
 */

/** A plan of the fake whose first scene's name and second goal are too long for the phone. */
function plrLongLines(PlanRequest $request): array
{
    $p = FakePlanModel::planPayload($request);
    $p['scenes'][0]['title_native'] = 'Запись к врачу на завтрашнее утро';
    $p['scenes'][0]['goals_native'][1] = 'ответить на все вопросы регистратора';

    return $p;
}

/** @return array{0: string, 1: object} the plan's id and its first scene's row */
function plrBuild(object $ctx, FakePlanModel $fake): array
{
    app()->instance(PlanModelPort::class, $fake);
    [, $token] = planLearner();
    $id = planCreate($ctx, $token, ['days_total' => 3])['id'];

    return [$id, DB::table('plan_scenes')->where('plan_id', $id)->orderBy('order')->first() ?? throw new LogicException('no scene')];
}

/** @return list<string> the line repairs the plan's findings keep */
function plrFindings(string $planId): array
{
    $findings = json_decode((string) DB::table('plans')->where('id', $planId)->value('checks_json'), true) ?? [];

    return array_values(array_map(static fn (array $f): string => $f['detail'], array_filter($findings, static fn (array $f): bool => $f['check'] === 'line_repair')));
}

// Catches a plan asked again for a line that is only too long, a line repair asked for the whole plan, the repaired line not
// stored, and a line repaired with no trace.
it('shortens each line over its limit with a call of its own, and builds the plan once', function () {
    $fake = new FakePlanModel(plan: static fn (PlanRequest $request): array => plrLongLines($request));
    [$id, $scene] = plrBuild($this, $fake);

    expect($fake->planCalls)->toBe(1)
        ->and(array_map(static fn (PlanLineRepairRequest $r): string => "{$r->field}:{$r->limit}:{$r->language}", $fake->planLineRequests))
        ->toBe(['title_native:18:Russian', 'goals_native:30:Russian'])
        ->and($fake->planLineRequests[0]->line)->toBe('Запись к врачу на завтрашнее утро')
        // The fake's repair cuts the line to its limit; the answer is taken with its spaces trimmed.
        ->and($scene->title_native)->toBe('Запись к врачу на')
        ->and(json_decode((string) $scene->goals_native, true)[1])->toBe('ответить на все вопросы регист')
        ->and(plrFindings($id))->toHaveCount(2)
        ->and(plrFindings($id)[0])->toContain('→');
});

// «проверка лимита после него»: a line the repair did not bring under its limit, or a model that did not answer, leaves the
// line as the plan builder wrote it — the plan is never held back for a line. Catches an over-long answer stored, a silent
// model failing the plan, and either going unrecorded.
it('keeps the line as written when the repair is still over its limit, or the model is silent', function (bool $silent) {
    $fake = new FakePlanModel(
        plan: static fn (PlanRequest $request): array => plrLongLines($request),
        planLine: static fn (PlanLineRepairRequest $request): array => $silent ? throw new RuntimeException('timeout') : ['line' => $request->line.' ещё длиннее'],
    );
    [$id, $scene] = plrBuild($this, $fake);

    expect($scene->title_native)->toBe('Запись к врачу на завтрашнее утро')
        ->and(DB::table('plans')->where('id', $id)->value('status'))->toBe('ready')
        ->and(plrFindings($id)[0])->toContain($silent ? 'not shortened: the model did not answer' : 'still over 18');
})->with(['still over' => [false], 'silent' => [true]]);

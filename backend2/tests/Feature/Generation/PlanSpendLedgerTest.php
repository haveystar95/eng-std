<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\PlanSpend;
use App\Modules\Generation\Application\Port\RecordsPlanSpend;
use App\Modules\Generation\Domain\Exception\PlanSpendNotRecorded;
use App\Modules\Generation\Infrastructure\Eloquent\EloquentPlanSpendLedger;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

/**
 * THE PLAN'S LEDGER — and the one rule the PLAN-1a run bought the hard way.
 *
 * Three paid `gpt-5.4` calls happened during that run with no record anywhere: the request log's
 * CHECK refused `purpose = 'plan'` because the migration had not reached that database, and the
 * listener caught the refusal and dropped it — correctly, for a LOG. The plan then finished
 * successfully and nothing said the accounting had failed. The calls are unrecoverable.
 *
 * So the ledger is not a log. It shouts and it throws, and these tests are what stop that from
 * being quietly undone later.
 */
function planSpend(string $planId, string $userId, array $overrides = []): PlanSpend
{
    $d = [
        'call' => PlanSpend::CALL_OUTLINE,
        'subject' => 'Иду к врачу, болит спина',
        'supportLang' => 'ru',
        'targetLang' => 'en',
        'promptVersion' => 'plan.v0.1.1',
        'model' => 'gpt-5.4-2026-03-05',
        'tokensIn' => 4798,
        'tokensOut' => 544,
        'costUsd' => '0.020155',
        'size' => 0,
        'succeeded' => true,
        'error' => null,
        ...$overrides,
    ];

    return new PlanSpend(
        planId: $planId, userId: $userId, call: $d['call'], subject: $d['subject'],
        supportLang: $d['supportLang'], targetLang: $d['targetLang'],
        promptVersion: $d['promptVersion'], model: $d['model'], tokensIn: $d['tokensIn'],
        tokensOut: $d['tokensOut'], costUsd: $d['costUsd'], size: $d['size'],
        succeeded: $d['succeeded'], error: $d['error'],
    );
}

it('writes a plan call into the ledger with its purpose and its plan', function () {
    [$user] = learner();
    $planId = \App\Modules\Shared\Domain\ValueObject\Ulid::generate();

    app(EloquentPlanSpendLedger::class)->record(planSpend($planId, $user->id));

    $row = DB::table('generation_requests')->where('plan_id', $planId)->first();

    expect($row)->not->toBeNull()
        ->and($row->purpose)->toBe('plan')
        ->and($row->user_id)->toBe($user->id)
        ->and($row->model)->toBe('gpt-5.4-2026-03-05')
        ->and($row->tokens_in)->toBe(4798)
        ->and($row->tokens_out)->toBe(544)
        ->and((float) $row->cost_usd)->toBe(0.020155)
        ->and($row->status)->toBe('succeeded')
        ->and($row->collection_id)->toBeNull();
});

it('records a REFUSED answer as spent money, because it was', function () {
    [$user] = learner();
    $planId = \App\Modules\Shared\Domain\ValueObject\Ulid::generate();

    app(EloquentPlanSpendLedger::class)->record(planSpend($planId, $user->id, [
        'succeeded' => false,
        'error' => 'outline.checkpoint_count [день 1]: чек-пойнтов 4, а должно быть 2–3',
    ]));

    $row = DB::table('generation_requests')->where('plan_id', $planId)->first();

    expect($row->status)->toBe('failed')
        ->and((float) $row->cost_usd)->toBe(0.020155)
        ->and($row->error)->toContain('checkpoint_count');
});

it('SHOUTS AND THROWS when the ledger refuses the row — never swallows it', function () {
    [$user] = learner();
    $planId = \App\Modules\Shared\Domain\ValueObject\Ulid::generate();

    // The exact mechanism of the live incident: a CHECK that does not know the value being
    // written. Reproduced rather than mocked, so this test fails if the constraint is ever
    // widened in a way that hides the class of bug it stands for.
    DB::statement('ALTER TABLE generation_requests DROP CONSTRAINT IF EXISTS generation_requests_purpose_check');
    DB::statement(
        "ALTER TABLE generation_requests ADD CONSTRAINT generation_requests_purpose_check CHECK (purpose IN ('generation'))"
    );

    Log::spy();

    try {
        // Inside a savepoint: a refused statement poisons the enclosing Postgres transaction, and
        // the suite's own test transaction is that enclosing one. The savepoint is the harness's
        // problem, not the ledger's — it lets the assertions after the throw still be able to read.
        DB::transaction(fn () => app(EloquentPlanSpendLedger::class)->record(planSpend($planId, $user->id)));
        $this->fail('a refused ledger write was swallowed — the whole point of this class');
    } catch (PlanSpendNotRecorded $e) {
        expect($e->planId)->toBe($planId)
            ->and($e->costUsd)->toBe('0.020155')
            // The message has to carry the money: whoever reads the failure has to know what was
            // lost, not merely that something was.
            ->and($e->getMessage())->toContain('0.020155');
    }

    Log::shouldHaveReceived('error')
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'NOT recorded')
            && $context['plan_id'] === $planId
            && $context['cost_usd'] === '0.020155');

    expect(DB::table('generation_requests')->where('plan_id', $planId)->count())->toBe(0);
});

it('keeps plan money out of the collection allowance', function () {
    [$user, $token] = learner();
    $planId = \App\Modules\Shared\Domain\ValueObject\Ulid::generate();

    // Ten plan calls — a three-day plan is four of them — must not spend a single unit of the
    // learner's «создать коллекцию» quota. They are different acts with different limits.
    for ($i = 0; $i < 10; $i++) {
        app(EloquentPlanSpendLedger::class)->record(planSpend($planId, $user->id, ['call' => PlanSpend::CALL_DAY]));
    }

    $used = app(\App\Modules\Generation\Application\Port\GenerationQuota::class)
        ->usedOn(UserId::fromString($user->id), new DateTimeImmutable('now'));

    expect($used)->toBe(0);
});

it('keeps plan rows out of the prompt cache, which serves collections', function () {
    [$user] = learner();
    $planId = \App\Modules\Shared\Domain\ValueObject\Ulid::generate();

    // A plan row that would match a collection request word for word, if anything let it.
    app(EloquentPlanSpendLedger::class)->record(planSpend($planId, $user->id, ['subject' => 'кофейня']));
    DB::table('generation_requests')->where('plan_id', $planId)->update([
        'collection_id' => \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
    ]);

    $hit = app(\App\Modules\Generation\Domain\Repository\GenerationRequestRepository::class)
        ->findCacheableCollection('кофейня', new LanguageCode('ru'), new LanguageCode('en'), 'plan.v0.1.1');

    expect($hit)->toBeNull();
});

it('reports plan spend on its own line and still inside the total', function () {
    [$user] = learner();
    $planId = \App\Modules\Shared\Domain\ValueObject\Ulid::generate();

    app(EloquentPlanSpendLedger::class)->record(planSpend($planId, $user->id));

    $breakdown = app(\App\Modules\Admin\Application\Port\AdminCostReader::class)->breakdownSince(null);

    expect($breakdown->plan)->toBe(0.020155)
        // Not folded into `generation`: two products, two budgets, two lines.
        ->and($breakdown->generation)->toBe(0.0)
        // …and the money is still in the total, which is the half that must never be filtered.
        ->and($breakdown->total)->toBe(0.020155);
});

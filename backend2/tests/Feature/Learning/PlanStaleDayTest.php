<?php

declare(strict_types=1);

use App\Modules\Learning\Domain\Entity\PlanDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A DAY STUCK IN `generating` IS TAKEN BACK BY THE NEXT READ — вердикт владельца по GEN-1, замки
 * на таймаут и на `failed` в пейлоаде плана.
 *
 * The day is put into the dead-worker state by hand (the only way to reach it in a test: the
 * worker that would die is the sync queue itself), then read the two ways a person reads it —
 * the plan payload and the client's poll — and both must answer with the truth instead of
 * «собирается».
 */
beforeEach(function (): void {
    fakePlanModel();
    [$this->user, $this->token] = learner();
    profileFor($this->user, ['native_language' => 'ru', 'target_language' => 'en']);
    $this->planId = startedPlanFor($this, $this->token);
});

/** Put day 2 into the state a dead worker leaves behind. */
function strandDay(string $planId, int $attempts, string $claimedAt): void
{
    DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 2)->update([
        'status' => 'generating',
        'generation_attempts' => $attempts,
        'claimed_at' => $claimedAt,
        'collection_id' => null,
    ]);
}

function dayTwo(string $planId): object
{
    /** @var object $row */
    $row = DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 2)->first();

    return $row;
}

it('re-queues a day whose worker went silent for longer than the window, on the plan read', function () {
    strandDay($this->planId, attempts: 1, claimedAt: now()->subMinutes(11)->toDateTimeString());

    $payload = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/plans/{$this->planId}")->assertOk()->json('data');

    // The sync queue wrote the second attempt inside the read: the payload shows a READY day,
    // the row shows both attempts spent and a fresh claim stamp.
    $row = dayTwo($this->planId);
    expect($row->status)->toBe('ready')
        ->and($row->generation_attempts)->toBe(2)
        ->and($row->claimed_at)->not->toBeNull()
        ->and($payload['days'][1]['status'])->toBe('ready');
});

it('fails a day whose worker went silent twice, and the plan payload says so', function () {
    strandDay($this->planId, attempts: 2, claimedAt: now()->subMinutes(10)->toDateTimeString());

    $payload = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/plans/{$this->planId}")->assertOk()->json('data');

    expect($payload['days'][1]['status'])->toBe('failed')
        ->and($payload['days'][1]['fail_code'])->toBe(PlanDay::TIMED_OUT)
        ->and($payload['days'][1]['fail_reason'])->toContain('дважды')
        ->and(dayTwo($this->planId)->status)->toBe('failed');

    // The client's poll gets the same truth, and does not buy a third attempt.
    $before = DB::table('generation_requests')->where('plan_id', $this->planId)->count();
    $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/v1/plans/{$this->planId}/days/2/generate")
        ->assertOk()->assertJsonPath('data.status', 'failed');
    expect(DB::table('generation_requests')->where('plan_id', $this->planId)->count())->toBe($before);
});

it('leaves a day inside its window generating', function () {
    strandDay($this->planId, attempts: 1, claimedAt: now()->subMinutes(3)->toDateTimeString());

    $payload = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/plans/{$this->planId}")->assertOk()->json('data');

    expect($payload['days'][1]['status'])->toBe('generating')
        ->and(dayTwo($this->planId)->generation_attempts)->toBe(1);
});

<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => fakePlanModel());

/**
 * THE TWO ENDS OF THE EVENT (PLAN-1c, Ч.5).
 *
 * The morning: `POST /plans/{id}/rehearsal` — the plan's phrases and nothing else, because the
 * screen it feeds is three minutes long and its point is hearing yourself, not being tested.
 *
 * The evening: `POST /plans/{id}/feedback` — the abilities the learner actually used, which is the
 * one fact about a plan nothing can derive, and the act that CLOSES the plan and lets its words
 * back into the ordinary day.
 */
// Own helpers rather than PlanApiTest's: Pest loads a test file's functions only with that file,
// and hoisting them into tests/Pest.php would put two plan-building fixtures in every suite's
// global namespace for the sake of three lines.
function eventPlan(object $ctx, string $token): array
{
    $plan = $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', [
            'goal_text' => 'Иду к врачу, болит спина, надо объяснить и понять назначение',
            'target_lang' => 'en',
            'level' => 'basic',
            'event_date' => now()->addDays(2)->format('Y-m-d'),
            'minutes_per_day' => 20,
        ])
        ->assertCreated()
        ->json('data');

    $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/outline")
        ->assertOk();

    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/start")
        ->assertOk()
        ->json('data');
}

it('gives the rehearsal the plan PHRASES and no words at all', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);
    $plan = eventPlan($this, $token);

    $rehearsal = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/rehearsal")
        ->assertOk()
        ->json('data');

    expect($rehearsal['plan_id'])->toBe($plan['id'])
        ->and($rehearsal['title'])->toBe($plan['title'])
        ->and($rehearsal['lines'])->not->toBeEmpty();

    // Every line is a phrase of a day of this plan, and the days come in order.
    $seen = [];
    foreach ($rehearsal['lines'] as $line) {
        expect($line['text'])->toBeString()->not->toBe('')
            ->and($line['day_index'])->toBeGreaterThan(0);
        $seen[] = $line['day_index'];
    }
    $sorted = $seen;
    sort($sorted);
    expect($seen)->toBe($sorted);

    // Cross-check against the register: no line here may be a `word`.
    $words = collect($this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$plan['id']}/days/1")
        ->json('data.terms'))
        ->where('type', 'word')
        ->pluck('id')
        ->all();

    foreach ($rehearsal['lines'] as $line) {
        expect($words)->not->toContain($line['id']);
    }
});

it('hides another learner rehearsal behind a 404', function () {
    [$owner, $ownerToken] = learner();
    profileFor($owner, ['native_language' => 'ru']);
    $plan = eventPlan($this, $ownerToken);

    [$stranger, $strangerToken] = learner();
    profileFor($stranger, ['native_language' => 'ru']);

    // The guard caches the user it resolved for the FIRST request of a test, and every request in
    // one test shares an application instance — so without this the second bearer token is never
    // looked at and the test would pass while proving nothing.
    app('auth')->forgetGuards();

    $this->withHeader('Authorization', "Bearer {$strangerToken}")
        ->postJson("/api/v1/plans/{$plan['id']}/rehearsal")
        ->assertNotFound();
});

it('closes the plan when the learner says how it went — and lets the words go', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);
    $headers = ['Authorization' => "Bearer {$token}"];
    $plan = eventPlan($this, $token);

    $held = DB::table('user_term_progress')
        ->where('user_id', $user->id)
        ->whereRaw('enrollment_sources @> ?::jsonb', [json_encode(['plan:' . $plan['id']])])
        ->count();
    expect($held)->toBeGreaterThan(0);

    $after = $this->withHeaders($headers)
        ->postJson("/api/v1/plans/{$plan['id']}/feedback", ['checkpoints' => [0, 2, 2]])
        ->assertOk()
        ->json('data');

    // Deduplicated and ordered: the same ability twice is one ability said twice.
    expect($after['event_feedback'])->toBe([0, 2])
        ->and($after['status'])->toBe('completed')
        ->and($after['completed_at'])->not->toBeNull();

    // «18 слов ушли в общее повторение» — the claim is off every pair, and they are back in the
    // ordinary day.
    $stillHeld = DB::table('user_term_progress')
        ->where('user_id', $user->id)
        ->whereRaw('enrollment_sources @> ?::jsonb', [json_encode(['plan:' . $plan['id']])])
        ->count();
    expect($stillHeld)->toBe(0)
        ->and($this->withHeaders($headers)->getJson('/api/v1/home-plan')->json('data.in_work.total'))
        ->toBe($held);
});

it('tells «asked and used nothing» apart from «never asked»', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);
    $headers = ['Authorization' => "Bearer {$token}"];
    $plan = eventPlan($this, $token);

    // Never asked.
    expect($this->withHeaders($headers)->getJson("/api/v1/plans/{$plan['id']}")->json('data.event_feedback'))
        ->toBeNull();

    // Asked, and none of it came up. A different sentence on the finished plan, and it has to be
    // storable — «0 из 6» is a real outcome.
    $this->withHeaders($headers)
        ->postJson("/api/v1/plans/{$plan['id']}/feedback", ['checkpoints' => []])
        ->assertOk()
        ->assertJsonPath('data.event_feedback', []);
});

it('takes the answer again without complaining — a second tap is not a second event', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);
    $headers = ['Authorization' => "Bearer {$token}"];
    $plan = eventPlan($this, $token);

    $this->withHeaders($headers)
        ->postJson("/api/v1/plans/{$plan['id']}/feedback", ['checkpoints' => [0]])
        ->assertOk();
    $completedAt = $this->withHeaders($headers)
        ->getJson("/api/v1/plans/{$plan['id']}")
        ->json('data.completed_at');

    $this->withHeaders($headers)
        ->postJson("/api/v1/plans/{$plan['id']}/feedback", ['checkpoints' => [0, 1]])
        ->assertOk()
        ->assertJsonPath('data.event_feedback', [0, 1])
        // The plan closed once. The report is a fact about the event, not a log of attempts to
        // state it, so re-stating it does not move the moment the plan ended.
        ->assertJsonPath('data.completed_at', $completedAt);
});

it('refuses a checkpoint list that is not a list of positions', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru']);
    $plan = eventPlan($this, $token);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/feedback", ['checkpoints' => ['где болит']])
        ->assertStatus(422)
        ->assertJsonValidationErrors('checkpoints.0');
});

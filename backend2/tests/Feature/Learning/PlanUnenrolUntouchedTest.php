<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Port\PlanTermReleaser;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * WHAT A PLAN TAKES BACK WITH IT WHEN IT ENDS.
 *
 * Releasing is not unenrolling — the learner spent days on those words and a plan ending is not a
 * reason to stop studying them. That argument is about words they WORKED ON. A plan enrols fourteen
 * cards the moment a day is written, so a plan abandoned on day one used to leave fourteen words in
 * the pool for ever: a conversation that never happened, coming back due, and (before the top-up
 * was scoped) turning up inside other plans' lessons.
 */
function planPair(object $user, string $text, array $sources, bool $reviewed): string
{
    $termId = seedWordFor($user, $text, 'перевод ' . $text, enroll: false);

    DB::table('user_term_progress')->insert([
        'user_id' => $user->id, 'term_id' => $termId,
        'state' => 'new', 'acquisition' => 'learning', 'learning_step' => 1,
        'reps' => 0, 'successful_reviews' => 0, 'lapses' => 0,
        'ease_factor' => 2.5, 'interval_days' => 0, 'due_at' => null,
        'enrolled_at' => now()->subDay(), 'enrollment_sources' => json_encode($sources),
        'created_at' => now()->subDay(), 'updated_at' => now()->subDay(),
    ]);

    if ($reviewed) {
        DB::table('reviews')->insert([
            'id' => Ulid::generate(), 'user_id' => $user->id, 'term_id' => $termId,
            'grade' => 'good', 'is_correct' => true, 'is_practice' => false,
            'exercise_mode' => 'multiple_choice', 'answered_at' => now()->subHour(),
            'client_seq' => 1, 'created_at' => now()->subHour(),
        ]);
    }

    return $termId;
}

it('unenrols the words the plan put in and the learner never answered', function () {
    [$user] = learner();
    $planId = Ulid::generate();
    $source = 'plan:' . $planId;

    $untouched = planPair($user, 'aisle seat', [$source], reviewed: false);
    $answered = planPair($user, 'boarding pass', [$source], reviewed: true);
    $alsoMine = planPair($user, 'runway', [$source, 'manual'], reviewed: false);

    $left = app(PlanTermReleaser::class)->unenrolUntouched(UserId::fromString($user->id), $planId);

    expect($left)->toBe(1)
        // Never answered, and the plan was its only reason: out of the pool.
        ->and(DB::table('user_term_progress')->where('term_id', $untouched)->value('enrolled_at'))->toBeNull()
        // ANSWERED — the learner worked on it, so it stays. This is the half the release rule has
        // always protected.
        ->and(DB::table('user_term_progress')->where('term_id', $answered)->value('enrolled_at'))->not->toBeNull()
        // Saved by hand as well: that reason is the learner's own and did not go anywhere.
        ->and(DB::table('user_term_progress')->where('term_id', $alsoMine)->value('enrolled_at'))->not->toBeNull();
});

it('leaves another plan`s words alone', function () {
    [$user] = learner();
    $mine = Ulid::generate();
    $other = Ulid::generate();

    $theirs = planPair($user, 'gate', ['plan:' . $other], reviewed: false);

    expect(app(PlanTermReleaser::class)->unenrolUntouched(UserId::fromString($user->id), $mine))->toBe(0)
        ->and(DB::table('user_term_progress')->where('term_id', $theirs)->value('enrolled_at'))->not->toBeNull();
});

it('takes the untouched words out when a plan is abandoned, end to end', function () {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);

    [$user, $token, $planId] = startedPlan($this);

    $dayTerms = DB::table('collection_items')
        ->whereIn('collection_id', DB::table('learning_plan_days')->where('plan_id', $planId)->whereNotNull('collection_id')->pluck('collection_id'))
        ->pluck('term_id');

    expect($dayTerms)->not->toBeEmpty()
        ->and(DB::table('user_term_progress')->where('user_id', $user->id)->whereIn('term_id', $dayTerms)->whereNotNull('enrolled_at')->count())
        ->toBe($dayTerms->count());

    // Abandoned without answering a single card — the shape the rule is about.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$planId}/abandon", ['reason' => 'передумал'])
        ->assertOk();

    expect(DB::table('user_term_progress')->where('user_id', $user->id)->whereIn('term_id', $dayTerms)->whereNotNull('enrolled_at')->count())
        ->toBe(0);
});

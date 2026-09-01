<?php

declare(strict_types=1);

use App\Modules\Learning\Application\Port\PlanTermArchiver;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * AN ENDED PLAN TAKES ITS WORDS WITH IT.
 *
 * This file used to assert the opposite half of the rule — that a plan ending RELEASED its words
 * into the ordinary queue and only the never-answered ones left with it. The owner reversed it on
 * 01.09: a plan is a course with a subject and a date, an ended one is an archive, and its
 * vocabulary does not become the learner's daily queue by default. «Учить» is a decision the learner
 * makes, one word at a time, off the archive screen.
 *
 * What did NOT change is the one exception: a word the learner had also saved by hand has a reason
 * of their own, and that reason did not end with anybody's plan.
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

it('takes every word of the plan out of the pool, answered or not', function () {
    [$user] = learner();
    $planId = Ulid::generate();
    $source = 'plan:' . $planId;

    $untouched = planPair($user, 'aisle seat', [$source], reviewed: false);
    $answered = planPair($user, 'boarding pass', [$source], reviewed: true);
    $alsoMine = planPair($user, 'runway', [$source, 'manual'], reviewed: false);

    $left = app(PlanTermArchiver::class)->archivePlan(UserId::fromString($user->id), $planId);

    // Three pairs carried the plan's claim, so three rows were rewritten; two of them left the pool.
    expect($left)->toBe(3)
        ->and(DB::table('user_term_progress')->where('term_id', $untouched)->value('enrolled_at'))->toBeNull()
        // ANSWERED, and out of the pool all the same. This is the reversal: days spent on a word is
        // not by itself a reason for it to keep arriving in a queue the learner never chose.
        ->and(DB::table('user_term_progress')->where('term_id', $answered)->value('enrolled_at'))->toBeNull()
        // Saved by hand as well: that reason is the learner's own and did not go anywhere.
        ->and(DB::table('user_term_progress')->where('term_id', $alsoMine)->value('enrolled_at'))->not->toBeNull()
        ->and(json_decode((string) DB::table('user_term_progress')->where('term_id', $alsoMine)->value('enrollment_sources'), true))
        ->toBe(['manual']);
});

it('keeps the results — a word taken out of the pool resumes where it left off', function () {
    [$user] = learner();
    $planId = Ulid::generate();

    $termId = planPair($user, 'departure', ['plan:' . $planId], reviewed: true);
    DB::table('user_term_progress')->where('term_id', $termId)
        ->update(['acquisition' => 'graduated', 'reps' => 5, 'successful_reviews' => 3, 'interval_days' => 12]);

    app(PlanTermArchiver::class)->archivePlan(UserId::fromString($user->id), $planId);

    $row = DB::table('user_term_progress')->where('term_id', $termId)->first();

    expect($row->enrolled_at)->toBeNull()
        // The rung, the schedule and the log: untouched. Archiving is the pool's own «пауза».
        ->and((int) $row->reps)->toBe(5)
        ->and((int) $row->successful_reviews)->toBe(3)
        ->and((int) $row->interval_days)->toBe(12)
        ->and(DB::table('reviews')->where('term_id', $termId)->count())->toBe(1);
});

it('leaves another plan`s words alone', function () {
    [$user] = learner();
    $mine = Ulid::generate();
    $other = Ulid::generate();

    $theirs = planPair($user, 'gate', ['plan:' . $other], reviewed: false);

    expect(app(PlanTermArchiver::class)->archivePlan(UserId::fromString($user->id), $mine))->toBe(0)
        ->and(DB::table('user_term_progress')->where('term_id', $theirs)->value('enrolled_at'))->not->toBeNull();
});

it('takes the words out when a plan is abandoned, end to end', function () {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);

    [$user, $token, $planId] = startedPlan($this);

    $dayTerms = DB::table('collection_items')
        ->whereIn('collection_id', DB::table('learning_plan_days')->where('plan_id', $planId)->whereNotNull('collection_id')->pluck('collection_id'))
        ->pluck('term_id');

    expect($dayTerms)->not->toBeEmpty()
        ->and(DB::table('user_term_progress')->where('user_id', $user->id)->whereIn('term_id', $dayTerms)->whereNotNull('enrolled_at')->count())
        ->toBe($dayTerms->count());

    // A day WALKED first, so the plan is abandoned holding words the learner really worked on.
    walkDay($this, $token, $planId, 1);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$planId}/abandon", ['reason' => 'передумал'])
        ->assertOk();

    expect(DB::table('user_term_progress')->where('user_id', $user->id)->whereIn('term_id', $dayTerms)->whereNotNull('enrolled_at')->count())
        ->toBe(0);
});

/**
 * IN THE HANDLER, NOT IN THE COMMAND — PLAN-FIX-4 п. 1.2.
 *
 * `plan:archive-terms` exists because plans that ended BEFORE the archive rule did had to be
 * cleaned up once (DECISIONS п. 214). It is a repair, and a repair that a live ending depends on is
 * a live ending that is broken between the ending and the next time somebody remembers to run a
 * console command. `EndPlanHandler` archives inside the same transaction that writes the status;
 * this asserts the state the pool is in the instant the request returns, with nothing run after it.
 */
it('leaves no pool row standing on the ended plan alone, the instant abandon returns', function () {
    fakePlanModel();
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);

    [$user, $token, $planId] = startedPlan($this);
    $source = 'plan:' . $planId;

    // One word the learner ALSO keeps by hand: its own reason did not end with the plan, so the
    // label comes off and the row stays in the pool. The rule has two halves and both are asserted.
    $mine = (string) DB::table('collection_items')
        ->where('collection_id', DB::table('learning_plan_days')->where('plan_id', $planId)->where('day_index', 1)->value('collection_id'))
        ->orderBy('position')
        ->value('term_id');
    DB::table('user_term_progress')->where('user_id', $user->id)->where('term_id', $mine)
        ->update(['enrollment_sources' => json_encode([$source, 'manual'])]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$planId}/abandon", ['reason' => 'передумал'])
        ->assertOk();

    $rows = DB::table('user_term_progress')->where('user_id', $user->id)->get(['term_id', 'enrolled_at', 'enrollment_sources']);

    foreach ($rows as $row) {
        // The label is gone from every row — nobody is left standing on an ended plan.
        expect(json_decode((string) $row->enrollment_sources, true))->not->toContain($source);
    }

    expect(DB::table('user_term_progress')->where('user_id', $user->id)->whereNotNull('enrolled_at')->pluck('term_id')->all())
        ->toBe([$mine]);
});

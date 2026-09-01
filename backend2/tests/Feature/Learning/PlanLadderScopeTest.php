<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * THE LADDER OF A PLAN IS THE PLAN'S OWN — PLAN-FIX-4 п. 1.1.
 *
 * Terms are globally deduplicated, so the same row carries every history the learner has with that
 * word: the plan they abandoned this morning, the notebook, this plan. Fed all of it, a new plan
 * read somebody else's answers as its own — the owner's day 1 on 01.09 opened on `speaking` with no
 * introduction, and one card of it owed nothing at all and never appeared.
 *
 * A standing therefore counts for the pair (PLAN, term): only what happened after the card joined
 * THIS plan. The other half of the same sentence, and the half that is easy to break by accident,
 * is that nothing is written — the notebook's own rung, schedule and log stand exactly where they
 * stood.
 */
beforeEach(function (): void {
    fakePlanModel();
    // The plan ladder deals `intro` and `speaking` and both ship dark; without the owner's switch
    // this file would be testing a shorter ladder than the one it describes.
    DB::table('learning_mode_settings')->where('scope', 'global')->whereNull('user_id')->update(['enabled' => true]);
});

/** The modes the session owes for one term, in the order it deals them. */
function chainFor(array $session, string $termId): array
{
    $out = [];
    foreach ($session['tasks'] as $task) {
        if ($task['card']['term_id'] === $termId) {
            $out[] = $task['card']['exercise_mode'];
        }
    }

    return $out;
}

it('starts a new plan at the introduction for cards an abandoned plan had already walked', function () {
    [$user, $token, $first] = startedPlan($this);

    // The whole of day 1 answered — every card of it is now introduced, recognised and said.
    walkDay($this, $token, $first, 1);

    $terms = DB::table('collection_items')
        ->where('collection_id', DB::table('learning_plan_days')->where('plan_id', $first)->where('day_index', 1)->value('collection_id'))
        ->pluck('term_id')
        ->all();

    expect(DB::table('reviews')->where('user_id', $user->id)->whereIn('term_id', $terms)->count())->toBeGreaterThan(0)
        ->and(DB::table('term_exposures')->where('user_id', $user->id)->whereIn('term_id', $terms)->count())->toBeGreaterThan(0);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$first}/abandon", ['reason' => 'передумал'])
        ->assertOk();

    // A SECOND, EXPLICITLY. Both sides of the cutoff are `timestamp(0)`, and this whole fixture runs
    // inside one of them: without the wait the old plan's answers carry the very timestamp the new
    // plan's collection is stamped with, and «before» cannot be told from «at». Real days are
    // written minutes to hours before the sitting that answers them.
    $this->travel(5)->seconds();

    // The SAME cards, dealt by a new plan — the double names its terms after the day, so the two
    // plans stand on the same rows, which is the whole point of the fixture.
    $second = startedPlanFor($this, $token);
    $session = planSession($this, $token, $second);

    expect($session['tasks'])->not->toBeEmpty()
        ->and($session['tasks'][0]['card']['exercise_mode'])->toBe('intro');

    $dayTerms = [];
    foreach ($session['tasks'] as $task) {
        $dayTerms[$task['card']['term_id']] = true;
    }
    foreach (array_keys($dayTerms) as $termId) {
        // Every card of the new day owes its introduction first, whatever the old plan did to it.
        expect(chainFor($session, $termId)[0])->toBe('intro');
    }

    // And EVERY card the first plan walked to the end is in the new day rather than silently
    // absent — «owes nothing, so it is not dealt» is how one of them disappeared from the owner's
    // sitting on 01.09.
    expect(array_diff($terms, array_keys($dayTerms)))->toBe([]);
});

it('leaves the notebook standing of a word exactly where it was', function () {
    [$user, $token, $first] = startedPlan($this);
    walkDay($this, $token, $first, 1);

    $termId = (string) DB::table('collection_items')
        ->where('collection_id', DB::table('learning_plan_days')->where('plan_id', $first)->where('day_index', 1)->value('collection_id'))
        ->orderBy('position')
        ->value('term_id');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$first}/abandon", ['reason' => 'передумал'])
        ->assertOk();

    // The learner keeps this one by hand: their own reason, their own rung, and it survived the
    // archive because of it.
    DB::table('user_term_progress')->where('user_id', $user->id)->where('term_id', $termId)->update([
        'enrolled_at' => now()->subDay(),
        'enrollment_sources' => json_encode(['manual']),
        'acquisition' => 'learning',
        'learning_step' => 2,
    ]);

    // A SECOND, EXPLICITLY. Both sides of the cutoff are `timestamp(0)`, and this whole fixture runs
    // inside one of them: without the wait the old plan's answers carry the very timestamp the new
    // plan's collection is stamped with, and «before» cannot be told from «at». Real days are
    // written minutes to hours before the sitting that answers them.
    $this->travel(5)->seconds();

    // Captured AFTER the new plan has enrolled its words (that is an enrolment and it is allowed to
    // touch the row) and BEFORE the sitting is built, which is the thing that must not.
    $second = startedPlanFor($this, $token);
    $before = DB::table('user_term_progress')->where('user_id', $user->id)->orderBy('term_id')->get()->map(
        static fn (object $row): array => (array) $row,
    )->all();

    $session = planSession($this, $token, $second);

    // The word carries a notebook rung of its own AND a walked plan behind it, and the new plan
    // still meets it from the beginning.
    expect(chainFor($session, $termId))->not->toBeEmpty()
        ->and(chainFor($session, $termId)[0])->toBe('intro');

    $after = DB::table('user_term_progress')->where('user_id', $user->id)->orderBy('term_id')->get()->map(
        static fn (object $row): array => (array) $row,
    )->all();

    // Building a plan session READS the ladder and writes nothing to any pair.
    expect($after)->toBe($before);
});


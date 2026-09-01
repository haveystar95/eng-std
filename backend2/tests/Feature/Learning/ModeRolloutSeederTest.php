<?php

declare(strict_types=1);

use Database\Seeders\LearningModeSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * A FRESH DATABASE IS THE PRODUCT THE OWNER RUNS — `migrate` and then `db:seed`.
 *
 * Every migration ships a new trainer dark and it is switched on себе → бете → всем from the admin
 * panel; that rule is not in question here. What was missing is the «всем» half surviving a new
 * database. The live run measured what that cost: `intro` and `speaking` are enabled in the plan's
 * own matrix at every level and were cut by the GLOBAL row, so stage A collapsed from a checklist
 * of four steps to two, and a plan on a fresh account was 27 tasks where the design says 56
 * (`docs/research/e2e-sim-1.md`, Д-14, Д-15). Nothing on any screen said so.
 */
beforeEach(fn () => fakePlanModel());

it('ships the matrix DARK, which is the migration doing its job', function () {
    // Stated as an assertion rather than assumed, because the seeder below is only correct while
    // this is true: if a migration ever starts shipping these on, the seeder is dead weight.
    $dark = DB::table('learning_mode_settings')
        ->whereNull('user_id')->where('scope', 'global')->where('enabled', false)
        ->pluck('mode')->sort()->values()->all();

    expect($dark)->toBe(['description_match', 'dictation', 'intro', 'pick_correct', 'speaking']);

    // …and the PLAN matrix has always wanted them. The intersection is where they died.
    expect(DB::table('learning_mode_settings')
        ->where('scope', 'plan')->where('level', 'basic')->whereIn('mode', ['intro', 'speaking'])
        ->pluck('enabled')->all())->toBe([true, true]);
});

it('turns on what the owner has already rolled out, and leaves the gates alone', function () {
    $gates = static fn (): array => DB::table('learning_mode_settings')
        ->whereNull('user_id')->where('scope', 'global')
        ->orderBy('mode')
        ->get(['mode', 'min_acquisition', 'min_learning_step', 'min_successful_reviews', 'options_policy'])
        ->map(static fn (object $row): array => (array) $row)
        ->all();

    $before = $gates();

    $this->seed(LearningModeSettingsSeeder::class);

    // The rollout moved; not one gate did. The seeder writes `enabled` and `position` and has no
    // opinion about when a trainer is allowed to appear.
    expect($gates())->toBe($before)
        ->and(DB::table('learning_mode_settings')
            ->whereNull('user_id')->where('scope', 'global')->where('enabled', false)->count())->toBe(0);
});

it('runs twice with the same result, and the second run writes nothing at all', function () {
    $rows = static fn (): array => DB::table('learning_mode_settings')
        ->orderBy('id')->get()
        ->map(static fn (object $row): array => (array) $row)
        ->all();

    $this->seed(LearningModeSettingsSeeder::class);
    $once = $rows();

    $this->seed(LearningModeSettingsSeeder::class);

    // `updated_at` included: a second run that touched it would make «when did this trainer go
    // out» read as «the last time anybody ran a seeder».
    expect($rows())->toBe($once);
});

it('never touches a learner’s own override', function () {
    [$user] = learner();
    $mine = DB::table('learning_mode_settings')->insertGetId([
        'id' => (string) \App\Modules\Shared\Domain\ValueObject\Ulid::generate(),
        'user_id' => $user->id,
        'scope' => 'global',
        'mode' => 'speaking',
        'enabled' => false,
        'position' => 3,
        'min_acquisition' => 'graduated',
        'options_policy' => 'standard',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->seed(LearningModeSettingsSeeder::class);

    // A person switched this off for themselves. A seeder that could undo that would be a settings
    // screen that lies.
    expect(DB::table('learning_mode_settings')
        ->where('user_id', $user->id)->where('mode', 'speaking')->value('enabled'))->toBeFalse()
        ->and($mine)->not->toBeNull();
});

it('gives a plan on a SEEDED database the intro card first (Д-15)', function () {
    $this->seed(LearningModeSettingsSeeder::class);

    [, $token, $planId] = startedPlan($this);
    $session = planSession($this, $token, $planId);

    $first = $session['tasks'][0]['card']['term_id'];
    $chain = [];
    foreach ($session['tasks'] as $task) {
        if ($task['card']['term_id'] === $first) {
            $chain[] = $task['card']['exercise_mode'];
        }
    }

    // Met, then recognised, then produced, then said out loud — the checklist the plan ladder has
    // always described and a fresh account never got.
    expect($chain[0])->toBe('intro')
        ->and($chain)->toContain('speaking');
});

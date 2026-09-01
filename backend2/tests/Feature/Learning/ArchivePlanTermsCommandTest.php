<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * THE SWEEP FOR THE PLANS THAT ENDED BEFORE THE RULE CHANGED.
 *
 * Every plan finished or abandoned before 01.09 released its words into the ordinary queue and left
 * them there. Those rows cannot be found by their `enrollment_sources` — the old release removed the
 * `plan:` marker, and the scheduler used to wipe the whole list on a word's first answer — so the
 * command reads the ARCHIVE instead: plan → days → collections → terms, a path nothing ever wrote
 * over.
 *
 * These tests are about the two ways such a sweep goes wrong: taking a word it had no business
 * taking, and writing when nobody asked it to.
 */
function endedPlanFixture(object $user, string $status): array
{
    $planId = Ulid::generate();
    $collectionId = Ulid::generate();

    DB::table('collections')->insert([
        'id' => $collectionId, 'owner_id' => $user->id, 'type' => 'custom', 'source' => 'ai',
        'title' => 'День 1', 'source_lang' => 'ru', 'target_lang' => 'en', 'visibility' => 'private',
        'items_count' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('learning_plans')->insert([
        'id' => $planId, 'user_id' => $user->id, 'status' => $status,
        'goal_text' => 'цель', 'title' => 'План ' . $status, 'target_lang' => 'en', 'support_lang' => 'ru',
        'level' => 'basic', 'minutes_per_day' => 20, 'event_date' => now()->subDays(3)->format('Y-m-d'),
        'created_at' => now()->subDays(9), 'updated_at' => now()->subDays(3),
    ]);
    DB::table('learning_plan_days')->insert([
        'id' => Ulid::generate(), 'plan_id' => $planId, 'day_index' => 1, 'status' => 'done',
        'title' => 'День 1', 'collection_id' => $collectionId, 'kind' => 'intro', 'generation_attempts' => 1,
        'created_at' => now()->subDays(9), 'updated_at' => now()->subDays(3),
    ]);

    return [$planId, $collectionId];
}

/** A term on a plan-day collection, with a progress row in the pool. */
function pooledTermOn(object $user, string $collectionId, string $text, array $sources): string
{
    $termId = Ulid::generate();
    DB::table('terms')->insert([
        'id' => $termId, 'lang' => 'en', 'text' => $text, 'normalized_text' => mb_strtolower($text),
        'type' => 'word', 'source' => 'ai', 'cefr' => 'A2', 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('collection_items')->insert([
        'id' => Ulid::generate(), 'collection_id' => $collectionId, 'term_id' => $termId,
        'position' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('user_term_progress')->insert([
        'user_id' => $user->id, 'term_id' => $termId,
        'state' => 'new', 'acquisition' => 'graduated', 'learning_step' => 0,
        'reps' => 4, 'successful_reviews' => 2, 'lapses' => 0,
        'ease_factor' => 2.5, 'interval_days' => 6, 'due_at' => now()->subDays(2),
        'enrolled_at' => now()->subDays(8), 'enrollment_sources' => json_encode($sources),
        'created_at' => now()->subDays(8), 'updated_at' => now()->subDays(2),
    ]);

    return $termId;
}

it('reports without writing unless it is told to apply', function () {
    [$user] = learner();
    [, $collectionId] = endedPlanFixture($user, 'abandoned');
    // `[]` — the shape the owner's own rows are in: the reason was erased on the way out.
    $termId = pooledTermOn($user, $collectionId, 'passport', []);

    $this->artisan('plan:archive-terms')->assertExitCode(0);

    expect(DB::table('user_term_progress')->where('term_id', $termId)->value('enrolled_at'))->not->toBeNull();

    $this->artisan('plan:archive-terms', ['--apply' => true])->assertExitCode(0);

    expect(DB::table('user_term_progress')->where('term_id', $termId)->value('enrolled_at'))->toBeNull();
});

it('refuses to take a word the learner has their own reason for', function () {
    [$user] = learner();
    [, $collectionId] = endedPlanFixture($user, 'completed');

    $planOnly = pooledTermOn($user, $collectionId, 'deposit', []);
    $alsoManual = pooledTermOn($user, $collectionId, 'luggage', ['manual']);
    $triaged = pooledTermOn($user, $collectionId, 'boarding', ['triage']);

    $this->artisan('plan:archive-terms', ['--apply' => true])->assertExitCode(0);

    expect(DB::table('user_term_progress')->where('term_id', $planOnly)->value('enrolled_at'))->toBeNull()
        ->and(DB::table('user_term_progress')->where('term_id', $alsoManual)->value('enrolled_at'))->not->toBeNull()
        ->and(DB::table('user_term_progress')->where('term_id', $triaged)->value('enrolled_at'))->not->toBeNull();
});

it('finds a word by its marker even when the day`s collection no longer lists it', function () {
    [$user] = learner();
    [$planId, $collectionId] = endedPlanFixture($user, 'abandoned');
    $termId = pooledTermOn($user, $collectionId, 'reception', ['plan:' . $planId]);

    // The word was taken off the day's collection at some point. The structural path cannot see a
    // soft-deleted item, and the marker is then the only provenance left — six live rows were in
    // exactly this state and survived the first sweep because of it.
    DB::table('collection_items')->where('term_id', $termId)->update(['deleted_at' => now()]);

    $this->artisan('plan:archive-terms', ['--apply' => true])->assertExitCode(0);

    expect(DB::table('user_term_progress')->where('term_id', $termId)->value('enrolled_at'))->toBeNull();
});

it('leaves a word alone while any plan of that learner is still standing on it', function () {
    [$user] = learner();
    [, $endedCollection] = endedPlanFixture($user, 'abandoned');
    [, $runningCollection] = endedPlanFixture($user, 'active');

    $sharedId = pooledTermOn($user, $endedCollection, 'available', []);
    // The same term, also on the running plan's day.
    DB::table('collection_items')->insert([
        'id' => Ulid::generate(), 'collection_id' => $runningCollection, 'term_id' => $sharedId,
        'position' => 0, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->artisan('plan:archive-terms', ['--apply' => true])->assertExitCode(0);

    expect(DB::table('user_term_progress')->where('term_id', $sharedId)->value('enrolled_at'))->not->toBeNull();
});

it('touches nothing but the pool — the plan, its days and the log stay readable', function () {
    [$user] = learner();
    [$planId, $collectionId] = endedPlanFixture($user, 'completed');
    $termId = pooledTermOn($user, $collectionId, 'utilities', []);
    DB::table('reviews')->insert([
        'id' => Ulid::generate(), 'user_id' => $user->id, 'term_id' => $termId,
        'grade' => 'good', 'is_correct' => true, 'is_practice' => false,
        'exercise_mode' => 'typing', 'answered_at' => now()->subDays(4),
        'client_seq' => 1, 'created_at' => now()->subDays(4),
    ]);

    $this->artisan('plan:archive-terms', ['--apply' => true])->assertExitCode(0);

    $row = DB::table('user_term_progress')->where('term_id', $termId)->first();

    expect(DB::table('learning_plans')->where('id', $planId)->count())->toBe(1)
        ->and(DB::table('learning_plan_days')->where('plan_id', $planId)->count())->toBe(1)
        ->and(DB::table('collection_items')->where('collection_id', $collectionId)->count())->toBe(1)
        ->and(DB::table('reviews')->where('term_id', $termId)->count())->toBe(1)
        // …and the results on the row itself, so «Учить» later resumes rather than restarts.
        ->and((int) $row->reps)->toBe(4)
        ->and((int) $row->interval_days)->toBe(6);
});

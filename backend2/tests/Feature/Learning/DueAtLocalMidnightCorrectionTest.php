<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Learning\Domain\ValueObject\Acquisition;
use App\Modules\Learning\Domain\ValueObject\LearningState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * THE ONE-SHOT CORRECTION of due dates written while the offset was being dropped (QA-BUG-1), run
 * against the real migration statement — a transcribed copy of the SQL would pass while the shipped
 * one was wrong.
 */
function runDueAtCorrection(): void
{
    $migration = require base_path('app/Modules/Learning/Infrastructure/Migration/2026_08_23_150000_move_due_at_to_owner_local_midnight.php');
    $migration->correct();
}

/** A learner with $tz on the profile (null = no profile row at all). */
function ownerWith(?string $tz): User
{
    $user = User::factory()->create();
    if ($tz !== null) {
        Profile::create(['user_id' => $user->id, 'daily_goal' => 20, 'timezone' => $tz]);
    }

    return $user;
}

/** A progress row with due_at written verbatim, bypassing the (now fixed) mapper. */
function progressRowDue(User $user, string $termId, string $state, string $dueAtUtc, int $interval = 1): void
{
    DB::table('user_term_progress')->insert([
        'user_id' => $user->id,
        'term_id' => $termId,
        'state' => $state,
        'acquisition' => Acquisition::Graduated->value,
        'learning_step' => 0,
        'reps' => 3,
        'successful_reviews' => 1,
        'lapses' => 0,
        'ease_factor' => 2.5,
        'interval_days' => $interval,
        'due_at' => $dueAtUtc,
        'enrolled_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function dueOf(User $user, string $termId): string
{
    return (string) DB::table('user_term_progress')
        ->where('user_id', $user->id)->where('term_id', $termId)->value('due_at');
}

it('re-reads a UTC-midnight due date in the owner\'s zone', function () {
    $user = ownerWith('Europe/Bucharest');
    $term = seedWordFor($user, 'apple', 'яблоко', enroll: false);
    // What the broken write left behind: the intended 24 August, Bucharest, stored as midnight UTC.
    progressRowDue($user, $term, LearningState::Review->value, '2026-08-24 00:00:00+00');

    runDueAtCorrection();

    expect((new DateTimeImmutable(dueOf($user, $term)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))
        ->toBe('2026-08-23 21:00:00'); // = 2026-08-24 00:00 in Bucharest
});

it('uses the zone\'s offset ON THAT DATE, not today\'s', function () {
    // A due date in February, corrected in August: Bucharest is +02:00 in winter, +03:00 in summer.
    // A fixed offset would put this card an hour off; the named zone gets it right.
    $user = ownerWith('Europe/Bucharest');
    $term = seedWordFor($user, 'winter', 'зима', enroll: false);
    progressRowDue($user, $term, LearningState::Review->value, '2027-02-10 00:00:00+00');

    runDueAtCorrection();

    expect((new DateTimeImmutable(dueOf($user, $term)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))
        ->toBe('2027-02-09 22:00:00'); // = 2027-02-10 00:00 EET (+02:00)
});

it('leaves an exact-moment due alone — a 0-day step and a known verification', function () {
    $user = ownerWith('Europe/Bucharest');
    $again = seedWordFor($user, 'again', 'снова', enroll: false);
    $known = seedWordFor($user, 'known', 'известно', enroll: false);

    // "Again this session": due at the moment of the answer, never floored.
    progressRowDue($user, $again, LearningState::Learning->value, '2026-08-23 16:00:00+00', interval: 0);
    // A passed `known` check: 90 days out, time of day and all.
    progressRowDue($user, $known, LearningState::Known->value, '2026-11-21 16:00:00+00', interval: 0);

    runDueAtCorrection();

    expect((new DateTimeImmutable(dueOf($user, $again)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))
        ->toBe('2026-08-23 16:00:00')
        ->and((new DateTimeImmutable(dueOf($user, $known)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))
        ->toBe('2026-11-21 16:00:00');
});

it('leaves a learner with no zone on file untouched — UTC already IS their local midnight', function () {
    $noProfile = ownerWith(null);
    $emptyZone = ownerWith('');
    $termA = seedWordFor($noProfile, 'apple', 'яблоко', enroll: false);
    $termB = seedWordFor($emptyZone, 'pear', 'груша', enroll: false);
    progressRowDue($noProfile, $termA, LearningState::Review->value, '2026-08-24 00:00:00+00');
    progressRowDue($emptyZone, $termB, LearningState::Review->value, '2026-08-24 00:00:00+00');

    runDueAtCorrection();

    expect((new DateTimeImmutable(dueOf($noProfile, $termA)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))
        ->toBe('2026-08-24 00:00:00')
        ->and((new DateTimeImmutable(dueOf($emptyZone, $termB)))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))
        ->toBe('2026-08-24 00:00:00');
});

it('is idempotent — a second run does not shift a corrected row again', function () {
    $user = ownerWith('Europe/Bucharest');
    $term = seedWordFor($user, 'apple', 'яблоко', enroll: false);
    progressRowDue($user, $term, LearningState::Review->value, '2026-08-24 00:00:00+00');

    runDueAtCorrection();
    $once = dueOf($user, $term);
    runDueAtCorrection();

    expect(dueOf($user, $term))->toBe($once);
});

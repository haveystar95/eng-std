<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Learning\Domain\Service\Fuzz;
use App\Modules\Learning\Domain\Service\Scheduler;
use App\Modules\Learning\Domain\Service\Sm2Scheduler;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * QA-BUG-1 — the next due date must be midnight in the LEARNER'S calendar zone, and must survive
 * the trip into Postgres as that instant. The scheduler already floored to the user's day; what was
 * lost was the offset on the write, so a Bucharest midnight came back to the phone as 03:00.
 *
 * These are round-trip tests on purpose: the arithmetic is covered by the unit suite, and the bug
 * was not in the arithmetic — it was between the entity and the column.
 */

/** A learner in $tz, with the fuzz disabled so an interval is the exact number of days. */
function tzLearner(string $tz): array
{
    app()->bind(Scheduler::class, static fn (): Sm2Scheduler => new Sm2Scheduler(Fuzz::none()));

    $user = User::factory()->create();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 20, 'timezone' => $tz]);

    return [$user, $user->createToken('device')->plainTextToken];
}

/**
 * Answer one term $times times at the same instant. The first two answers are the recognition
 * rungs (they never reach the scheduler); every answer after that is an SM-2 step.
 */
function answerAtInstant(object $ctx, string $token, string $termId, string $answeredAtIso, int $times): void
{
    $reviews = [];
    for ($i = 0; $i < $times; $i++) {
        $reviews[] = [
            'id' => Ulid::generate(),
            'term_id' => $termId,
            'exercise_mode' => 'typing',
            'response' => 'apple',
            'answered_at' => $answeredAtIso,
            'client_seq' => $i + 1,
        ];
    }

    $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => $reviews])
        ->assertOk();
}

/** @return array{0: string, 1: int} the raw stored due_at and the interval it was scheduled with */
function storedDue(User $user): array
{
    $row = DB::table('user_term_progress')->where('user_id', $user->id)->first();

    return [(string) $row->due_at, (int) $row->interval_days];
}

it('stores due_at as the learner\'s local midnight, not midnight UTC', function () {
    // Europe/Bucharest is UTC+3 in August, so the start of the learner's 24th of August is
    // 21:00Z on the 23rd. Stored as midnight UTC (the bug) the phone showed the card as due 03:00.
    [$user, $token] = tzLearner('Europe/Bucharest');
    $term = seedWordFor($user, 'apple', 'яблоко');

    answerAtInstant($this, $token, $term, '2026-08-23T19:00:00+03:00', times: 3);

    [$due, $interval] = storedDue($user);
    expect($interval)->toBe(1);

    $dueAt = new DateTimeImmutable($due);
    expect($dueAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-08-23 21:00:00')
        ->and($dueAt->setTimezone(new DateTimeZone('Europe/Bucharest'))->format('Y-m-d H:i:s'))->toBe('2026-08-24 00:00:00');
});

it('keeps the calendar day when the interval crosses a clock change', function () {
    // Answered 23:30 on 27 March 2026, while Bucharest is still EET (+02:00). DST starts on the
    // 29th, so the four-day step lands on 31 March in EEST (+03:00).
    //
    // «+4 days» is four CALENDAR days: 27 → 31 March, floored to 00:00 EEST = 2026-03-30T21:00Z.
    // Counted as 96 exact hours in UTC instead, the answer's 23:30 wall clock slides to 00:30 the
    // next morning and the card is floored to 1 April — a whole day late, once a year.
    [$user, $token] = tzLearner('Europe/Bucharest');
    $term = seedWordFor($user, 'apple', 'яблоко');

    // Four answers: two recognition rungs, then new→learning (1 day), then learning→review (4 days).
    answerAtInstant($this, $token, $term, '2026-03-27T23:30:00+02:00', times: 4);

    [$due, $interval] = storedDue($user);
    expect($interval)->toBe(4);

    $dueAt = new DateTimeImmutable($due);
    expect($dueAt->setTimezone(new DateTimeZone('Europe/Bucharest'))->format('Y-m-d H:i:s'))->toBe('2026-03-31 00:00:00')
        ->and($dueAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-03-30 21:00:00');
});

it('falls back to UTC midnight for a learner with no timezone on file', function () {
    [$user, $token] = tzLearner('');
    $term = seedWordFor($user, 'apple', 'яблоко');

    answerAtInstant($this, $token, $term, '2026-08-23T19:00:00+00:00', times: 3);

    [$due] = storedDue($user);
    expect((new DateTimeImmutable($due))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))
        ->toBe('2026-08-24 00:00:00');
});

<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Learning\Application\Port\StatsReader;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * QAF-3 — the device's own timestamps must survive the trip into Postgres as INSTANTS.
 *
 * QA-BUG-1 fixed this for the scheduler's columns (see DueAtTimezoneTest); `reviews.answered_at` and
 * `term_exposures.shown_at` were written by their own repositories, straight past that mapper, and
 * lost the offset exactly the same way: 23:30+03:00 landed in the column as 23:30Z. Three hours
 * forward is a different CALENDAR DAY for anything answered late in the evening, and the streak, the
 * activity calendar and «сегодня» all bucket by the learner's local date.
 */

/** A learner in $tz. */
function qaf3Learner(string $tz = 'Europe/Bucharest'): array
{
    $user = User::factory()->create();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 20, 'timezone' => $tz]);

    return [$user, $user->createToken('device')->plainTextToken];
}

/** The stored value, read back as UTC wall clock — what the column actually means. */
function storedUtc(string $table, string $column, string $userId): string
{
    $raw = (string) DB::table($table)->where('user_id', $userId)->value($column);

    return (new DateTimeImmutable($raw))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

it('stores answered_at as the instant the device meant, not its wall clock', function () {
    [$user, $token] = qaf3Learner();
    $term = seedWordFor($user, 'apple', 'яблоко');

    // 23:30 in Bucharest on 23 August is 20:30Z — still the 23rd everywhere. Stored as 23:30Z (the
    // bug) it reads back as 02:30 on the 24th in the learner's own zone: the evening's work lands on
    // tomorrow's calendar day.
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(),
            'term_id' => $term,
            'exercise_mode' => 'typing',
            'response' => 'apple',
            'answered_at' => '2026-08-23T23:30:00+03:00',
            'client_seq' => 1,
        ]]])
        ->assertOk()
        ->assertJsonPath('data.accepted', 1);

    expect(storedUtc('reviews', 'answered_at', $user->id))->toBe('2026-08-23 20:30:00');
});

it('stores an intro exposure at the instant it was shown', function () {
    [$user, $token] = qaf3Learner();
    $term = seedWordFor($user, 'apple', 'яблоко');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['exposures' => [[
            'term_id' => $term,
            'shown_at' => '2026-08-23T23:30:00+03:00',
        ]]])
        ->assertOk()
        ->assertJsonPath('data.exposures', 1);

    expect(storedUtc('term_exposures', 'shown_at', $user->id))->toBe('2026-08-23 20:30:00');
});

it('credits a late-evening answer to the learner\'s day, not to UTC\'s', function () {
    [$user, $token] = qaf3Learner();
    $term = seedWordFor($user, 'apple', 'яблоко');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(),
            'term_id' => $term,
            'exercise_mode' => 'typing',
            'response' => 'apple',
            'answered_at' => '2026-08-23T23:30:00+03:00',
            'client_seq' => 1,
        ]]])
        ->assertOk();

    // Read the activity calendar the morning after, in the learner's zone. The stats reader buckets
    // by `answered_at AT TIME ZONE <зона ученика>` — correct arithmetic over a wrong column is still
    // the wrong day, which is why this asserts the day and not just the raw value above.
    $stats = app(StatsReader::class)->read(
        UserId::fromString($user->id),
        new DateTimeImmutable('2026-08-24T09:00:00+03:00'),
        new DateTimeZone('Europe/Bucharest'),
        newGoal: 20,
        newToday: 0,
    );

    expect($stats->activeDays)->toBe(['2026-08-23'])
        // …and «сегодня» is genuinely empty: nothing was answered on the 24th.
        ->and($stats->reviewsToday)->toBe(0);
});

<?php

declare(strict_types=1);

use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Learning\Application\Port\LearnerTimezoneWriter;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * QAF-3 — a learner who MOVES. The profile used to learn its timezone only at Google sign-in and on
 * a profile edit, so somebody who emigrated without re-logging in kept a whole calendar — «сегодня»,
 * the streak, the day a card is floored to — pinned to the country they left.
 *
 * The sync carries the device's zone now. It is an OPTIONAL query parameter and nothing else about
 * the endpoint moves: a request without it is the request that was always valid, and the response is
 * byte-identical either way.
 */

/** @return array{0: User, 1: string} */
function movingLearner(string $tz = 'Europe/Bucharest'): array
{
    $user = User::factory()->create();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 20, 'timezone' => $tz]);

    return [$user, $user->createToken('device')->plainTextToken];
}

function storedZone(User $user): string
{
    return (string) DB::table('profiles')->where('user_id', $user->id)->value('timezone');
}

it('adopts the zone the device reports', function () {
    [$user, $token] = movingLearner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/sync?timezone=Europe/Berlin')
        ->assertOk()
        ->assertJsonStructure(['data' => ['server_time', 'has_more', 'changes']]);

    expect(storedZone($user))->toBe('Europe/Berlin');
});

it('leaves the zone alone when the client sends none', function () {
    [$user, $token] = movingLearner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/sync')
        ->assertOk();

    expect(storedZone($user))->toBe('Europe/Bucharest');
});

it('ignores a zone it cannot recognise, and syncs anyway', function (string $zone) {
    [$user, $token] = movingLearner();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/sync?timezone=' . urlencode($zone))
        ->assertOk()
        ->assertJsonStructure(['data' => ['server_time', 'has_more', 'changes']]);

    expect(storedZone($user))->toBe('Europe/Bucharest');
})->with([
    'nonsense' => 'Mars/Phobos',
    'an offset, not a zone' => '+03:00',
    'empty' => '',
    'a sentence' => 'Europe/Berlin; drop table',
]);

it('does not reschedule what is already due when the learner moves', function () {
    [$user, $token] = movingLearner();
    $term = seedWordFor($user, 'apple', 'яблоко');

    // A card already planned for midnight in Bucharest (21:00Z the evening before).
    DB::table('user_term_progress')
        ->where('user_id', $user->id)->where('term_id', $term)
        ->update(['due_at' => '2026-08-23 21:00:00+00', 'state' => 'review', 'interval_days' => 4]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/sync?timezone=America/New_York')
        ->assertOk();

    // Deliberate: the move changes where the NEXT answer is planned, not where the previous one was.
    // Re-flooring a whole pool into the new zone on a flight would move dates nobody asked to move,
    // and «which day did the learner mean» has no honest answer for a card scheduled before the trip.
    $due = new DateTimeImmutable((string) DB::table('user_term_progress')
        ->where('user_id', $user->id)->where('term_id', $term)->value('due_at'));

    expect(storedZone($user))->toBe('America/New_York')
        ->and($due->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'))->toBe('2026-08-23 21:00:00');
});

it('writes nothing when the reported zone is the one already on file', function () {
    [, $token] = movingLearner();

    // Every launch of every device carries the zone, and almost every one of them reports the same
    // one — so «no change» has to mean no write, not a write that happens to change nothing.
    $writes = [];
    app()->instance(LearnerTimezoneWriter::class, new class($writes) implements LearnerTimezoneWriter
    {
        /** @param list<string> $writes */
        public function __construct(public array &$writes) {}

        public function updateTimezone(UserId $user, string $ianaZone): void
        {
            $this->writes[] = $ianaZone;
        }
    });

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/sync?timezone=Europe/Bucharest')
        ->assertOk();
    expect($writes)->toBe([]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/sync?timezone=Europe/Berlin')
        ->assertOk();
    expect($writes)->toBe(['Europe/Berlin']);
});

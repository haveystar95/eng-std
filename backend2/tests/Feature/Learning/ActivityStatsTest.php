<?php

declare(strict_types=1);

use App\Modules\Collections\Application\Command\AddWordToCollection;
use App\Modules\Collections\Application\Command\AddWordToCollectionHandler;
use App\Modules\Collections\Application\Command\CreateCustomCollection;
use App\Modules\Collections\Application\Command\CreateCustomCollectionHandler;
use App\Modules\Identity\Infrastructure\Eloquent\Profile;
use App\Modules\Identity\Infrastructure\Eloquent\User;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @return array{0: User, 1: string} user + device token, with the given timezone on the profile. */
function activityUser(?string $tz = null): array
{
    $user = User::factory()->create();
    Profile::create(['user_id' => $user->id, 'daily_goal' => 20, 'timezone' => $tz]);

    return [$user, $user->createToken('device')->plainTextToken];
}

function activityTerm(User $user, string $text = 'apple'): string
{
    $actor = UserId::fromString($user->id);
    $cid = app(CreateCustomCollectionHandler::class)(new CreateCustomCollection(
        $actor, 'Deck', new LanguageCode('ru'), new LanguageCode('en'),
    ));

    return app(AddWordToCollectionHandler::class)(new AddWordToCollection($cid, $actor, $text, 'x'))->value;
}

function answerAt(object $ctx, string $token, string $termId, string $answeredAtIso, int $seq, bool $practice = false): void
{
    $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/reviews/batch', ['reviews' => [[
            'id' => Ulid::generate(), 'term_id' => $termId, 'exercise_mode' => 'typing', 'response' => 'apple',
            'answered_at' => $answeredAtIso, 'is_practice' => $practice, 'client_seq' => $seq,
        ]]])->assertOk();
}

/** @return array<int, string> the /stats active_days list */
function activeDays(object $ctx, string $token): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/stats')->assertOk()->json('data.active_days');
}

it('reports activity from a mix of study and practice answers', function () {
    [$user, $token] = activityUser(); // UTC
    $term = activityTerm($user);

    $d1 = now()->subDays(3)->format('Y-m-d');
    $d2 = now()->subDays(1)->format('Y-m-d');
    answerAt($this, $token, $term, now()->subDays(3)->toIso8601String(), 1, practice: false); // study
    answerAt($this, $token, $term, now()->subDays(1)->toIso8601String(), 2, practice: true);  // practice

    expect(activeDays($this, $token))->toContain($d1)->toContain($d2);
});

it('buckets a review by the user timezone, not UTC (a 23:50 local answer is that local day)', function () {
    // America/New_York is UTC-4 in August: 23:50 local on the 10th is 03:50 UTC on the 11th.
    [$user, $token] = activityUser('America/New_York');
    $term = activityTerm($user);

    $localEvening = CarbonImmutable::parse('2026-08-10 23:50', 'America/New_York');
    answerAt($this, $token, $term, $localEvening->toIso8601String(), 1);

    $days = activeDays($this, $token);
    expect($days)->toContain('2026-08-10')   // the LOCAL day it was answered
        ->not->toContain('2026-08-11');       // NOT the UTC day
});

it('restores the calendar and streak after a relogin (server is the source of truth)', function () {
    [$user, $token] = activityUser();
    $term = activityTerm($user);
    answerAt($this, $token, $term, now()->subDays(2)->toIso8601String(), 1);
    $before = activeDays($this, $token);

    // Relogin/reinstall: brand-new device token, no client state carried over.
    $freshToken = $user->createToken('new-device')->plainTextToken;

    expect(activeDays($this, $freshToken))->toBe($before);
    expect($before)->toContain(now()->subDays(2)->format('Y-m-d'));
});

it('credits a practice day retroactively when its batch lands late', function () {
    [$user, $token] = activityUser();
    $term = activityTerm($user);

    // A practice session from two days ago whose queued batch only reaches the server now.
    $twoDaysAgo = now()->subDays(2)->format('Y-m-d');
    expect(activeDays($this, $token))->not->toContain($twoDaysAgo);

    answerAt($this, $token, $term, now()->subDays(2)->toIso8601String(), 1, practice: true);

    expect(activeDays($this, $token))->toContain($twoDaysAgo);
});

it('keeps a streak alive through a day whose only activity was practice', function () {
    [$user, $token] = activityUser(); // UTC
    $term = activityTerm($user);

    // Three consecutive days; the middle day is practice-only. Activity = any answer, so streak = 3.
    answerAt($this, $token, $term, now()->subDays(2)->toIso8601String(), 1, practice: false);
    answerAt($this, $token, $term, now()->subDays(1)->toIso8601String(), 2, practice: true);
    answerAt($this, $token, $term, now()->toIso8601String(), 3, practice: false);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/stats')
        ->assertOk()
        ->assertJsonPath('data.streak_days', 3);
});

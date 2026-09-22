<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
});

/**
 * «ПОВТОРЕНИЕ» — THE REVIEW DAY'S OWN STAGE (наряд BACK-TAILS-2 §3). A review day dealt its cards under `speak` and read
 * «Говорю сам» on a day that has no such stage; its stage is `repetition` everywhere a stage is named — the cards, the
 * window, the route, the room, the close of a stage — and the days dealt before are moved by a data migration.
 */

/** @return array{token: string, id: string} a plan of five days (scene, scene, review, scene, rehearsal), days 1–2 walked, day 3 open today */
function repReviewDay(object $ctx): array
{
    [, $token] = planLearner();
    $id = planCreate($ctx, $token)['id'];
    $ctx->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    planWalkDay($ctx, $token, $id, 1);
    planShiftDay($id);
    planWalkDay($ctx, $token, $id, 2);
    planShiftDay($id);

    return ['token' => $token, 'id' => $id];
}

// Canon (§3): «ввести id repetition: DayStages, бюджеты, plan_stage_passages, close, highlights — всё, что читает id этапа».
// CATCHES a review card dealt under `speak`, a window or a route that still says «Говорю сам» on a review, a stage that
// cannot be closed by its name, «Сказал сам» that forgets the review's lines, and «Ещё раз» lost on a passed review.
it('deals a review day\'s own cards under «Повторение» and names the stage so everywhere', function () {
    ['token' => $token, 'id' => $id] = repReviewDay($this);
    $read = fn (): array => $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/3")->assertOk()->json('data');

    expect($read()['day']['type'])->toBe('review')
        ->and(array_column($read()['window']['stages'], 'stage'))->not->toContain('speak');

    $cards = planOpenDay($this, $token, $id, 3)['cards'];
    $stages = array_values(array_unique(array_column($cards, 'stage')));
    expect($stages)->toContain('repetition')->not->toContain('speak')
        ->and(array_unique(array_column(array_filter($cards, static fn (array $c): bool => $c['stage'] === 'repetition'), 'kind')))
        ->each->toBeIn(['speak_answer', 'speak_echo', 'speak_retell']);

    $room = $read();
    $window = array_column($room['window']['stages'], 'stage');
    expect($window)->toContain('repetition')->not->toContain('speak')
        ->and($window[count($window) - 1])->toBe('conversation')
        ->and(array_column($room['day']['stages'], 'stage'))->toContain('repetition')->not->toContain('speak')
        ->and(array_column(array_filter($room['stages'], static fn (array $s): bool => $s['total'] > 0), 'stage'))->toContain('repetition');

    // Walked: every card answered, the stage closed by ITS name, the talk held, the day closed.
    foreach ($cards as $card) {
        planAnswer($this, $token, $id, 3, $card['id'], planWalkResult($card['kind']));
    }
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/3/stages/repetition/close")->assertOk();
    planTalkThrough($this, $token, $id, 3);
    $closed = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/3/close")->assertOk()->json('data');

    $said = count(array_filter($cards, static fn (array $c): bool => $c['stage'] === 'repetition'));
    expect($closed['window']['highlights'][0])->toContain(" из {$said}")
        // «Ещё раз» is the stage's row now (наряд FIX-3 §8): «Повторение» may be walked again on the passed day, and the day
        // itself has no «again» of its own.
        ->and(array_column($closed['window']['stages'], 'again', 'stage')['repetition'])->toBeTrue()
        ->and($closed['window']['allowed_action'])->toBeNull();
});

// Canon (§3): «уже розданные дни повторения: миграция данных переименовывает speak → repetition только у дней типа
// „повторение“, обратимая». CATCHES a migration that moves a scene day's «Говорю сам», one that moves nothing, and a way
// back that does not bring the review's cards home.
it('moves the cards review days were dealt with from speak to repetition, and back, and nothing else', function () {
    ['token' => $token, 'id' => $id] = repReviewDay($this);
    planOpenDay($this, $token, $id, 3);
    $days = DB::table('plan_days')->where('plan_id', $id)->pluck('id', 'number');
    // The review as a day dealt before the наряд had it: its own cards under `speak`.
    DB::table('day_cards')->where('day_id', $days[3])->where('stage', 'repetition')->update(['stage' => 'speak']);
    $count = static fn (int $day, string $stage): int => DB::table('day_cards')->where('day_id', $days[$day])->where('stage', $stage)->count();
    [$reviewSpeak, $sceneSpeak] = [$count(3, 'speak'), $count(1, 'speak')];
    expect($reviewSpeak)->toBeGreaterThan(0)->and($sceneSpeak)->toBeGreaterThan(0);

    $migration = require base_path('app/Modules/Plan/Infrastructure/Migration/2026_09_22_100000_add_repetition_stage_of_review_days.php');
    $migration->up();
    expect($count(3, 'speak'))->toBe(0)
        ->and($count(3, 'repetition'))->toBe($reviewSpeak)
        ->and($count(1, 'speak'))->toBe($sceneSpeak)
        ->and($count(1, 'repetition'))->toBe(0);

    $migration->down();
    expect($count(3, 'speak'))->toBe($reviewSpeak)
        ->and($count(3, 'repetition'))->toBe(0)
        ->and($count(1, 'speak'))->toBe($sceneSpeak);

    $migration->up();
    expect($count(3, 'repetition'))->toBe($reviewSpeak);
});

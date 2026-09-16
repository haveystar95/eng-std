<?php

declare(strict_types=1);

/**
 * A UNIT COMES BACK ONCE (наряд SESSION-1a, хвост): on the nearest following day of whatever type; a review day takes
 * the units of its two scene days that have come back nowhere yet, and the scene after it takes none of theirs; a card
 * that is itself a return never sends its unit back again.
 *
 * The chain is walked over HTTP on a five-day plan — scene 1 → scene 2 → review 3 → scene 4 → rehearsal 5 — with one
 * word failed twice on each scene day, and the returned card of day 2 failed twice as well.
 */

use Illuminate\Routing\Middleware\ThrottleRequests;

// Four walked days are some three hundred answers: past the API's 120 a minute, as for every walk over days here.
beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/** @return list<array<string, mixed>> the day's cards, dealt on the first call */
function s1rCards(object $ctx, string $token, string $id, int $number): array
{
    return planOpenDay($ctx, $token, $id, $number)['cards'];
}

/**
 * Fail a card twice — the card and the copy its first failure deals — and give back the second reply.
 *
 * @param  array<string, mixed>  $card
 * @return array<string, mixed>
 */
function s1rFailTwice(object $ctx, string $token, string $id, int $number, array $card): array
{
    $first = planAnswer($ctx, $token, $id, $number, $card['id'], 'failed');
    expect($first['requeued'])->not->toBeNull();

    return planAnswer($ctx, $token, $id, $number, $first['requeued']['id'], 'failed', 2);
}

/**
 * The returned cards of a day, as «scene:kind:ref» — what «the unit came back» is counted by.
 *
 * @param  list<array<string, mixed>>  $cards
 * @return list<string>
 */
function s1rReturned(array $cards): array
{
    $out = [];
    foreach ($cards as $card) {
        if ($card['source'] === 'returned' && $card['retry_of'] === null) {
            $out[] = $card['payload']['scene_id'].':'.$card['unit']['kind'].':'.$card['unit']['ref'];
        }
    }

    return $out;
}

/**
 * A word card of today's own material that deals a copy on a failure.
 *
 * @param  list<array<string, mixed>>  $cards
 * @return array<string, mixed>
 */
function s1rTodaysWordChoice(array $cards, string $kind): array
{
    return array_values(array_filter($cards, static fn (array $c): bool => $c['kind'] === $kind && $c['source'] === 'today'))[0];
}

it('brings a unit back once, on the nearest following day: scene → scene → review → scene', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token)['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();

    // Day 1 (scene): a word failed twice — it comes back tomorrow, on day 2.
    $day1 = s1rCards($this, $token, $id, 1);
    $u1 = s1rTodaysWordChoice($day1, 'word_assemble');
    $u1Key = $u1['payload']['scene_id'].':word:'.$u1['unit']['ref'];
    expect(s1rFailTwice($this, $token, $id, 1, $u1)['unit'])->toMatchArray(['returns_tomorrow' => true, 'returns_day' => 2]);
    planWalkDay($this, $token, $id, 1);
    planShiftDay($id);

    // Day 2 (scene): the unit of day 1 is back, once. It fails twice again — a return never sends its unit back again —
    // and a word of day 2's own scene fails twice: that one comes back on day 3, the review.
    $day2 = s1rCards($this, $token, $id, 2);
    expect(s1rReturned($day2))->toBe([$u1Key]);
    $back = array_values(array_filter($day2, static fn (array $c): bool => $c['source'] === 'returned'))[0];
    expect(s1rFailTwice($this, $token, $id, 2, $back)['unit'])->toMatchArray(['returns_tomorrow' => false, 'returns_day' => null]);
    $u2 = s1rTodaysWordChoice($day2, 'word_assemble');
    $u2Key = $u2['payload']['scene_id'].':word:'.$u2['unit']['ref'];
    expect(s1rFailTwice($this, $token, $id, 2, $u2)['unit'])->toMatchArray(['returns_tomorrow' => true, 'returns_day' => 3]);
    planWalkDay($this, $token, $id, 2);
    planShiftDay($id);

    // Day 3 (review): the two scene days' units that have come back nowhere yet — the unit of day 2 only; the unit of
    // day 1 came back on day 2 and is not dealt again.
    $day3 = s1rCards($this, $token, $id, 3);
    expect(s1rReturned($day3))->toBe([$u2Key]);
    planWalkDay($this, $token, $id, 3);
    planShiftDay($id);

    // Day 4 (scene after the review): nothing of the scenes the review has covered comes back again.
    $day4 = s1rCards($this, $token, $id, 4);
    expect(s1rReturned($day4))->toBe([]);

    // Across the chain every unit came back exactly once.
    $all = [...s1rReturned($day2), ...s1rReturned($day3), ...s1rReturned($day4)];
    expect(array_count_values($all))->toBe([$u1Key => 1, $u2Key => 1]);
});

it('brings yesterday\'s unit back on the rehearsal too — the nearest following day of any type', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token, ['days_total' => 3])['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();

    planWalkDay($this, $token, $id, 1);
    planShiftDay($id);

    // Day 2 (the last scene): a word failed twice names day 3, the rehearsal, as its day.
    $day2 = s1rCards($this, $token, $id, 2);
    $unit = s1rTodaysWordChoice($day2, 'word_assemble');
    expect(s1rFailTwice($this, $token, $id, 2, $unit)['unit'])->toMatchArray(['returns_tomorrow' => true, 'returns_day' => 3]);
    planWalkDay($this, $token, $id, 2);
    planShiftDay($id);

    $day3 = s1rCards($this, $token, $id, 3);
    expect(s1rReturned($day3))->toBe([$unit['payload']['scene_id'].':word:'.$unit['unit']['ref']])
        ->and(array_values(array_unique(array_map(static fn (array $c): string => $c['stage'], $day3))))->toBe(['words', 'speak']);
});

it('deals no copy of a wrong listening answer — the review shows it — and the day\'s listening never returns', function () {
    [, $token] = planLearner();
    $id = planCreate($this, $token)['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();

    $cards = s1rCards($this, $token, $id, 1);
    $question = array_values(array_filter($cards, static fn (array $c): bool => $c['kind'] === 'listen_question'))[0];
    $reply = planAnswer($this, $token, $id, 1, $question['id'], 'failed');

    expect($reply['card']['result'])->toBe('failed')
        ->and($reply['requeued'])->toBeNull()
        ->and($reply['card']['returns'])->toBeFalse()
        ->and($reply['unit'])->toMatchArray(['kind' => 'day', 'returns_tomorrow' => false, 'returns_day' => null]);
});

<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Service\Clock;
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
 * «ПОВТОРИТЬ РАЗГОВОР» AND THE DAY'S MINUTES (наряд BACK-TAILS-2 §§7–8): a walked talk may be held again from the window
 * of the day — being walked or passed — as a replay that touches neither the day's state nor its result, at most
 * `plan.conversation.replays_per_day` times a calendar day of the learner; and the day's `minutes_spent` is its cards
 * plus the talk that walked the stage — from the moment it walks it, never a replay.
 */

/** A clock the test moves by hand, bound before the first request so every handler reads it. */
function rpClock(string $at): object
{
    $clock = new class($at) implements Clock
    {
        public DateTimeImmutable $at;

        public function __construct(string $at)
        {
            $this->at = new DateTimeImmutable($at);
        }

        public function now(): DateTimeImmutable
        {
            return $this->at;
        }

        public function move(string $by): void
        {
            $this->at = $this->at->modify($by);
        }
    };
    app()->instance(Clock::class, $clock);

    return $clock;
}

/** @return array{token: string, id: string} a started plan of two days, day 1 opened */
function rpDay(object $ctx): array
{
    [, $token] = planLearner();
    $id = planCreate($ctx, $token, ['days_total' => 2])['id'];
    $ctx->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    planOpenDay($ctx, $token, $id, 1);

    return ['token' => $token, 'id' => $id];
}

/** Every card of the day answered at the clock's present moment. */
function rpAnswerAll(object $ctx, string $token, string $id, int $number = 1): void
{
    foreach (planOpenDay($ctx, $token, $id, $number)['cards'] as $card) {
        planAnswer($ctx, $token, $id, $number, $card['id'], planWalkResult($card['kind']));
    }
}

/** A talk held to its end, a move every 20 seconds of the clock. @return array<string, mixed> */
function rpTalk(object $ctx, object $clock, string $token, string $id, int $number = 1, bool $again = false): array
{
    $talk = $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$id}/days/{$number}/conversation", ['again' => $again])->assertOk()->json('data');
    while ($talk['state'] !== 'ended') {
        $clock->move('+20 seconds');
        $talk = $ctx->withHeader('Authorization', "Bearer {$token}")
            ->postJson("/api/v1/plans/{$id}/conversation/{$talk['id']}/turn", ['kind' => 'said', 'heard' => 'It started three days ago.'])
            ->assertOk()->json('data');
    }

    return $talk;
}

/** @return array<string, mixed> the day as the room reads it */
function rpRoom(object $ctx, string $token, string $id, int $number = 1): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/{$number}")->assertOk()->json('data');
}

// Canon (§7): «window.talk_again = true, когда этап conversation дня пройден по журналу и день показывает окно; POST …/
// conversation на пройденном дне — replay: не трогает „пройден“ и состояние дня, targets те же, журнал и траты пишутся».
// CATCHES a «Повторить разговор» offered before the talk is walked, a passed (closed) day that refuses the replay with 409
// `plan_day_not_open`, a replay that reopens the closed day or rewrites its minutes, other targets, and a replay not
// journaled.
it('offers the walked talk again from the window, and holds it on a passed day as a replay that changes nothing', function () {
    $clock = rpClock('2026-09-22T09:00:00Z');
    ['token' => $token, 'id' => $id] = rpDay($this);

    expect(rpRoom($this, $token, $id)['window']['talk_again'])->toBeFalse();
    rpAnswerAll($this, $token, $id);
    expect(rpRoom($this, $token, $id)['window']['talk_again'])->toBeFalse();

    $walked = rpTalk($this, $clock, $token, $id);
    // Walked, the day still open: «Ещё раз» is there — the talk's stage is walked by the journal.
    expect(rpRoom($this, $token, $id)['window']['talk_again'])->toBeTrue();

    $closed = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/close")->assertOk()->json('data');
    $minutes = $closed['metrics']['minutes_spent'];
    expect($closed['window']['talk_again'])->toBeTrue()
        // The cards' «Ещё раз» of a passed day is what it was.
        ->and($closed['window']['allowed_action'])->toBe('again');

    $clock->move('+1 hour');
    $replay = rpTalk($this, $clock, $token, $id);
    $room = rpRoom($this, $token, $id);

    expect($replay['replay'])->toBeTrue()
        ->and($replay['id'])->not->toBe($walked['id'])
        ->and(array_column($replay['targets'], 'ref'))->toBe(array_column($walked['targets'], 'ref'))
        ->and($replay['summary']['returns_tomorrow'])->toBeFalse()
        ->and($room['day']['status'])->toBe('closed')
        ->and($room['metrics']['minutes_spent'])->toBe($minutes)
        ->and($room['window']['talk_again'])->toBeTrue()
        ->and(DB::table('plan_stage_passages')->where('day_id', $room['day']['id'])->value('conversation_id'))->toBe($walked['id'])
        // Its lines and their bill are written like any talk's.
        ->and(DB::table('conversation_turns')->where('conversation_id', $replay['id'])->count())->toBe(count($replay['turns']))
        ->and(DB::table('conversation_turns')->where('conversation_id', $replay['id'])->whereNotNull('model')->count())->toBeGreaterThan(0);
});

// Canon (§7): «кап plan.conversation.replays_per_day = 3 повтора на день плана в календарные сутки ученика; сверх — 409
// plan_conversation_replay_limit с retry_after_utc». CATCHES a fourth replay bought, a limit counted over the walked talk
// itself or over the days of the plan together, a retry time that is not the learner's next midnight, and a limit that
// does not open again then.
it('holds a walked talk again three times a calendar day of the learner, and says when the fourth may be', function () {
    $clock = rpClock('2026-09-22T09:00:00Z');
    [, $token] = planLearner('Europe/Kyiv');
    $id = planCreate($this, $token, ['days_total' => 2])['id'];
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    rpAnswerAll($this, $token, $id);
    rpTalk($this, $clock, $token, $id);

    foreach ([1, 2, 3] as $n) {
        $clock->move('+10 minutes');
        expect(rpTalk($this, $clock, $token, $id)['replay'])->toBeTrue("replay {$n}");
    }
    $clock->move('+10 minutes');
    $refused = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/conversation");
    $refused->assertStatus(409)
        ->assertJsonPath('code', 'plan_conversation_replay_limit')
        // Kyiv is UTC+3 on 22.09: the learner's next midnight is 21:00 UTC.
        ->assertJsonPath('meta.retry_after_utc', '2026-09-22T21:00:00Z')
        ->assertJsonPath('meta.replays_per_day', 3);
    expect(DB::table('conversations')->where('plan_id', $id)->count())->toBe(4);

    // The learner's midnight: the replay opens again.
    $clock->move('+12 hours');
    expect(rpTalk($this, $clock, $token, $id)['replay'])->toBeTrue()
        ->and(DB::table('conversations')->where('plan_id', $id)->count())->toBe(5);
});

// Canon (§8): «minutes_spent дня = минуты карточек, как сейчас, + summary.minutes первого разговора, прошедшего этап;
// повторы не входят». CATCHES a talk added only at the close (the summary 30-7 printed «6 минут» of a day the close then
// called 9, CLIENT-CONV-1b §5 п. 4), a replay added to the day, and minutes of the talk by the wall clock.
it('counts the day\'s minutes as its cards and the talk that walked it, from the moment it walks, and never a replay', function () {
    $clock = rpClock('2026-09-22T09:00:00Z');
    ['token' => $token, 'id' => $id] = rpDay($this);
    rpAnswerAll($this, $token, $id);
    $cards = rpRoom($this, $token, $id)['metrics']['minutes_spent'];
    expect($cards)->toBe(1);

    // Four moves 20 s apart after the opening line: 80 s of talk — 2 minutes by its own summary.
    $walked = rpTalk($this, $clock, $token, $id);
    expect($walked['summary']['minutes'])->toBe(2);
    $room = rpRoom($this, $token, $id);
    expect($room['day']['status'])->toBe('in_progress')
        ->and($room['metrics']['minutes_spent'])->toBe($cards + 2)
        ->and(planRead($this, $token, $id)['days'][0]['minutes_spent'])->toBe($cards + 2);

    // A replay of five hours and more: the day is not a minute longer for it.
    $clock->move('+5 hours');
    rpTalk($this, $clock, $token, $id, again: true);
    expect(rpRoom($this, $token, $id)['metrics']['minutes_spent'])->toBe($cards + 2);

    $closed = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/close")->assertOk()->json('data');
    expect($closed['metrics']['minutes_spent'])->toBe($cards + 2)
        ->and($closed['window']['day']['minutes_spent'])->toBe($cards + 2);
});

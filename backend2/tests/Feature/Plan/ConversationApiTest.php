<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Infrastructure\Adapter\FakeSpeechSynthesizer;
use App\Modules\Plan\Application\Dto\ConversationAgentRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Shared\Domain\Service\Clock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * THE TALK WITH THE AGENT OVER HTTP (наряд CONV-1, кадры 37-5…37-12): the sixth stage of a day — started, walked turn
 * by turn, ended by the role, and read back after a dropped connection.
 *
 * Everything here runs on `FakePlanModel`: the role is played deterministically, so the suite buys nothing and the
 * rules — whose move it is, what a rescue costs, who counts the phrases of the plan, when the talk ends — are checked
 * against the server and not against a vendor's mood.
 */

// Every test starts from an empty database: the counters and the journal of stages are read by what they hold.
uses(RefreshDatabase::class);

// A day walked through is some two hundred answers, past the API's 120 a minute.
beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/** A plan started, its day 1 open and its cards dealt. @return array{0: string, 1: string} token and plan id */
function convDay(object $ctx, int $days = 2): array
{
    [, $token] = planLearner();
    $id = planCreate($ctx, $token, ['days_total' => $days])['id'];
    $ctx->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    planOpenDay($ctx, $token, $id, 1);

    return [$token, $id];
}

/** @return array<string, mixed> */
function convStart(object $ctx, string $token, string $id, int $number = 1, array $body = []): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$id}/days/{$number}/conversation", $body)->assertOk()->json('data');
}

/** @return array<string, mixed> */
function convTurn(object $ctx, string $token, string $id, string $talkId, string $kind = 'said', string $heard = 'It started three days ago.'): array
{
    return $ctx->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$id}/conversation/{$talkId}/turn", ['kind' => $kind, 'heard' => $heard])
        ->assertOk()->json('data');
}

/**
 * The role, played by a closure over the fake model — the one place a test decides what the agent answers.
 *
 * BIND IT BEFORE THE FIRST REQUEST TO THE ROUTE IT SHOULD ANSWER. Laravel caches a controller on its Route object, so
 * a controller already built for `…/turn` keeps the port it was built with, whatever is bound afterwards. A test that
 * walks a day first (`planWalkDay` holds a talk of its own) must bind before that walk.
 */
function convAgentSays(callable $reply): FakePlanModel
{
    $fake = new FakePlanModel(lesson: planCleanLesson(...), conversation: $reply);
    app()->instance(PlanModelPort::class, $fake);

    return $fake;
}

/**
 * Canon (наряд CONV-1, п. 1): «обычный день — шесть этапов; „день пройден" = шесть насквозь». Catches a day closed on
 * five stages with the talk never held, and a talk that closes the day by being merely started.
 */
it('holds the day shut until the talk is over, and closes it on six stages', function () {
    [$token, $id] = convDay($this);
    $cards = planOpenDay($this, $token, $id, 1)['cards'];
    foreach ($cards as $card) {
        planAnswer($this, $token, $id, 1, $card['id'], planWalkResult($card['kind']));
    }

    // Every card answered — and the day still refuses to close: its sixth stage has not been walked.
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/close")
        ->assertStatus(409)->assertJsonPath('code', 'plan_stage_incomplete')->assertJsonPath('meta.stage', 'conversation');

    $talk = convStart($this, $token, $id);
    expect($talk['state'])->toBe('your_turn');

    // Started is not walked: the stage closes when the TALK is over, not when it is open.
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/stages/conversation/close")
        ->assertStatus(409)->assertJsonPath('code', 'plan_stage_incomplete');

    $ended = planTalkThrough($this, $token, $id, 1);
    expect($ended['state'])->toBe('ended');

    $closed = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/close")
        ->assertOk()->json('data');
    expect($closed['day']['status'])->toBe('closed')
        ->and(array_column($closed['window']['stages'], 'state'))->toBe(['done', 'done', 'done', 'done', 'done', 'done']);
});

/**
 * Canon (п. 3): the role opens, and the learner answers what it said. Catches a talk that starts on an empty ribbon
 * waiting for the learner to speak first, and a second `POST …/conversation` buying a second opening line.
 */
it('opens with the role\'s own line, and carries on the same talk when asked again', function () {
    [$token, $id] = convDay($this);

    $talk = convStart($this, $token, $id);
    expect($talk['turns'])->toHaveCount(1)
        ->and($talk['turns'][0])->toMatchArray(['index' => 1, 'speaker' => 'partner', 'kind' => 'agent'])
        ->and($talk['turns'][0]['text_target'])->not->toBeEmpty()
        ->and($talk['turns'][0]['text_native'])->not->toBeEmpty()
        ->and($talk['type'])->toBe('day')
        ->and($talk['day'])->toBe(1)
        ->and($talk['turns_left'])->toBe(4)
        ->and($talk['hints'])->toMatchArray(['enabled' => true, 'delay_ms' => 5000])
        ->and($talk['partner']['role_native'])->not->toBeEmpty()
        ->and($talk['scenes'][0]['state'])->toBe('current')
        ->and($talk['summary'])->toBeNull()
        ->and($talk['replay'])->toBeFalse()
        // The intention offered is the learner's OWN line of this scene, in their language — the lesson's, not the
        // model's (DECISIONS п. 359) — and as a CLAUSE: the client prints «Скажи, что …» around it, and «Скажи, что У
        // него болит поясница.» was the chip of the first live run (наряд CONV-2, п. 11).
        ->and($talk['hints']['native'])->toBe('у него болит поясница');

    // Asked again: the same talk, not a second one — a phone coming back from the background carries on.
    $again = convStart($this, $token, $id);
    expect($again['id'])->toBe($talk['id'])->and($again['turns'])->toHaveCount(1);

    convTurn($this, $token, $id, $talk['id']);
    $carried = convStart($this, $token, $id);
    expect($carried['id'])->toBe($talk['id'])->and(count($carried['turns']))->toBe(3);
});

// «Ещё раз» (кадр 37-12) is a NEW talk and the old one is closed as `replayed`: what was said stays said.
it('starts a new talk on «Ещё раз» and closes the old one as replayed', function () {
    [$token, $id] = convDay($this);
    $first = convStart($this, $token, $id);
    convTurn($this, $token, $id, $first['id']);

    $second = convStart($this, $token, $id, body: ['again' => true]);

    expect($second['id'])->not->toBe($first['id'])
        ->and($second['turns'])->toHaveCount(1)
        ->and(DB::table('conversations')->where('id', $first['id'])->value('ended_reason'))->toBe('replayed')
        ->and(DB::table('conversations')->where('id', $first['id'])->value('state'))->toBe('ended')
        // The first talk's lines are still there: a closed journal is not a deleted one.
        ->and(DB::table('conversation_turns')->where('conversation_id', $first['id'])->count())->toBe(3);
});

/**
 * Canon (п. 3, кадр 37-7): «Не понял» asks the role to repeat itself — no judgement is written for it, it counts as a
 * rescue, and it does NOT spend one of the day's moves («переспросы всегда нейтральны»). Catches a rescue that eats a
 * turn and a rescue counted as a line the learner said.
 */
it('asks the role to repeat itself without spending a move of the scene', function () {
    [$token, $id] = convDay($this);
    $talk = convStart($this, $token, $id);

    $after = convTurn($this, $token, $id, $talk['id'], 'rescue', '');
    expect($after['turns_left'])->toBe(4)
        // The learner's own bubble says what a learner says when they did not catch it — in the language of the talk,
        // from its pack (наряд CONV-2, п. 4а; кадр 37-7 draws «Sorry?»). It is not a line of their own: no phrases.
        ->and($after['turns'][1])->toMatchArray(['speaker' => 'learner', 'kind' => 'rescue', 'text_target' => 'Sorry?', 'phrases_used' => []])
        ->and($after['turns'][2]['understood'])->toBeNull()
        // …and it is not «сказал сам».
        ->and(DB::table('conversation_turns')->where('conversation_id', $talk['id'])->where('kind', 'rescue')->value('text_target'))->toBe('Sorry?');

    $said = convTurn($this, $token, $id, $talk['id']);
    expect($said['turns_left'])->toBe(3);
});

/**
 * Canon (п. 4): «фразы плана — SpeechMatch, режим free». The COUNT is the code's: the model's own `phrases_used` is
 * stored but never scored. Catches a summary that trusts the model — the day's frame is heard because the frame's own
 * words were said, not because the agent felt generous.
 */
it('counts the phrases of the plan by the server\'s own rule, not by the model\'s answer', function () {
    [$token, $id] = convDay($this);
    // The role claims every phrase sounded on every turn; the code hears only what was actually said.
    convAgentSays(static function (ConversationAgentRequest $request): array {
        $payload = FakePlanModel::conversationPayload($request);
        $payload['phrases_used'] = $request->phraseIds();

        return $payload;
    });

    $talk = convStart($this, $token, $id);
    $after = convTurn($this, $token, $id, $talk['id'], 'said', 'It started three days ago.');
    $learner = $after['turns'][1];

    expect($learner['phrases_used'])->toHaveCount(1)
        ->and($learner['phrases_used'][0]['ref'])->toBe('p2')
        ->and($after['turns'][2]['phrases_used'])->toBe([]);

    // A line that says none of the day's frames is counted as none of them.
    $nothing = convTurn($this, $token, $id, $talk['id'], 'said', 'Hello, nice weather today.');
    expect($nothing['turns'][3]['phrases_used'])->toBe([]);
});

/**
 * Canon (п. 3): a second move sent while the first is still being answered is refused — it does not buy a second
 * reply to one line. The journal is append-only and numbered by the server, so there is one place this can be caught.
 */
it('refuses a move that is not the learner\'s', function () {
    [$token, $id] = convDay($this);
    $talk = convStart($this, $token, $id);
    $ended = planTalkThrough($this, $token, $id, 1);

    expect($ended['state'])->toBe('ended');
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$id}/conversation/{$talk['id']}/turn", ['kind' => 'said', 'heard' => 'Hello.'])
        ->assertStatus(409)->assertJsonPath('code', 'plan_conversation_ended');

    // Someone else's talk reads as one that never existed.
    [, $other] = planLearner();
    app('auth')->forgetGuards(); // the request guard remembers the last bearer within one test
    $this->withHeader('Authorization', "Bearer {$other}")
        ->getJson("/api/v1/plans/{$id}/conversation/{$talk['id']}")->assertStatus(404);
});

/**
 * Canon (кадр 37-10, «Связь пропала — разговор продолжится отсюда»): a model that did not answer writes NOTHING — the
 * ribbon stays where it was and the learner repeats the move. Catches a half-written turn: the learner's line stored
 * with no answer to it, which would leave the talk waiting for a role that never speaks.
 */
it('writes nothing at all when the role does not answer, and carries on from the same place', function () {
    [$token, $id] = convDay($this);
    $talk = convStart($this, $token, $id);

    convAgentSays(static fn (): array => throw new RuntimeException('the vendor timed out'));
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$id}/conversation/{$talk['id']}/turn", ['kind' => 'said', 'heard' => 'It started three days ago.'])
        ->assertStatus(503)->assertJsonPath('code', 'plan_conversation_unavailable');

    $read = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$id}/conversation/{$talk['id']}")->assertOk()->json('data');

    expect($read['turns'])->toHaveCount(1)
        ->and($read['state'])->toBe('your_turn')
        ->and(DB::table('conversation_turns')->where('conversation_id', $talk['id'])->count())->toBe(1);
});

/**
 * Canon (п. 5): the money cap makes the NEXT move the role's last — `ended_reason: limit` — and never cuts a learner
 * off mid-word. It counts the MODEL AND THE VOICE together: here the fake model costs nothing and the fake vendor
 * charges for the opening line, so the cap is reached by the voice alone and still closes the talk.
 *
 * Catches a cap that ends the talk where the learner stands, a cap counted on the model alone, and a talk that keeps
 * buying after it.
 */
it('lets the role say goodbye when the talk has spent what the plan allows it', function () {
    // The knobs first: `ConversationRules` is one instance for the application, and the day room resolves it.
    config(['plan.conversation.cost_cap_usd' => 0.000001]);
    config(['generation.speech.enabled' => true, 'generation.speech.driver' => 'fake']);
    app()->instance(SpeechSynthesizerPort::class, new FakeSpeechSynthesizer);
    [$token, $id] = convDay($this);

    $talk = convStart($this, $token, $id);
    expect($talk['state'])->toBe('your_turn')
        ->and((float) DB::table('conversations')->where('id', $talk['id'])->value('cost_usd'))->toBeGreaterThan(0);

    // The opening line has already cost something: the next move is asked for with «say goodbye now».
    $after = convTurn($this, $token, $id, $talk['id']);

    expect($after['state'])->toBe('ended')
        ->and($after['summary']['ended_reason'])->toBe('limit')
        ->and($after['summary']['said_count'])->toBe(1)
        // The learner's line is in the ribbon: the cap closed the talk after it, not instead of it.
        ->and($after['turns'][1])->toMatchArray(['speaker' => 'learner', 'kind' => 'said']);
});

/**
 * Canon (п. 4): a refused subject pushed twice ends the talk as `declined`, and the summary says so. Catches an
 * `end` the server invents and an `end` the server ignores.
 */
it('ends as declined when the role says goodbye over a refused subject', function () {
    [$token, $id] = convDay($this);
    convAgentSays(static fn (ConversationAgentRequest $request): array => [
        ...FakePlanModel::conversationPayload($request),
        'off_topic' => true,
        'end' => $request->turn === 'start' ? 'no' : 'declined',
    ]);

    $talk = convStart($this, $token, $id);
    $after = convTurn($this, $token, $id, $talk['id'], 'said', 'Who did you vote for?');

    expect($after['state'])->toBe('ended')
        ->and($after['summary']['ended_reason'])->toBe('declined')
        ->and($after['turns'][2]['off_topic'])->toBeTrue()
        // A talk that ends offers no next intention.
        ->and($after['hints']['native'])->toBeNull();
});

/**
 * Canon (п. 4): a refused subject pushed a SECOND time ends the talk even when the role does not say goodbye itself.
 * Found live (отчёт §4): a `mini` model kept repeating its refusal, because «the second time» is a fact it could not
 * see — the streak is now on the wire (`OFF_TOPIC_STREAK`) AND the server closes the talk on it.
 *
 * Catches a talk that goes on for ever while the learner pushes, and a first push ending it.
 */
it('ends the talk on the second push off the scene, whatever the role answers', function () {
    [$token, $id] = convDay($this);
    // The role never ends anything by itself: only the server's rule can close this talk.
    convAgentSays(static fn (ConversationAgentRequest $request): array => [
        ...FakePlanModel::conversationPayload($request),
        'off_topic' => $request->turn === 'said',
        'end' => 'no',
    ]);

    $talk = convStart($this, $token, $id);
    $first = convTurn($this, $token, $id, $talk['id'], 'said', 'Who did you vote for?');
    expect($first['state'])->toBe('your_turn')
        ->and($first['turns'][2]['off_topic'])->toBeTrue();

    $second = convTurn($this, $token, $id, $talk['id'], 'said', 'No, really, tell me.');
    expect($second['state'])->toBe('ended')
        ->and($second['summary']['ended_reason'])->toBe('declined');
});

/**
 * Canon (п. 4): the role is led through the scenes IN ORDER, and a scene it closes stays closed. Catches a rehearsal
 * that walks its three scenes in the model's order instead of the plan's, and a checkpoint reopened by a later turn.
 */
it('walks the scenes of a rehearsal in the plan\'s order', function () {
    [$token, $id] = convDay($this, days: 3);
    // The role closes the checkpoint it is on whenever the learner says something — bound BEFORE the days are walked,
    // because the walk holds talks of its own and the route keeps the controller it first built.
    convAgentSays(static fn (ConversationAgentRequest $request): array => [
        ...FakePlanModel::conversationPayload($request),
        'checkpoint_done' => $request->turn === 'said' ? $request->currentCheckpoint : null,
        'end' => 'no',
    ]);
    planWalkDay($this, $token, $id, 1);
    planShiftDay($id);
    planWalkDay($this, $token, $id, 2);
    planShiftDay($id);
    planOpenDay($this, $token, $id, 3);

    $talk = convStart($this, $token, $id, 3);
    $scenes = array_column($talk['scenes'], 'scene_id');

    expect($talk['type'])->toBe('rehearsal')
        ->and($talk['turns_left'])->toBe(10)
        ->and(count($scenes))->toBe(2)
        ->and(array_column($talk['scenes'], 'state'))->toBe(['current', 'locked']);

    // One move: the role closes the first scene, the talk moves to the second and the first stays walked.
    $after = convTurn($this, $token, $id, $talk['id']);

    expect(array_column($after['scenes'], 'state'))->toBe(['done', 'current'])
        ->and($after['turns'][2]['understood'])->toBeTrue();
});

/**
 * Canon (п. 3): «несказанные фразы плана — в возврат следующего дня по правилу „единица возвращается один раз"». They
 * come back as the learner's own line said aloud (`speak_retell`, кадр 35-4) — not as a recognition: nothing about
 * them was answered wrong. Catches a phrase that comes back twice and a return dealt as a choice.
 */
it('gives the phrases the talk did not hear back tomorrow, once, as the line said aloud', function () {
    [$token, $id] = convDay($this);
    planWalkDay($this, $token, $id, 1);
    planShiftDay($id);

    $back = array_values(array_filter(
        planOpenDay($this, $token, $id, 2)['cards'],
        static fn (array $c): bool => $c['source'] === 'returned' && $c['kind'] === 'speak_retell',
    ));

    expect($back)->not->toBeEmpty()
        ->and(array_unique(array_column($back, 'kind')))->toBe(['speak_retell'])
        ->and(array_unique(array_column(array_column($back, 'unit'), 'kind')))->toBe(['exchange'])
        ->and(array_column($back, 'source_day'))->each->toBe(1)
        // Once: the same refs are not dealt again on the day after.
        ->and(count(array_unique(array_column(array_column($back, 'unit'), 'ref'))))->toBe(count($back))
        // Every one of them is a line of the learner's own, said by coverage of that line (кадр 35-4).
        ->and(array_keys($back[0]['payload']))->toEqualCanonicalizing(['scene_id', 'exchange', 'own_line', 'expected_text', 'speech_mode'])
        ->and($back[0]['payload']['speech_mode'])->toBe('repeat');
});

/**
 * Canon (наряд CONV-1, п. 3 + кадр 37-13; наряд CONV-2, п. 9): the day's summary gets «Что было хорошо» — ready lines,
 * inflected by the server — the moment every stage is walked, BEFORE «Закрыть день»: кадр 30-7 is shown to a day still
 * open, and on the first pass through a day the block was empty (CLIENT-CONV-1a, §5 п. 2). Catches a client asked to
 * conjugate «6 реплик», a block printed about a talk that never happened, and a block that waits for the close.
 */
it('writes «Что было хорошо» once every stage is walked, before the day is closed, and nothing before that', function () {
    [$token, $id] = convDay($this);
    $read = fn (): array => $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/1")->assertOk()->json('data');
    expect($read()['window']['highlights'])->toBe([]);

    foreach (planOpenDay($this, $token, $id, 1)['cards'] as $card) {
        planAnswer($this, $token, $id, 1, $card['id'], planWalkResult($card['kind']));
    }
    // Five stages walked, the talk not yet: still nothing.
    expect($read()['window']['highlights'])->toBe([]);

    planTalkThrough($this, $token, $id, 1);
    $open = $read();
    expect($open['day']['status'])->toBe('in_progress')
        ->and($open['window']['highlights'])->toHaveCount(3)
        ->and($open['window']['highlights'][0])->toStartWith('Сказал сам ')
        ->and($open['window']['highlights'][1])->toStartWith('В разговоре использовал ')
        ->and($open['window']['highlights'][2])->toBe('Понял все вопросы');

    $closed = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/close")->assertOk()->json('data');
    expect($closed['window']['highlights'])->toBe($open['window']['highlights']);
});

/**
 * Canon (п. 5): the role's line is said in the voice of the scene's partner, bought turn by turn, and its bill is
 * written on the turn — characters, credits, dollars. Catches a talk that buys nothing and a talk whose sound is
 * filed as the scene's (`plan_line_audios`), where `plan:speak-backfill` would then owe it for ever.
 */
it('buys the role\'s voice for the turn and bills it to the turn', function () {
    [$token, $id] = convDay($this);
    config(['generation.speech.enabled' => true, 'generation.speech.driver' => 'fake']);
    $vendor = new FakeSpeechSynthesizer;
    app()->instance(SpeechSynthesizerPort::class, $vendor);

    $talk = convStart($this, $token, $id);
    $line = $talk['turns'][0];
    $row = DB::table('conversation_turns')->where('id', $line['audio']['ref'])->first();

    expect($line['audio']['url'])->toContain('/api/v1/plans/audio/'.$line['audio']['ref'])
        ->and($line['audio']['voice'])->toBe('partner')
        ->and($vendor->calls)->toBe(1)
        ->and((int) $row?->audio_characters)->toBeGreaterThan(0)
        ->and((int) $row?->audio_credits)->toBeGreaterThan(0)
        ->and((float) $row?->cost_usd)->toBeGreaterThan(0)
        ->and(DB::table('plan_line_audios')->where('line_ref', $line['audio']['ref'])->count())->toBe(0);

    $this->withHeader('Authorization', "Bearer {$token}")->get("/api/v1/plans/audio/{$line['audio']['ref']}")
        ->assertOk()->assertHeader('Content-Type', 'audio/mpeg');
});

// A day dealt before наряд CONV-1 keeps the five stages it was dealt with: nothing is re-dealt, and it closes on them.
it('gives no talk to a day dealt before the talk existed', function () {
    [$token, $id] = convDay($this);
    DB::table('plan_days')->where('plan_id', $id)->where('number', 1)->update(['has_conversation' => false]);

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/conversation")
        ->assertStatus(422)->assertJsonPath('code', 'plan_conversation_not_in_day');

    $cards = planOpenDay($this, $token, $id, 1)['cards'];
    foreach ($cards as $card) {
        planAnswer($this, $token, $id, 1, $card['id'], planWalkResult($card['kind']));
    }
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/close")->assertOk();
});

/**
 * Canon (рубильник раздачи): `plan.conversation.enabled = false` — the day is dealt WITHOUT the sixth stage. The
 * switch is how the server ships before the client that speaks: a day dealt while it is off has the five stages of
 * before the наряд, walks them, closes on them, and has no talk to open. Catches a switch that only hides the stage
 * from the screens while `close` goes on holding the day shut, waiting for a talk nobody can start.
 */
it('deals five stages while the talk is switched off, and closes the day on them', function () {
    config(['plan.conversation.enabled' => false]);
    [$token, $id] = convDay($this);

    $room = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/1")->assertOk()->json('data');
    expect(array_column($room['stages'], 'stage'))->not->toContain('conversation')
        ->and(array_column($room['day']['stages'], 'stage'))->not->toContain('conversation')
        ->and(array_column($room['window']['stages'], 'stage'))->not->toContain('conversation');

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/conversation")
        ->assertStatus(422)->assertJsonPath('code', 'plan_conversation_not_in_day');

    foreach (planOpenDay($this, $token, $id, 1)['cards'] as $card) {
        planAnswer($this, $token, $id, 1, $card['id'], planWalkResult($card['kind']));
    }

    $closed = $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/close")
        ->assertOk()->json('data');
    expect($closed['day']['status'])->toBe('closed')
        ->and(array_column($closed['window']['stages'], 'state'))->toBe(['done', 'done', 'done', 'done', 'done']);
});

/** A clock the test moves by hand — bound before the first request to the conversation routes, so their handlers read it. */
function convClock(string $at): object
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
    };
    app()->instance(Clock::class, $clock);

    return $clock;
}

/** The id of a plan's day — what the journal of stages is keyed by. */
function convDayId(string $planId, int $number = 1): string
{
    return (string) DB::table('plan_days')->where('plan_id', $planId)->where('number', $number)->value('id');
}

/** @return int the hits of one check of the role's prompt, as the admin panel reads them */
function convHits(string $code): int
{
    return (int) DB::table('plan_check_counters')->where('prompt_version', 'conversation_agent.v2')
        ->where('check_name', $code)->where('action', 'counted')->value('hits');
}

/**
 * Canon (наряд CONV-2, п. 1): «ответ роли, совпадающий по Options::APART ≥ 0,5 с любой репликой ученика из плана,
 * отбрасывается и запрашивается заново с усиленной инструкцией (одна попытка), счётчик». Catches a flipped line reaching
 * the ribbon, a retry without the reason, a second retry, and a turn billed for one call when two were made.
 */
it('asks the role again when it says the learner\'s line, once, with the reason, and counts it', function () {
    $fake = convAgentSays(static function (ConversationAgentRequest $request): array {
        $payload = FakePlanModel::conversationPayload($request);
        // The parent's own question in the doctor's mouth — until the server says why that answer was refused.
        $payload['reply_target'] = $request->redo === null ? 'Hello. Do we need an X-ray?' : 'Hello. Where does it hurt?';

        return $payload;
    });
    [$token, $id] = convDay($this);

    $talk = convStart($this, $token, $id);
    $second = $fake->conversationRequests[1];

    expect($talk['turns'])->toHaveCount(1)
        ->and($talk['turns'][0]['text_target'])->toBe('Hello. Where does it hurt?')
        ->and($fake->conversationCalls)->toBe(2)
        ->and($second->redo)->toBe(['reason' => 'learner_line', 'said' => 'Hello. Do we need an X-ray?', 'line' => 'Do we need an X-ray?'])
        ->and($second->turn)->toBe($fake->conversationRequests[0]->turn)
        ->and(convHits('conversation.learner_line'))->toBe(1)
        ->and(convHits('conversation.learner_line_kept'))->toBe(0)
        // Two calls, one line: the turn carries both calls' tokens.
        ->and((int) DB::table('conversation_turns')->where('conversation_id', $talk['id'])->value('tokens_in'))->toBe(2 * 900);
});

/**
 * Canon (п. 1): ONE attempt — the learner is waiting. A second answer that says a learner line too is taken as it is and
 * counted as kept; a second call that does not come leaves the first answer standing, never «Врач не отвечает». Catches
 * a guard that loops, and a guard that turns a reply into a 503.
 */
it('takes the second answer whatever it says, and keeps the first when the second does not come', function () {
    $fake = convAgentSays(static function (ConversationAgentRequest $request, int $call): array {
        if ($request->redo !== null && $request->turn === 'said') {
            throw new RuntimeException('the vendor timed out');
        }

        return [...FakePlanModel::conversationPayload($request), 'reply_target' => 'Do we need an X-ray?'];
    });
    [$token, $id] = convDay($this);

    $talk = convStart($this, $token, $id);
    expect($talk['turns'][0]['text_target'])->toBe('Do we need an X-ray?')
        ->and($fake->conversationCalls)->toBe(2)
        ->and(convHits('conversation.learner_line_kept'))->toBe(1);

    $after = convTurn($this, $token, $id, $talk['id']);
    expect($after['turns'])->toHaveCount(3)
        ->and($after['turns'][2]['text_target'])->toBe('Do we need an X-ray?')
        ->and($fake->conversationCalls)->toBe(4)
        ->and(convHits('conversation.learner_line'))->toBe(2)
        ->and(convHits('conversation.learner_line_kept'))->toBe(2);
});

/**
 * Canon (п. 1): when the answer asked for again still says the learner's line, the sentence that says it is cut out —
 * of the reply and of its translation — and the role's own sentence is said and voiced. Catches a flipped question that
 * reaches the ribbon because the second answer copied the first (the replay of the owner's rehearsal, report §1), and a
 * cut translation that no longer matches its line.
 */
it('cuts the learner\'s line out when the second answer says it too, and says the rest', function () {
    convAgentSays(static fn (ConversationAgentRequest $request): array => [
        ...FakePlanModel::conversationPayload($request),
        'reply_target' => $request->turn === 'start' ? 'Hello, come in. Do we need an X-ray?' : 'And what brings you in today?',
        'reply_native' => $request->turn === 'start' ? 'Здравствуйте, проходите. Нам нужен рентген?' : 'Что вас беспокоит?',
    ]);
    [$token, $id] = convDay($this);

    $talk = convStart($this, $token, $id);

    expect($talk['turns'][0]['text_target'])->toBe('Hello, come in.')
        ->and($talk['turns'][0]['text_native'])->toBe('Здравствуйте, проходите.')
        ->and(convHits('conversation.learner_line'))->toBe(1)
        ->and(convHits('conversation.learner_line_cut'))->toBe(1)
        ->and(convHits('conversation.learner_line_kept'))->toBe(0);
});

/**
 * Canon (наряд CONV-2, п. 4б): «роль повторяет ПРОЩЕ: тот же смысл, другие слова, короче; страховка — ответ, совпадающий с
 * предыдущей репликой роли ≥ 0,7, запрашивается заново с инструкцией „перефразируй", счётчик». Both live runs of
 * CLIENT-CONV-1a got the rescued line back word for word. Catches that, and a rescue compared with the wrong line.
 */
it('asks for other words when a rescue says the line again, and counts it', function () {
    $fake = convAgentSays(static function (ConversationAgentRequest $request): array {
        $payload = FakePlanModel::conversationPayload($request);
        if ($request->turn === 'rescue') {
            $payload['reply_target'] = $request->redo === null ? 'And what brings you in today?' : 'Why are you here?';
        }

        return $payload;
    });
    [$token, $id] = convDay($this);

    $talk = convStart($this, $token, $id);
    $after = convTurn($this, $token, $id, $talk['id'], 'rescue', '');

    expect($after['turns'][2]['text_target'])->toBe('Why are you here?')
        ->and($fake->conversationCalls)->toBe(3)
        ->and($fake->conversationRequests[2]->redo)->toBe(['reason' => 'same_words', 'said' => 'And what brings you in today?', 'line' => null])
        ->and(convHits('conversation.rescue_same_words'))->toBe(1)
        ->and($after['turns_left'])->toBe(4);
});

/**
 * Canon (наряд CONV-2, п. 2): «„Ещё раз" не снимает „пройден" с этапа: журнал прохождения append-only (решение 298), повтор
 * — replay, день закрывается по первому естественному концу». The day's result is the talk that walked the stage: what
 * comes back tomorrow is what IT did not hear. Catches the live defect of CLIENT-CONV-1a (§5 п. 15) — «Ещё раз» after a
 * finished talk made the row «идёт» and held the day shut — and a replay's words rewriting the day's returns.
 */
it('keeps the stage walked through «Ещё раз», closes the day on the first talk, and returns what that talk did not hear', function () {
    [$token, $id] = convDay($this);
    foreach (planOpenDay($this, $token, $id, 1)['cards'] as $card) {
        planAnswer($this, $token, $id, 1, $card['id'], planWalkResult($card['kind']));
    }
    // The first talk hears no phrase of the plan: every turn says «My lower back hurts.».
    $first = planTalkThrough($this, $token, $id, 1);
    expect($first['state'])->toBe('ended')->and($first['replay'])->toBeFalse();

    $replay = convStart($this, $token, $id, body: ['again' => true]);
    expect($replay['replay'])->toBeTrue()->and($replay['state'])->toBe('your_turn');

    $room = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/1")->assertOk()->json('data');
    expect(end($room['window']['stages'])['state'])->toBe('done')
        ->and(end($room['stages'])['state'])->toBe('done')
        ->and(DB::table('plan_stage_passages')->where('day_id', convDayId($id))->where('stage', 'conversation')->value('conversation_id'))->toBe($first['id']);

    // The replay says the day's first phrase, and its summary gives nothing back — the day's result is the first talk.
    $said = convTurn($this, $token, $id, $replay['id'], 'said', 'It hurts in his lower back.');
    while ($said['state'] !== 'ended') {
        $said = convTurn($this, $token, $id, $replay['id'], 'said', 'It hurts in his lower back.');
    }
    expect($said['summary']['returns_tomorrow'])->toBeFalse()
        ->and($said['summary']['phrases_used'])->toBe(1)
        ->and(DB::table('plan_stage_passages')->where('day_id', convDayId($id))->count())->toBe(1);

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/close")->assertOk();
    planShiftDay($id);
    $back = array_values(array_filter(
        planOpenDay($this, $token, $id, 2)['cards'],
        static fn (array $c): bool => $c['source'] === 'returned' && $c['kind'] === 'speak_retell',
    ));
    $frames = array_column(array_column(array_column($back, 'payload'), 'own_line'), 'frame_ref');
    expect($frames)->toContain('p1');
});

/**
 * Canon (наряд CONV-2, п. 3): «summary.minutes — время разговора, не часов … разговор, пролежавший открытым пять часов, —
 * 2 минуты». Catches the wall clock: «Разговор окончен · 323 минуты» on the phone, and a day's `minutes_spent` of 436.
 */
it('reports the minutes the talk was talked, not the hours it stood open', function () {
    [$token, $id] = convDay($this);
    $clock = convClock('2026-09-21T10:00:00Z');

    $talk = convStart($this, $token, $id);
    $clock->at = new DateTimeImmutable('2026-09-21T10:00:20Z');
    convTurn($this, $token, $id, $talk['id']);
    $clock->at = new DateTimeImmutable('2026-09-21T15:00:20Z');   // five hours away from the phone
    convTurn($this, $token, $id, $talk['id']);
    $clock->at = new DateTimeImmutable('2026-09-21T15:00:40Z');
    convTurn($this, $token, $id, $talk['id']);
    $clock->at = new DateTimeImmutable('2026-09-21T15:00:50Z');
    $ended = convTurn($this, $token, $id, $talk['id']);

    // 20 s + 60 s (the five hours, capped) + 20 s + 10 s = 110 s — two minutes.
    expect($ended['state'])->toBe('ended')
        ->and($ended['summary']['minutes'])->toBe(2);
});

/**
 * Canon (наряд CONV-2, п. 10): «документ разговора несёт targets[] … said обновляется каждым ходом; итог считает по тому
 * же списку; phrases_used — с текстом». Catches a strip that never ticks, a summary counting another list than the
 * entry showed, and a phrase heard on a rehearsal that the client cannot underline for want of its text.
 */
it('carries the talk\'s targets, ticks them off turn by turn, and names the heard phrases with their text', function () {
    [$token, $id] = convDay($this);

    $talk = convStart($this, $token, $id);
    expect($talk['targets'])->not->toBeEmpty()
        ->and(count($talk['targets']))->toBeLessThanOrEqual(7)
        ->and(array_unique(array_column($talk['targets'], 'said')))->toBe([false])
        ->and(array_keys($talk['targets'][0]))->toBe(['scene_id', 'ref', 'text_target', 'text_native', 'said']);

    $after = convTurn($this, $token, $id, $talk['id'], 'said', 'It started three days ago.');
    $said = array_values(array_filter($after['targets'], static fn (array $t): bool => $t['said']));
    expect(array_column($said, 'ref'))->toBe(['p2'])
        ->and($after['turns'][1]['phrases_used'])->toBe([[
            'scene_id' => $said[0]['scene_id'], 'ref' => 'p2', 'text_target' => $said[0]['text_target'], 'text_native' => $said[0]['text_native'],
        ]]);

    $ended = planTalkThrough($this, $token, $id, 1);
    expect($ended['summary']['phrases_total'])->toBe(count($talk['targets']))
        ->and(array_column($ended['summary']['phrases'], 'ref'))->toBe(array_column($talk['targets'], 'ref'))
        ->and($ended['summary']['phrases_used'])->toBe(1);
});

/**
 * Canon (наряд CONV-2, п. 12): «talk_title_native („Поговори с врачом"), число сцен в ряду этапа репетиции». The entry
 * (кадр 37-5) is drawn before any talk exists, so both ride on the talk's row of the window. Catches «Поговори с
 * собеседником» where the role is known, and a rehearsal entry that cannot say «· 2 сцены».
 */
it('names the talk and counts its scenes on the talk\'s row of the window', function () {
    [$token, $id] = convDay($this, days: 3);
    $row = function (int $n) use ($token, $id): array {
        $stages = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/{$n}")->assertOk()->json('data.window.stages');

        return $stages[count($stages) - 1];
    };

    $day = $row(1);
    expect($day['stage'])->toBe('conversation')
        ->and($day['talk_title_native'])->toStartWith('Поговори с ')->not->toBe('Поговори с собеседником')
        ->and($day['scenes_count'])->toBe(1)
        ->and(convStart($this, $token, $id)['talk_title_native'])->toBe($day['talk_title_native']);

    planWalkDay($this, $token, $id, 1);
    planShiftDay($id);
    planWalkDay($this, $token, $id, 2);
    planShiftDay($id);
    planOpenDay($this, $token, $id, 3);
    expect($row(3)['scenes_count'])->toBe(2)
        // A card row carries neither.
        ->and($this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/3")->json('data.window.stages.0.talk_title_native'))->toBeNull();
});

/**
 * Canon (наряд CONV-2, п. 2): «запертые дни — починить командой (reconcile), не UPDATE вручную». A talk that ended before
 * the journal of stages existed has no passage; `plan:reconcile-talks` writes it from the journal of talks — the first
 * talk that ended of its own — and a second run writes nothing. Catches a day left locked by an open «Ещё раз» after a
 * finished talk, a replayed talk taken for a walked one, and a reconcile that writes twice.
 */
it('writes the passages of talks that ended before the journal of stages, once, from the first natural end', function () {
    [$token, $id] = convDay($this);
    foreach (planOpenDay($this, $token, $id, 1)['cards'] as $card) {
        planAnswer($this, $token, $id, 1, $card['id'], planWalkResult($card['kind']));
    }
    $first = planTalkThrough($this, $token, $id, 1);
    convStart($this, $token, $id, body: ['again' => true]);
    // The day as it stood before наряд CONV-2: its talk over, its passage never written, a replay going on.
    DB::table('plan_stage_passages')->where('day_id', convDayId($id))->delete();
    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/close")
        ->assertStatus(409)->assertJsonPath('meta.stage', 'conversation');

    expect(Artisan::call('plan:reconcile-talks', ['--dry' => true]))->toBe(0)
        ->and(Artisan::output())->toContain('было 1 / стало 1')->toContain("[--dry] план {$id} · день 1 (in_progress) · разговор {$first['id']} · natural")
        ->and(DB::table('plan_stage_passages')->where('day_id', convDayId($id))->count())->toBe(0);

    Artisan::call('plan:reconcile-talks');
    expect(Artisan::output())->toContain('было 1 / стало 0')->toContain('записано прохождений: 1')->toContain($first['id'])
        ->and(DB::table('plan_stage_passages')->where('day_id', convDayId($id))->value('conversation_id'))->toBe($first['id']);

    Artisan::call('plan:reconcile-talks');
    expect(Artisan::output())->toContain('было 0 / стало 0')
        ->and(DB::table('plan_stage_passages')->where('day_id', convDayId($id))->count())->toBe(1);

    $this->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/days/1/close")->assertOk();
});

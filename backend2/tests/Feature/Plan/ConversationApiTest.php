<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Infrastructure\Adapter\FakeSpeechSynthesizer;
use App\Modules\Plan\Application\Dto\ConversationAgentRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;

/**
 * THE TALK WITH THE AGENT OVER HTTP (наряд CONV-1, кадры 37-5…37-12): the sixth stage of a day — started, walked turn
 * by turn, ended by the role, and read back after a dropped connection.
 *
 * Everything here runs on `FakePlanModel`: the role is played deterministically, so the suite buys nothing and the
 * rules — whose move it is, what a rescue costs, who counts the phrases of the plan, when the talk ends — are checked
 * against the server and not against a vendor's mood.
 */

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
        // The intention offered is the learner's OWN line of this scene, in their language and bare — the client
        // prints «Скажи, что …» itself (DECISIONS п. 359). It is the lesson's, not the model's.
        ->and($talk['hints']['native'])->toBe('У него болит поясница.');

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
        ->and($after['turns'][1])->toMatchArray(['speaker' => 'learner', 'kind' => 'rescue', 'text_target' => null, 'phrases_used' => []])
        ->and($after['turns'][2]['understood'])->toBeNull();

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
 * Canon (наряд CONV-1, п. 3 + кадр 37-13): the day's summary gets «Что было хорошо» — ready lines, inflected by the
 * server. Catches a client asked to conjugate «6 реплик» and a block printed about a talk that never happened.
 */
it('writes «Что было хорошо» on the day it is passed, and nothing before that', function () {
    [$token, $id] = convDay($this);
    $room = $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/plans/{$id}/days/1")->assertOk()->json('data');
    expect($room['window']['highlights'])->toBe([]);

    $closed = planWalkDay($this, $token, $id, 1);
    $highlights = $closed['window']['highlights'];

    expect($highlights)->toHaveCount(3)
        ->and($highlights[0])->toStartWith('Сказал сам ')
        ->and($highlights[1])->toStartWith('В разговоре использовал ')
        ->and($highlights[2])->toBe('Понял все вопросы');
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

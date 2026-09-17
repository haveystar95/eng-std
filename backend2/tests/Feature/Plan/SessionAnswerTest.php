<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\SpeechSynthesizerPort;
use App\Modules\Generation\Infrastructure\Adapter\FakeSpeechSynthesizer;
use App\Modules\Plan\Application\Port\LineSpeaker;
use App\Modules\Plan\Domain\Assembly\Audio;
use App\Modules\Plan\Domain\Assembly\Retry;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\Repository\PlanRepository;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardResult;
use App\Modules\Plan\Domain\ValueObject\CardSource;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\PlanId;
use App\Modules\Plan\Domain\ValueObject\PlanSceneId;
use App\Modules\Plan\Domain\ValueObject\Speaker;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\VoiceGender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(ThrottleRequests::class);
    Storage::fake('local');
});

/**
 * ONE ANSWER OVER HTTP, ON THE REGISTRY'S CARDS (наряд SESSION-1a, разд. 3, 5; D-02, D-04, D-06, D-30, D-31): what a kind
 * lets the client write, what an answer keeps, the copy of a first failure, the unit that returns and the day it names,
 * the day's and the stage's numbers in the reply, every sound rendered with its file and length, and the room's cards
 * stage by stage.
 *
 * The cards are dealt by this file, not by the assembler: the day is put in progress with nothing dealt, and each test
 * writes exactly the cards it is about — the rules of an answer do not depend on which cards a lesson yields.
 */

/** @return array{token: string, id: string, dayId: string, sceneId: string} a plan of two scene days, day 1 in progress, no card dealt */
function s1aDay(object $ctx): array
{
    [, $token] = planLearner();
    $id = planCreate($ctx, $token, ['days_total' => 2])['id'];
    $ctx->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$id}/start")->assertOk();
    $day = DB::table('plan_days')->where('plan_id', $id)->where('number', 1)->first();
    DB::table('day_cards')->where('day_id', $day->id)->delete();
    DB::table('plan_days')->where('id', $day->id)->update(['status' => 'in_progress', 'opened_at' => now()]);

    return ['token' => $token, 'id' => $id, 'dayId' => (string) $day->id, 'sceneId' => (string) $day->scene_id];
}

/**
 * Deal one card of the day at its place in its stage, answered already when `$answeredAt` is given; `$id` fixes the
 * card's address where a test asserts what is seeded by it.
 *
 * @param  array{token: string, id: string, dayId: string, sceneId: string}  $day
 * @param  array<string, mixed>  $payload
 */
function s1aDeal(array $day, CardKind $kind, UnitKind $unit, string $ref, int $position, array $payload = [], ?DateTimeImmutable $answeredAt = null, ?string $id = null): DayCard
{
    $card = DayCard::dealt(
        $id === null ? DayCardId::generate() : DayCardId::fromString($id), PlanDayId::fromString($day['dayId']), $kind->stage(), $position, $kind,
        ['scene_id' => $day['sceneId'], ...$payload], CardSource::Today, null, $unit, $ref,
    );
    if ($answeredAt !== null) {
        $card->answer(CardResult::Passed, 1, null, $answeredAt);
    }
    app(DayCardRepository::class)->insertAll([$card]);

    return $card;
}

/**
 * @param  array{token: string, id: string, dayId: string, sceneId: string}  $day
 * @param  array<string, mixed>|null  $response
 */
function s1aAnswer(object $ctx, array $day, string $cardId, string $result, int $attempts = 1, ?array $response = null): TestResponse
{
    return $ctx->withHeader('Authorization', "Bearer {$day['token']}")->postJson(
        "/api/v1/plans/{$day['id']}/days/1/cards/{$cardId}/answer",
        ['result' => $result, 'attempts' => $attempts] + ($response === null ? [] : ['response' => $response]),
    );
}

/** @return array<string, mixed> a choice of four, the right one `o3` */
function s1aChoice(string $ref): array
{
    return [
        'direction' => 'term_to_native',
        'prompt' => ['text_target' => 'fever', 'image' => ['url' => null, 'tone' => null], 'audio' => Audio::of($ref)],
        'options' => [['id' => 'o1', 'text' => 'кашель'], ['id' => 'o2', 'text' => 'спина'], ['id' => 'o3', 'text' => 'жар'], ['id' => 'o4', 'text' => 'грелка']],
        'correct' => 'o3',
    ];
}

/**
 * @param  array{token: string, id: string, dayId: string, sceneId: string}  $day
 * @return array<string, array<string, mixed>> the day's cards on the wire, by id
 */
function s1aCards(object $ctx, array $day): array
{
    $cards = $ctx->withHeader('Authorization', "Bearer {$day['token']}")->getJson("/api/v1/plans/{$day['id']}/days/1/cards")->assertOk()->json('data.cards');

    return array_column($cards, null, 'id');
}

/**
 * The voice pipe on over the fake vendor — after the plan is built (nothing is bought) and before the day's first read:
 * a route keeps the controller it resolved, and with it the speaker it was given.
 */
function s1aVoiceOn(): void
{
    config(['generation.speech.enabled' => true, 'generation.speech.driver' => 'fake']);
    app()->instance(SpeechSynthesizerPort::class, new FakeSpeechSynthesizer());
}

/**
 * A stored file of `$ref` in its speaker's voice of the day's scene.
 *
 * @param  array{token: string, id: string, dayId: string, sceneId: string}  $day
 */
function s1aVoiceFile(array $day, string $ref, int $durationMs): string
{
    $partner = VoiceGender::from((string) (DB::table('plan_scenes')->where('id', $day['sceneId'])->value('partner_voice_gender') ?? 'female'));
    $speaker = str_starts_with($ref, 'x') && ! str_ends_with($ref, 'b') ? Speaker::Partner : Speaker::Learner;
    $gender = $speaker === Speaker::Partner ? $partner : $partner->opposite();
    $id = Ulid::generate();
    DB::table('plan_line_audios')->insert([
        'id' => $id, 'scene_id' => $day['sceneId'], 'user_id' => (string) DB::table('plan_scenes')->where('id', $day['sceneId'])->value('user_id'),
        'line_ref' => $ref, 'voice_key' => (string) app(LineSpeaker::class)->voiceKeyFor('en', $speaker, $gender), 'format' => 'mp3',
        'path' => "plan-audio/{$ref}.mp3", 'bytes' => 1, 'duration_ms' => $durationMs, 'created_at' => now(),
    ]);

    return $id;
}

// Canon (D-31): «судья — только skipped; сквозные — passed|skipped; голос — без failed». Catches a pass nobody judged,
// a failed voice (the recogniser's silence is no lapse), and a listening walked «wrong».
it('refuses a result the kind cannot have with 422 plan_card_result_not_allowed, and leaves the card unanswered', function () {
    $day = s1aDay($this);
    $refused = [
        [s1aDeal($day, CardKind::SpeakAnswer, UnitKind::Exchange, 'x1', 1), 'passed'],
        [s1aDeal($day, CardKind::WordRepeat, UnitKind::Word, 'v1', 1), 'failed'],
        [s1aDeal($day, CardKind::DialogueAnswer, UnitKind::Exchange, 'x2', 1), 'failed'],
        [s1aDeal($day, CardKind::ListenPace, UnitKind::Day, 'day', 1), 'failed'],
        [s1aDeal($day, CardKind::PhraseOwnSlot, UnitKind::Phrase, 'p1', 1), 'hinted'],
        [s1aDeal($day, CardKind::ListenDialogue, UnitKind::Day, 'day', 2), 'hinted'],
    ];

    foreach ($refused as [$card, $result]) {
        s1aAnswer($this, $day, $card->id()->value, $result)
            ->assertStatus(422)
            ->assertJsonPath('code', 'plan_card_result_not_allowed')
            ->assertJsonPath('meta.kind', $card->kind()->value)
            ->assertJsonPath('meta.result', $result);
    }

    expect(DB::table('day_cards')->where('day_id', $day['dayId'])->whereNotNull('result')->count())->toBe(0)
        ->and((int) DB::table('plan_days')->where('id', $day['dayId'])->value('cards_done'))->toBe(0);
});

// Canon (разд. 3): «Пропустить» → skipped обычным POST — the one thing the client writes on a judged card.
it('lets a judged card be given up — skipped on phrase_own_slot and speak_answer — and a voice be passed with a hint', function () {
    $day = s1aDay($this);
    $given = [
        s1aDeal($day, CardKind::PhraseOwnSlot, UnitKind::Phrase, 'p2', 1),
        s1aDeal($day, CardKind::SpeakAnswer, UnitKind::Exchange, 'x1', 1),
    ];
    foreach ($given as $card) {
        s1aAnswer($this, $day, $card->id()->value, 'skipped', 2)->assertOk()
            ->assertJsonPath('data.card.result', 'skipped')
            ->assertJsonPath('data.card.attempts', 2)
            ->assertJsonPath('data.requeued', null);
    }
    $voice = s1aDeal($day, CardKind::DialogueAsk, UnitKind::Exchange, 'x4', 1);
    // «Повтори свою реплику» is a voice card now (наряд BACK-TAILS-1 §1.1): the client passes it itself.
    $retell = s1aDeal($day, CardKind::SpeakRetell, UnitKind::Exchange, 'x3', 2);

    s1aAnswer($this, $day, $voice->id()->value, 'hinted')->assertOk()->assertJsonPath('data.card.result', 'hinted');
    s1aAnswer($this, $day, $retell->id()->value, 'passed', 2)->assertOk()
        ->assertJsonPath('data.card.result', 'passed')
        ->assertJsonPath('data.requeued', null);
    s1aAnswer($this, $day, s1aDeal($day, CardKind::SpeakRetell, UnitKind::Exchange, 'x5', 3)->id()->value, 'failed')
        ->assertStatus(422)->assertJsonPath('code', 'plan_card_result_not_allowed');
    expect(DB::table('day_cards')->where('day_id', $day['dayId'])->where('result', 'skipped')->count())->toBe(2);
});

// Canon (D-30): «response: heard, hinted_at, slot_value, filler_index, mode, no_mic — только эти ключи». Catches a
// response dropped on the floor, a key the client invented stored beside them, and a mode nobody deals.
it('keeps what the answer left — only the keys of the contract — and gives it back on the reply and on the day’s cards', function () {
    $day = s1aDay($this);
    $said = s1aDeal($day, CardKind::PhraseOtherSlot, UnitKind::Phrase, 'p1', 1);
    $silent = s1aDeal($day, CardKind::PhraseIntro, UnitKind::Phrase, 'p1', 2);
    $response = ['heard' => 'I have a pain in my neck', 'hinted_at' => '2026-09-16T10:00:05Z', 'slot_value' => 'my neck', 'filler_index' => 2, 'mode' => 'voice_hint', 'no_mic' => false];

    $reply = s1aAnswer($this, $day, $said->id()->value, 'passed', 1, [...$response, 'secret' => 'not kept', 'judge' => ['accepted' => true]])
        ->assertOk()->json('data.card.response');
    s1aAnswer($this, $day, $silent->id()->value, 'passed')->assertOk()->assertJsonPath('data.card.response', null);

    $stored = json_decode((string) DB::table('day_cards')->where('id', $said->id()->value)->value('response'), true);
    $cards = s1aCards($this, $day);

    expect($reply)->toEqual($response)
        ->and($stored)->toEqual($response)
        ->and($cards[$said->id()->value]['response'])->toEqual($response)
        ->and($cards[$silent->id()->value]['response'])->toBeNull();

    $other = s1aDeal($day, CardKind::PhraseRepeat, UnitKind::Phrase, 'p2', 3);
    // Хвост SESSION-1b (§15.1 п. 12), закрыт нарядом BACK-TAILS-1 §2.1: the client walks a phrase said aloud in a series
    // of rounds and could not say so — the server refused the word and the mode was lost.
    s1aAnswer($this, $day, $other->id()->value, 'passed', 2, ['mode' => 'rounds'])->assertOk()
        ->assertJsonPath('data.card.response.mode', 'rounds');
    $third = s1aDeal($day, CardKind::PhraseRepeat, UnitKind::Phrase, 'p3', 4);
    s1aAnswer($this, $day, $third->id()->value, 'passed', 1, ['mode' => 'shouting'])->assertStatus(422)->assertJsonValidationErrors(['response.mode']);
    s1aAnswer($this, $day, $third->id()->value, 'passed', 1, ['filler_index' => 12])->assertStatus(422)->assertJsonValidationErrors(['response.filler_index']);
    s1aAnswer($this, $day, $third->id()->value, 'passed', 1, ['heard' => str_repeat('a', 1001)])->assertStatus(422)->assertJsonValidationErrors(['response.heard']);
});

// Canon (разд. 3; D-06): «неверно первый раз → failed и копия в конец этапа (requeued, retry_of); второй раз → returns».
// Catches a copy in the same order (the second try is the first one's position remembered), a copy whose `correct` no
// longer names the right option, a copy dealt in the middle of the stage, and a return without its day.
it('deals a choice failed once again at the end of its stage, reshuffled with the right id kept, and returns the word on day 2 when the copy fails', function () {
    $day = s1aDay($this);
    $intro = s1aDeal($day, CardKind::WordIntro, UnitKind::Word, 'v2', 1);
    // A fixed address: the copy is shuffled by `<id>:retry`, and this one's order is o3, o2, o1, o4 — not the original's.
    $choose = s1aDeal($day, CardKind::WordChoose, UnitKind::Word, 'v2', 2, s1aChoice('v2'), id: '01J8SESS10NANSWER0CH00SE01');
    $repeat = s1aDeal($day, CardKind::WordRepeat, UnitKind::Word, 'v2', 3);
    $phrase = s1aDeal($day, CardKind::PhraseIntro, UnitKind::Phrase, 'p1', 7);

    $first = s1aAnswer($this, $day, $choose->id()->value, 'failed', 1, ['filler_index' => 0])->assertOk()->json('data');
    $copy = $first['requeued'];
    $expected = Retry::payload($choose->payload(), $choose->id()->value.':retry');

    expect(array_keys($first))->toBe(['card', 'requeued', 'unit', 'day', 'stage'])
        ->and($first['card']['result'])->toBe('failed')
        ->and($first['card']['returns'])->toBeFalse()
        ->and($copy)->not->toBeNull()
        ->and($copy['retry_of'])->toBe($choose->id()->value)
        ->and($copy['stage'])->toBe('words')
        ->and($copy['kind'])->toBe('word_choose')
        ->and($copy['position'])->toBe(4)
        ->and($copy['result'])->toBeNull()
        ->and($copy['response'])->toBeNull()
        ->and($copy['payload']['options'])->toEqual($expected['options'])
        ->and(array_column($copy['payload']['options'], 'id'))->toBe(['o3', 'o2', 'o1', 'o4'])
        ->and(array_column($copy['payload']['options'], 'text', 'id'))->toEqual(array_column($choose->payload()['options'], 'text', 'id'))
        ->and($copy['payload']['correct'])->toBe('o3')
        ->and($first['unit'])->toBe(['kind' => 'word', 'ref' => 'v2', 'returns_tomorrow' => false, 'returns_day' => null])
        ->and($first['day']['cards_total'])->toBe(5)
        ->and($first['day']['cards_done'])->toBe(1)
        ->and($first['stage']['stage'])->toBe('words');

    $second = s1aAnswer($this, $day, $copy['id'], 'failed', 2)->assertOk()->json('data');

    expect($second['requeued'])->toBeNull()
        ->and($second['card']['returns'])->toBeTrue()
        ->and($second['unit'])->toBe(['kind' => 'word', 'ref' => 'v2', 'returns_tomorrow' => true, 'returns_day' => 2])
        ->and($second['day']['cards_total'])->toBe(5)
        ->and($second['day']['cards_done'])->toBe(2)
        ->and(DB::table('day_cards')->where('day_id', $day['dayId'])->count())->toBe(5);

    // The room deals the stage in position order, the copy last; the phrase stage has its own.
    $stages = collect($this->withHeader('Authorization', "Bearer {$day['token']}")->getJson("/api/v1/plans/{$day['id']}/days/1")->assertOk()->json('data.stages'))->keyBy('stage');

    expect(array_column($stages['words']['cards'], 'id'))->toBe([$intro->id()->value, $choose->id()->value, $repeat->id()->value, $copy['id']])
        ->and(array_column($stages['phrases']['cards'], 'id'))->toBe([$phrase->id()->value])
        ->and($stages['dialogue']['cards'])->toBe([])
        ->and($stages['words']['cards'][3]['retry_of'])->toBe($choose->id()->value);
});

// Canon (D-05): «day-единица никогда не возвращается». Catches the day's listening marked to come back tomorrow — the
// next day has another dialogue — and the listening listed among the programme's units.
// Canon (SESSION-1a, хвост): «Слушаю и отвечаю» deals no copy — its review shows the answer — and the day's listening
// never returns.
it('never returns the day’s listening and deals it no copy: a wrong question is failed and nothing more, and the room lists no day unit', function () {
    $day = s1aDay($this);
    $question = s1aDeal($day, CardKind::ListenQuestion, UnitKind::Day, 'L1', 2, [
        'question' => ['ref' => 'L1', 'text_native' => 'Где болит?'], 'exchange_step' => 2,
        'options' => [['id' => 'o1', 'text' => 'В спине'], ['id' => 'o2', 'text' => 'В шее'], ['id' => 'o3', 'text' => 'В ноге'], ['id' => 'o4', 'text' => 'В руке']],
        'correct' => 'o1',
    ]);
    s1aDeal($day, CardKind::ListenDialogue, UnitKind::Day, 'day', 1, ['lines' => [], 'total_ms' => null]);
    s1aDeal($day, CardKind::WordIntro, UnitKind::Word, 'v1', 1);

    $reply = s1aAnswer($this, $day, $question->id()->value, 'failed')->assertOk()->json('data');

    expect($reply['requeued'])->toBeNull()
        ->and($reply['card']['result'])->toBe('failed')
        ->and($reply['card']['returns'])->toBeFalse()
        ->and($reply['card']['unit'])->toBe(['kind' => 'day', 'ref' => 'L1'])
        ->and($reply['unit'])->toBe(['kind' => 'day', 'ref' => 'L1', 'returns_tomorrow' => false, 'returns_day' => null])
        ->and($reply['stage']['stage'])->toBe('listen');

    $room = $this->withHeader('Authorization', "Bearer {$day['token']}")->getJson("/api/v1/plans/{$day['id']}/days/1")->assertOk()->json('data');

    expect(array_unique(array_column($room['program'], 'unit_kind')))->toBe(['word'])
        ->and(collect($room['stages'])->firstWhere('stage', 'listen')['cards'])->toHaveCount(2);
});

// Canon (разд. 3): «POST результата отвечает {card, unit, day: {cards_done, minutes_spent}}» — the stage's summary too
// (D-02). Catches the stage's minutes counted over the whole day, and the day's numbers left as they were before the answer.
it('answers with the day’s numbers refolded and the minutes of the card’s own stage', function () {
    $day = s1aDay($this);
    s1aDeal($day, CardKind::ListenQuestion, UnitKind::Day, 'L1', 1, [], new DateTimeImmutable('-2000 seconds'));
    s1aDeal($day, CardKind::ListenQuestion, UnitKind::Day, 'L2', 2, [], new DateTimeImmutable('-1830 seconds'));
    s1aDeal($day, CardKind::WordIntro, UnitKind::Word, 'v1', 1, [], new DateTimeImmutable('-40 seconds'));
    $open = s1aDeal($day, CardKind::WordChoose, UnitKind::Word, 'v1', 2, s1aChoice('v1'));
    s1aDeal($day, CardKind::WordRepeat, UnitKind::Word, 'v1', 3);

    $reply = s1aAnswer($this, $day, $open->id()->value, 'passed')->assertOk()->json('data');

    // Day: 170 s between the questions, a pause of half an hour not counted, 40 s to the answer → 4 min; words: 40 s → 1.
    expect($reply['day'])->toBe(['cards_total' => 5, 'cards_done' => 4, 'minutes_spent' => 4])
        ->and($reply['stage'])->toBe(['stage' => 'words', 'minutes_spent' => 1])
        ->and($reply['requeued'])->toBeNull()
        ->and((int) DB::table('plan_days')->where('id', $day['dayId'])->value('cards_done'))->toBe(4)
        ->and((int) DB::table('plan_days')->where('id', $day['dayId'])->value('minutes_spent'))->toBe(4);
});

// Canon (разд. 0, 5): «у каждого звучащего элемента audio {ref, url, duration_ms, voice}; duration_ms — null, если нет».
// Catches a stub sent raw, a url before there is a file, a length the file does not have, a visit's length that leaves a
// line out, and a word card that shows no photo it has.
it('renders every sound as {ref, url, duration_ms, voice} — no url without a file, the file’s address and length with one — and a word card’s photo', function () {
    $day = s1aDay($this);
    s1aVoiceOn();
    DB::table('plan_terms')->where('scene_id', $day['sceneId'])->where('ref', 'v1')
        ->update(['image_url' => 'https://images.pexels.test/v1.jpg', 'image_tone' => '#112233']);
    $intro = s1aDeal($day, CardKind::WordIntro, UnitKind::Word, 'v1', 1, [
        'term' => ['ref' => 'v1', 'text_target' => 'fever', 'image' => ['url' => null, 'tone' => null]],
        'audio' => ['term' => Audio::of('v1'), 'line' => Audio::of('x1')],
    ]);
    $visit = s1aDeal($day, CardKind::ListenDialogue, UnitKind::Day, 'day', 1, [
        'lines' => [
            ['ref' => 'x1', 'role' => 'partner', 'exchange_step' => 1, 'text_target' => 'Hi.', 'text_native' => 'Привет.', 'audio' => Audio::of('x1')],
            ['ref' => 'x1b', 'role' => 'learner', 'exchange_step' => 1, 'text_target' => 'Hello.', 'text_native' => 'Здравствуйте.', 'audio' => Audio::of('x1b')],
        ],
        'total_ms' => null,
    ]);

    $before = s1aCards($this, $day);

    expect($before[$intro->id()->value]['payload']['audio']['term'])->toBe(['ref' => 'v1', 'url' => null, 'duration_ms' => null, 'voice' => 'learner'])
        ->and($before[$intro->id()->value]['payload']['audio']['line'])->toBe(['ref' => 'x1', 'url' => null, 'duration_ms' => null, 'voice' => 'partner'])
        ->and($before[$intro->id()->value]['payload']['term']['image'])->toBe(['url' => 'https://images.pexels.test/v1.jpg', 'tone' => '#112233'])
        ->and($before[$visit->id()->value]['payload']['total_ms'])->toBeNull();

    $word = s1aVoiceFile($day, 'v1', 812);
    $partner = s1aVoiceFile($day, 'x1', 2400);
    $after = s1aCards($this, $day);
    $sounds = $after[$intro->id()->value]['payload']['audio'];

    expect(array_keys($sounds['term']))->toBe(['ref', 'url', 'duration_ms', 'voice'])
        ->and($sounds['term']['url'])->toEndWith("/api/v1/plans/audio/{$word}")
        ->and($sounds['term']['duration_ms'])->toBe(812)
        ->and($sounds['term']['voice'])->toBe('learner')
        ->and($sounds['line']['url'])->toEndWith("/api/v1/plans/audio/{$partner}")
        ->and($sounds['line']['duration_ms'])->toBe(2400)
        // The learner's line of exchange 1 has no file yet: the visit has no length.
        ->and($after[$visit->id()->value]['payload']['lines'][1]['audio'])->toBe(['ref' => 'x1b', 'url' => null, 'duration_ms' => null, 'voice' => 'learner'])
        ->and($after[$visit->id()->value]['payload']['total_ms'])->toBeNull();

    s1aVoiceFile($day, 'x1b', 1100);

    expect(s1aCards($this, $day)[$visit->id()->value]['payload']['total_ms'])->toBe(3500);
});

// Canon (наряд SESSION-1a, разд. 5 «Window»): «диалог — все обмены своей сцены по уроку; тексты — из урока; обмен без
// карточек пройден, когда пройден этап диалога». Catches a tab that lists only the exchanges cards were dealt on, texts
// read off a payload that no longer carries them, a line without cards walked before the stage is, and a word read off
// its card instead of its term.
it('reads the window’s dialogue off the day’s own lesson and its words off their terms, a line without cards walked with the dialogue stage', function () {
    $day = s1aDay($this);
    $lesson = app(PlanRepository::class)->findById(PlanId::fromString($day['id']))?->scene(PlanSceneId::fromString($day['sceneId']))->lesson();
    $steps = array_values(array_unique(array_map(static fn ($e): int => $e->step, $lesson->exchanges)));
    $term = DB::table('plan_terms')->where('scene_id', $day['sceneId'])->where('ref', 'v1')->first();
    s1aDeal($day, CardKind::WordIntro, UnitKind::Word, 'v1', 1, ['term' => ['text_target' => 'not the term']]);
    $choose = s1aDeal($day, CardKind::WordChoose, UnitKind::Word, 'v1', 2, s1aChoice('v1'));
    $partner = s1aDeal($day, CardKind::DialoguePartner, UnitKind::Exchange, 'x1', 1);
    $answer = s1aDeal($day, CardKind::DialogueAnswer, UnitKind::Exchange, 'x1', 2);
    s1aDeal($day, CardKind::SpeakAnswer, UnitKind::Exchange, 'x2', 1);
    s1aDeal($day, CardKind::ListenDialogue, UnitKind::Day, 'day', 1, ['lines' => [], 'total_ms' => null]);
    $window = fn (): array => $this->withHeader('Authorization', "Bearer {$day['token']}")->getJson("/api/v1/plans/{$day['id']}/days/1")->assertOk()->json('data.window.program');

    $before = $window();
    $first = $lesson->exchange($steps[0]);

    expect(array_column($before['dialogue']['items'], 'step'))->toBe($steps)
        ->and($before['dialogue']['items'][0]['partner']['text'])->toBe(trim((string) $first?->partner()?->textTarget))
        ->and($before['dialogue']['items'][0]['learner']['text'])->toBe(trim((string) $first?->learner()?->textTarget))
        ->and($before['dialogue']['items'][0]['learner']['translation'])->toBe($first?->learner()?->textNative)
        ->and($before['dialogue']['items'][0]['kind'])->toBe($first?->kind->value)
        ->and(array_unique(array_column(array_column($before['dialogue']['items'], 'learner'), 'state')))->toBe(['pending'])
        ->and($before['words']['items'])->toHaveCount(1)
        ->and($before['words']['items'][0]['term'])->toBe($term->text_target)
        ->and($before['words']['items'][0]['translation'])->toBe($term->text_native);

    s1aAnswer($this, $day, $partner->id()->value, 'passed')->assertOk();
    s1aAnswer($this, $day, $answer->id()->value, 'passed')->assertOk();
    $copy = s1aAnswer($this, $day, $choose->id()->value, 'failed')->assertOk()->json('data.requeued');
    s1aAnswer($this, $day, $copy['id'], 'failed')->assertOk();
    $after = $window();
    $states = array_column(array_column($after['dialogue']['items'], 'learner'), 'state');
    $learners = count(array_filter(array_column($after['dialogue']['items'], 'learner')));

    // x1: its cards answered; x2: its speak card still open; every other exchange: no card, the dialogue stage walked.
    expect($states[0])->toBe('done')
        ->and($states[1])->toBe('pending')
        ->and(array_unique(array_slice($states, 2)))->toBe(['done'])
        ->and($after['dialogue']['summary'])->toBe(['total' => $learners, 'done' => $learners - 1, 'returns' => 0])
        ->and($after['words']['items'][0]['state'])->toBe('returns_tomorrow')
        ->and($after['words']['items'][0]['returns_day'])->toBe(2)
        ->and($after['words']['summary'])->toBe(['total' => 1, 'done' => 0, 'returns' => 1]);
});

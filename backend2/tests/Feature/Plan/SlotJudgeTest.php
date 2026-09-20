<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelCatalog;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Domain\ValueObject\PromptShape;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Plan\Application\Dto\SlotJudgeRequest;
use App\Modules\Plan\Application\Port\PlanModelPort;
use App\Modules\Plan\Domain\Entity\DayCard;
use App\Modules\Plan\Domain\Repository\DayCardRepository;
use App\Modules\Plan\Domain\ValueObject\CardKind;
use App\Modules\Plan\Domain\ValueObject\CardSource;
use App\Modules\Plan\Domain\ValueObject\DayCardId;
use App\Modules\Plan\Domain\ValueObject\PlanDayId;
use App\Modules\Plan\Domain\ValueObject\UnitKind;
use App\Modules\Plan\Infrastructure\Adapter\ArraySlotJudgeQuota;
use App\Modules\Plan\Infrastructure\Adapter\RedisSlotJudgeQuota;
use App\Modules\Plan\Infrastructure\Model\ContentModelPlanBuilder;
use App\Modules\Plan\Infrastructure\Model\FakePlanModel;
use App\Modules\Plan\Infrastructure\Prompt\PlanPromptFiles;
use App\Modules\Plan\Infrastructure\Prompt\PlanSchemas;
use App\Modules\Shared\Domain\ValueObject\Ulid;
use App\Modules\Shared\Domain\ValueObject\UserId;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Sleep;

/**
 * THE SLOT JUDGE OVER HTTP (`POST …/cards/{card}/judge`, наряд SESSION-1a, разд. 4; наряд BACK-TAILS-1 §1.1): the code
 * before the model, the model only for the slot, the code's word when the model is silent or the day's cap is spent —
 * and the verdict written on the card without a transaction held across the call. Two kinds are judged and no more:
 * `speak_retell` says the learner's own line back and the client counts its coverage.
 *
 * The cards are inserted by the test with the payloads the registry deals (§4 of the SPEC), so the judge is checked
 * against the frames of the fake lesson whatever the assembly deals around them.
 */

uses(RefreshDatabase::class);

beforeEach(fn () => $this->withoutMiddleware(ThrottleRequests::class));

/**
 * An intermediate plan with day 1 open, and the fake model bound so a test can script the judge.
 *
 * @return array{token: string, plan: string, day: string, scene: string, model: FakePlanModel}
 */
function sjOpenDay(object $ctx, ?Closure $slotJudge = null): array
{
    $model = new FakePlanModel(slotJudge: $slotJudge);
    app()->instance(PlanModelPort::class, $model);

    [, $token] = planLearner();
    $build = planCreate($ctx, $token, ['level' => 'intermediate']);
    $ctx->withHeader('Authorization', "Bearer {$token}")->postJson("/api/v1/plans/{$build['id']}/start")->assertOk();
    planOpenDay($ctx, $token, $build['id'], 1);
    $day = DB::table('plan_days')->where('plan_id', $build['id'])->where('number', 1)->first();

    return ['token' => $token, 'plan' => $build['id'], 'day' => (string) $day->id, 'scene' => (string) $day->scene_id, 'model' => $model];
}

/**
 * A card of the kind with the payload given, dealt into day 1 after whatever the day was dealt.
 *
 * @param  array{day: string, scene: string}  $day
 * @param  array<string, mixed>  $payload
 */
function sjDeal(array $day, CardKind $kind, array $payload, UnitKind $unit, string $ref): string
{
    static $position = 900;
    $id = DayCardId::generate();
    app(DayCardRepository::class)->insertAll([DayCard::dealt(
        $id, PlanDayId::fromString($day['day']), $kind->stage(), $position++, $kind,
        ['scene_id' => $day['scene'], ...$payload], CardSource::Today, null, $unit, $ref,
    )]);

    return $id->value;
}

/** @return array{ref: string, voice: string, url: null, duration_ms: null} */
function sjAudio(string $ref): array
{
    return ['ref' => $ref, 'voice' => str_ends_with($ref, 'b') || str_starts_with($ref, 'p') ? 'learner' : 'partner', 'url' => null, 'duration_ms' => null];
}

/**
 * The frame of the fake lesson as the registry deals it.
 *
 * @param  list<array{0: string, 1: string}>  $fillers  target, native
 * @return array<string, mixed>
 */
function sjFrame(string $ref, string $target, string $native, ?string $hint, array $fillers): array
{
    return [
        'ref' => $ref,
        'kind' => 'answer',
        'frame_target' => $target,
        'frame_native' => $native,
        'frame_pronunciation_native' => '',
        'slot' => $hint === null ? null : [
            'hint_native' => $hint,
            'fillers' => array_map(static fn (int $i, array $f): array => [
                'index' => $i, 'target' => $f[0], 'native' => $f[1], 'pronunciation_native' => '', 'in_dialogue' => $i === 0,
                'native_line' => $f[1], 'audio' => sjAudio("{$ref}.f".($i + 1)),
            ], array_keys($fillers), $fillers),
        ],
    ];
}

/** @return array<string, mixed> `speak_answer` on exchange 1 — «It hurts in his ___.», four frame words, coverage 0.7 */
function sjSpeakAnswer(): array
{
    $frame = sjFrame('p1', 'It hurts in his ___.', 'У него болит ___.', 'где болит', [['lower back', 'поясница'], ['neck', 'шея'], ['shoulder', 'плечо']]);

    return [
        'exchange' => ['ref' => 'x1', 'step' => 1, 'kind' => 'answer'],
        'partner_line' => ['ref' => 'x1', 'text_target' => 'Where does it hurt: his upper back or his lower back?', 'text_native' => 'Где болит: вверху спины или в пояснице?', 'audio' => sjAudio('x1')],
        'own_line' => ['ref' => 'x1b', 'text_target' => 'It hurts in his lower back.', 'text_native' => 'У него болит поясница.', 'frame_ref' => 'p1', 'filler_index' => 0, 'key' => 'It hurts', 'audio' => sjAudio('x1b')],
        'task_native' => 'У него болит поясница.',
        'frame' => $frame,
        'key' => 'It hurts',
        'speech_mode' => 'free',
        'hint' => 'It hurts in his ___.',
        'judge' => true,
    ];
}

/** @return array<string, mixed> «Скажи целиком» of «It started ___.» — its own-word round is what the judge rules on */
function sjOwnSlot(): array
{
    $frame = sjFrame('p2', 'It started ___.', 'Началось ___.', 'когда', [['three days ago', 'три дня назад'], ['last night', 'вчера вечером'], ['this morning', 'сегодня утром']]);

    return [
        'frame' => $frame,
        'partner_line' => ['ref' => 'x2', 'text_target' => 'Did it start today, or earlier this week?', 'text_native' => 'Началось сегодня или раньше на этой неделе?', 'audio' => sjAudio('x2')],
        'key' => 'It started',
        'rounds' => [['filler_index' => 0, 'expected_text' => 'It started three days ago.', 'task_native' => 'Началось три дня назад.']],
        'speech_mode' => 'repeat',
        'own_round' => [
            'task_native' => 'Началось ___.',
            'examples' => ['три дня назад', 'вчера вечером', 'сегодня утром'],
            'speech_mode' => 'free',
            'judge' => true,
        ],
    ];
}

/** @return \Illuminate\Testing\TestResponse<\Symfony\Component\HttpFoundation\Response> */
function sjJudge(object $ctx, array $day, string $cardId, ?string $heard, bool $hinted = false, int $number = 1): Illuminate\Testing\TestResponse
{
    return $ctx->withHeader('Authorization', "Bearer {$day['token']}")
        ->postJson("/api/v1/plans/{$day['plan']}/days/{$number}/cards/{$cardId}/judge", ['heard' => $heard, 'hinted' => $hinted]);
}

/** @return array<string, mixed> the stored response of a card, keys sorted — jsonb keeps no key order */
function sjStored(string $cardId): array
{
    return sjSorted(json_decode((string) DB::table('day_cards')->where('id', $cardId)->value('response'), true, flags: JSON_THROW_ON_ERROR));
}

/**
 * @param  array<array-key, mixed>  $value
 * @return array<array-key, mixed>
 */
function sjSorted(array $value): array
{
    ksort($value);

    return array_map(static fn (mixed $v): mixed => is_array($v) ? sjSorted($v) : $v, $value);
}

function sjUnavailableHits(): int
{
    return (int) DB::table('plan_check_counters')
        ->where('prompt_version', 'slot_judge.v2')->where('check_name', 'judge.unavailable')->where('action', 'counted')
        ->value('hits');
}

/** The judge model's port as the catalogue hands it out: it records every call and always rules «accepted, knee». */
function sjJudgePort(): ContentModelPort
{
    return new class implements ContentModelPort
    {
        /** @var list<array{prompt: RenderedPrompt, user: string, schema: array<string, mixed>}> */
        public array $calls = [];

        public function provider(): ProviderId
        {
            return ProviderId::OpenAi;
        }

        public function model(): string
        {
            return 'gpt-5.4-mini';
        }

        public function complete(RenderedPrompt $prompt, string $userMessage, array $schema): ModelAnswer
        {
            $this->calls[] = ['prompt' => $prompt, 'user' => $userMessage, 'schema' => $schema];

            return new ModelAnswer(['accepted' => true, 'slot_value' => 'knee', 'reason_native' => null], 'gpt-5.4-mini-2026', 812, 420, 18, '0.000150', '{}');
        }
    };
}

/** The one test double of the catalogue: it records how the plan asks for the judge's model. */
function sjJudgeCatalog(ContentModelPort $port): ContentModelCatalog
{
    return new class($port) implements ContentModelCatalog
    {
        /** @var list<array<string, mixed>> */
        public array $asked = [];

        public function __construct(private readonly ContentModelPort $port) {}

        public function availability(): array
        {
            return [];
        }

        public function available(): array
        {
            return [$this->port];
        }

        public function get(ProviderId $provider, ?string $model = null, ?string $purpose = null, ?int $timeoutSeconds = null, ?int $retries = null, ?string $journalPurpose = null): ?ContentModelPort
        {
            $this->asked[] = ['provider' => $provider, 'model' => $model, 'purpose' => $purpose, 'timeout' => $timeoutSeconds, 'retries' => $retries, 'journal' => $journalPurpose];

            return $this->port;
        }
    };
}

it('rejects by code an attempt without the frame: no model, no quota, the card open with one attempt', function () {
    config(['plan.slot_judge.daily_cap' => 1]);
    $day = sjOpenDay($this);
    $card = sjDeal($day, CardKind::SpeakAnswer, sjSpeakAnswer(), UnitKind::Exchange, 'x1');

    $data = sjJudge($this, $day, $card, 'lower back')->assertOk()->json('data');

    expect($data['accepted'])->toBeFalse()
        ->and($data['slot_value'])->toBeNull()
        ->and($data['reason_native'])->toBe('Каркас не прозвучал — скажи его целиком')
        ->and($data['result'])->toBeNull()
        ->and($data['attempts'])->toBe(1)
        ->and($data['card']['id'])->toBe($card)
        ->and($data['card']['response']['judge']['by'])->toBe('code')
        ->and($day['model']->slotJudgeCalls)->toBe(0)
        ->and(sjStored($card))->toBe(sjSorted([
            'heard' => 'lower back',
            'slot_value' => null,
            'hinted' => false,
            'judge' => [
                'accepted' => false, 'reason_native' => 'Каркас не прозвучал — скажи его целиком', 'by' => 'code',
                'model' => null, 'prompt_version' => null, 'cost_usd' => null, 'latency_ms' => null, 'tokens_in' => null, 'tokens_out' => null,
            ],
        ]));

    // Silence is an attempt too — and no quota was spent by either: the one call of today still reaches the model.
    expect(sjJudge($this, $day, $card, '')->assertOk()->json('data.attempts'))->toBe(2);
    $asked = sjJudge($this, $day, $card, 'It hurts in his left knee')->assertOk()->json('data');
    expect($asked['accepted'])->toBeTrue()
        ->and($asked['attempts'])->toBe(3)
        ->and($day['model']->slotJudgeCalls)->toBe(1)
        ->and(sjStored($card)['judge']['by'])->toBe('model');
});

it('accepts by code a value the lesson knows, said in the frame, without asking the model', function () {
    $day = sjOpenDay($this);
    $answer = sjDeal($day, CardKind::SpeakAnswer, sjSpeakAnswer(), UnitKind::Exchange, 'x1');
    $own = sjDeal($day, CardKind::PhraseOtherSlot, sjOwnSlot(), UnitKind::Phrase, 'p2');

    $data = sjJudge($this, $day, $answer, 'It hurts in his neck.')->assertOk()->json('data');
    expect($data)->toMatchArray(['accepted' => true, 'slot_value' => 'neck', 'reason_native' => null, 'result' => 'passed', 'attempts' => 1])
        ->and(sjStored($answer)['judge']['by'])->toBe('code')
        ->and(sjStored($answer)['slot_value'])->toBe('neck');

    // A value of several words, heard as one run inside a longer answer. The card is «Скажи целиком»: the verdict is
    // its own-word round's and answers nothing — the result is the client's, from the value rounds (наряд FIX-2, п. 5).
    $data = sjJudge($this, $day, $own, 'it started last night I think')->assertOk()->json('data');
    expect($data)->toMatchArray(['accepted' => true, 'slot_value' => 'last night', 'result' => null])
        ->and($day['model']->slotJudgeCalls)->toBe(0);
});

it('accepts by code a frame without a slot once its words are heard — no model, no quota, judged by code on both kinds', function () {
    config(['plan.slot_judge.daily_cap' => 1]);
    $day = sjOpenDay($this);
    // «He doesn't have a fever.» — a frame with no window: four words besides the article, coverage 0.7.
    $slotless = sjFrame('p4', "He doesn't have a fever.", 'Температуры у него нет.', null, []);
    $answer = sjDeal($day, CardKind::SpeakAnswer, [
        ...sjSpeakAnswer(),
        'exchange' => ['ref' => 'x4', 'step' => 4, 'kind' => 'answer'],
        'own_line' => ['ref' => 'x4b', 'text_target' => "He doesn't have a fever.", 'text_native' => 'Температуры у него нет.', 'frame_ref' => 'p4', 'filler_index' => null, 'key' => "He doesn't have a fever", 'audio' => sjAudio('x4b')],
        'task_native' => 'Температуры у него нет.',
        'frame' => $slotless,
        'key' => "He doesn't have a fever",
        'hint' => "He doesn't have a fever.",
    ], UnitKind::Exchange, 'x4');
    $own = sjDeal($day, CardKind::PhraseOtherSlot, [
        ...sjOwnSlot(),
        'frame' => $slotless,
        'key' => "He doesn't have a fever",
        'rounds' => [],
        'own_round' => ['task_native' => 'Температуры у него нет.', 'examples' => [], 'speech_mode' => 'free', 'judge' => true],
    ], UnitKind::Phrase, 'p4');
    $byCode = static fn (bool $accepted, ?string $reason): array => sjSorted([
        'accepted' => $accepted, 'reason_native' => $reason, 'by' => 'code',
        'model' => null, 'prompt_version' => null, 'cost_usd' => null, 'latency_ms' => null, 'tokens_in' => null, 'tokens_out' => null,
    ]);

    // The frame's words not heard: rejected by code, the card open — a frame without a slot is still a frame to say.
    $missed = sjJudge($this, $day, $answer, 'fever')->assertOk()->json('data');
    expect($missed)->toMatchArray(['accepted' => false, 'slot_value' => null, 'reason_native' => 'Каркас не прозвучал — скажи его целиком', 'result' => null, 'attempts' => 1])
        ->and(sjStored($answer)['judge'])->toBe($byCode(false, 'Каркас не прозвучал — скажи его целиком'));

    // Heard: nothing is left to judge — accepted by code, no slot value.
    $passed = sjJudge($this, $day, $answer, 'no, he doesn\'t have a fever')->assertOk()->json('data');
    expect($passed)->toMatchArray(['accepted' => true, 'slot_value' => null, 'reason_native' => null, 'result' => 'passed', 'attempts' => 2])
        ->and($passed['card']['response']['judge']['by'])->toBe('code')
        ->and(sjStored($answer)['slot_value'])->toBeNull()
        ->and(sjStored($answer)['judge'])->toBe($byCode(true, null));

    $ownPassed = sjJudge($this, $day, $own, "He doesn't have a fever, doctor.")->assertOk()->json('data');
    expect($ownPassed)->toMatchArray(['accepted' => true, 'slot_value' => null, 'reason_native' => null, 'result' => null, 'attempts' => 1])
        ->and(sjStored($own)['judge'])->toBe($byCode(true, null))
        ->and($day['model']->slotJudgeCalls)->toBe(0)
        ->and(sjUnavailableHits())->toBe(0);

    // No quota was spent: today's one call still reaches the model on a card with a slot.
    $withSlot = sjDeal($day, CardKind::SpeakAnswer, sjSpeakAnswer(), UnitKind::Exchange, 'x1');
    sjJudge($this, $day, $withSlot, 'It hurts in his left knee')->assertOk();
    expect($day['model']->slotJudgeCalls)->toBe(1)
        ->and(sjStored($withSlot)['judge']['by'])->toBe('model');
});

it('asks the model about a value the lesson does not know — with the inputs of the card — and a hinted speak_answer passes as hinted', function () {
    $day = sjOpenDay($this, static fn (SlotJudgeRequest $r): array => ['accepted' => true, 'slot_value' => 'left knee', 'reason_native' => 'лишнее']);
    $card = sjDeal($day, CardKind::SpeakAnswer, sjSpeakAnswer(), UnitKind::Exchange, 'x1');

    $data = sjJudge($this, $day, $card, 'It hurts in his  left knee', hinted: true)->assertOk()->json('data');

    expect($data)->toMatchArray(['accepted' => true, 'slot_value' => 'left knee', 'reason_native' => null, 'result' => 'hinted', 'attempts' => 1])
        ->and($data['card']['result'])->toBe('hinted')
        ->and($day['model']->slotJudgeCalls)->toBe(1);

    $request = $day['model']->slotJudgeRequests[0];
    expect($request->targetLanguage)->toBe('English')
        ->and($request->nativeLanguage)->toBe('Russian')
        ->and($request->level)->toBe('intermediate')
        ->and($request->partnerLine)->toBe('Where does it hurt: his upper back or his lower back?')
        ->and($request->partnerLineNative)->toBe('Где болит: вверху спины или в пояснице?')
        ->and($request->pattern)->toBe('It hurts in his ___.')
        ->and($request->patternNative)->toBe('У него болит ___.')
        ->and($request->slotHint)->toBe('где болит')
        ->and($request->exampleValues)->toBe('lower back; neck; shoulder')
        ->and($request->heard)->toBe('It hurts in his  left knee');

    expect(sjStored($card))->toBe(sjSorted([
        'heard' => 'It hurts in his  left knee',
        'slot_value' => 'left knee',
        'hinted' => true,
        'judge' => [
            'accepted' => true, 'reason_native' => null, 'by' => 'model', 'model' => FakePlanModel::MODEL, 'prompt_version' => 'slot_judge.v2',
            'cost_usd' => '0.000000', 'latency_ms' => 1, 'tokens_in' => 350, 'tokens_out' => 40,
        ],
    ]));
});

it('ignores hinted on «Скажи целиком» — its frame is always on screen — and records the verdict without closing the card', function () {
    $day = sjOpenDay($this, static fn (SlotJudgeRequest $r): array => ['accepted' => true, 'slot_value' => 'yesterday', 'reason_native' => null]);
    $card = sjDeal($day, CardKind::PhraseOtherSlot, sjOwnSlot(), UnitKind::Phrase, 'p2');

    $data = sjJudge($this, $day, $card, 'It started yesterday', hinted: true)->assertOk()->json('data');

    expect($data)->toMatchArray(['accepted' => true, 'slot_value' => 'yesterday', 'result' => null, 'attempts' => 1])
        ->and(DB::table('day_cards')->where('id', $card)->value('result'))->toBeNull()
        ->and(sjStored($card)['hinted'])->toBeFalse()
        ->and($day['model']->slotJudgeRequests[0]->pattern)->toBe('It started ___.')
        ->and($day['model']->slotJudgeRequests[0]->partnerLine)->toBe('Did it start today, or earlier this week?')
        ->and($day['model']->slotJudgeRequests[0]->exampleValues)->toBe('three days ago; last night; this morning');
});

it('keeps the card open when the model rejects: the reason comes back, attempts grow, the next pass answers it', function () {
    $day = sjOpenDay($this, static fn (SlotJudgeRequest $r, int $call): array => $call === 1
        ? ['accepted' => false, 'slot_value' => 'banana', 'reason_native' => 'Ты не сказал, где болит.']
        : ['accepted' => true, 'slot_value' => 'knee', 'reason_native' => null]);
    $card = sjDeal($day, CardKind::SpeakAnswer, sjSpeakAnswer(), UnitKind::Exchange, 'x1');

    $rejected = sjJudge($this, $day, $card, 'It hurts in his banana')->assertOk()->json('data');
    expect($rejected)->toMatchArray(['accepted' => false, 'slot_value' => 'banana', 'reason_native' => 'Ты не сказал, где болит.', 'result' => null, 'attempts' => 1])
        ->and(DB::table('day_cards')->where('id', $card)->value('result'))->toBeNull()
        ->and(DB::table('day_cards')->where('id', $card)->value('answered_at'))->toBeNull()
        ->and(sjStored($card)['judge'])->toMatchArray(['accepted' => false, 'by' => 'model', 'reason_native' => 'Ты не сказал, где болит.']);

    $passed = sjJudge($this, $day, $card, 'It hurts in his knee')->assertOk()->json('data');
    expect($passed)->toMatchArray(['accepted' => true, 'result' => 'passed', 'attempts' => 2])
        ->and($day['model']->slotJudgeCalls)->toBe(2)
        ->and(sjUnavailableHits())->toBe(0);
});

it('accepts on the code\'s word when the model is silent or off the shape, the words beyond the frame as the slot, and counts judge.unavailable', function () {
    $day = sjOpenDay($this, static function (SlotJudgeRequest $r, int $call): array {
        if ($call === 1) {
            throw new RuntimeException('OpenAI API error: 504 timeout');
        }

        return ['accepted' => 'yes', 'slot_value' => 'knee', 'reason_native' => null];
    });
    $silent = sjDeal($day, CardKind::SpeakAnswer, sjSpeakAnswer(), UnitKind::Exchange, 'x1');
    $offShape = sjDeal($day, CardKind::PhraseOtherSlot, sjOwnSlot(), UnitKind::Phrase, 'p2');

    $data = sjJudge($this, $day, $silent, 'It hurts, in his left knee!')->assertOk()->json('data');
    expect($data)->toMatchArray(['accepted' => true, 'slot_value' => 'left knee', 'reason_native' => null, 'result' => 'passed', 'attempts' => 1])
        ->and(sjStored($silent)['judge'])->toBe(sjSorted([
            'accepted' => true, 'reason_native' => null, 'by' => 'unavailable',
            'model' => null, 'prompt_version' => null, 'cost_usd' => null, 'latency_ms' => null, 'tokens_in' => null, 'tokens_out' => null,
        ]))
        ->and(sjUnavailableHits())->toBe(1);

    $data = sjJudge($this, $day, $offShape, 'it started on monday')->assertOk()->json('data');
    expect($data)->toMatchArray(['accepted' => true, 'slot_value' => 'on monday', 'result' => null])
        ->and(sjStored($offShape)['judge']['by'])->toBe('unavailable')
        ->and($day['model']->slotJudgeCalls)->toBe(2)
        ->and(sjUnavailableHits())->toBe(2);
});

it('judges only the judged kinds, only an open card on a day in progress, only the learner\'s own plan', function () {
    $day = sjOpenDay($this);
    $choose = sjDeal($day, CardKind::WordChoose, [
        'direction' => 'native_to_term',
        'prompt' => ['text_native' => 'поясница', 'image' => ['url' => null, 'tone' => null]],
        'options' => [['id' => 'o1', 'text' => 'lower back', 'audio' => sjAudio('v1')], ['id' => 'o2', 'text' => 'fever', 'audio' => sjAudio('v3')]],
        'correct' => 'o1',
    ], UnitKind::Word, 'v1');
    $answer = sjDeal($day, CardKind::SpeakAnswer, sjSpeakAnswer(), UnitKind::Exchange, 'x1');
    $retell = sjDeal($day, CardKind::SpeakRetell, [
        'exchange' => ['ref' => 'x5', 'step' => 5, 'kind' => 'answer'],
        'own_line' => ['ref' => 'x5b', 'text_target' => 'It started two days ago.', 'text_native' => 'Началось два дня назад.', 'frame_ref' => 'p2', 'filler_index' => 0, 'key' => 'It started', 'audio' => sjAudio('x5b')],
        'expected_text' => 'It started two days ago.',
        'coverage_min' => 0.7,
    ], UnitKind::Exchange, 'x5');

    sjJudge($this, $day, $choose, 'lower back')->assertStatus(422)
        ->assertJsonPath('code', 'plan_card_not_judged')
        ->assertJsonPath('meta.kind', 'word_choose');

    // «Повтори свою реплику» left the judge with `speak_retell` itself (наряд BACK-TAILS-1 §1.1): a call on it would be
    // a paid verdict on a coverage the client already counted.
    sjJudge($this, $day, $retell, 'it started two days ago')->assertStatus(422)
        ->assertJsonPath('code', 'plan_card_not_judged')
        ->assertJsonPath('meta.kind', 'speak_retell');

    // The body: `heard` present (null is silence), `hinted` a boolean.
    $this->withHeader('Authorization', "Bearer {$day['token']}")
        ->postJson("/api/v1/plans/{$day['plan']}/days/1/cards/{$answer}/judge", ['heard' => 'x'])
        ->assertStatus(422)->assertJsonValidationErrors(['hinted']);
    $this->withHeader('Authorization', "Bearer {$day['token']}")
        ->postJson("/api/v1/plans/{$day['plan']}/days/1/cards/{$answer}/judge", ['hinted' => false])
        ->assertStatus(422)->assertJsonValidationErrors(['heard']);

    // An answered card takes no second verdict.
    sjJudge($this, $day, $answer, 'It hurts in his neck')->assertOk()->assertJsonPath('data.result', 'passed');
    sjJudge($this, $day, $answer, 'It hurts in his shoulder')->assertStatus(409)->assertJsonPath('code', 'plan_card_answered');

    // A day not in progress — day 2 is still locked.
    sjJudge($this, $day, $answer, 'It hurts in his neck', number: 2)->assertStatus(409)->assertJsonPath('code', 'plan_day_not_open');

    // A card id that is not a card of the day.
    sjJudge($this, $day, DayCardId::generate()->value, 'It hurts in his neck')->assertStatus(404)->assertJsonPath('code', 'plan_card_not_found');

    // Another learner's plan is not there at all.
    [, $other] = planLearner();
    app('auth')->forgetGuards();
    sjJudge($this, ['token' => $other, 'plan' => $day['plan']], $answer, 'It hurts in his neck')->assertStatus(404)->assertJsonPath('code', 'plan_not_found');

    expect($day['model']->slotJudgeCalls)->toBe(0);
});

it('stops asking the model past the day\'s cap: the next call never reaches it and the verdict is the code\'s', function () {
    config(['plan.slot_judge.daily_cap' => 2]);
    $day = sjOpenDay($this, static fn (SlotJudgeRequest $r): array => ['accepted' => false, 'slot_value' => 'banana', 'reason_native' => 'Не то.']);
    $card = sjDeal($day, CardKind::SpeakAnswer, sjSpeakAnswer(), UnitKind::Exchange, 'x1');

    sjJudge($this, $day, $card, 'It hurts in his banana')->assertOk()->assertJsonPath('data.accepted', false);
    sjJudge($this, $day, $card, 'It hurts in his apple')->assertOk()->assertJsonPath('data.accepted', false);
    $third = sjJudge($this, $day, $card, 'It hurts in his left knee')->assertOk()->json('data');

    expect($day['model']->slotJudgeCalls)->toBe(2)
        ->and($third)->toMatchArray(['accepted' => true, 'slot_value' => 'left knee', 'result' => 'passed', 'attempts' => 3])
        ->and(sjStored($card)['judge']['by'])->toBe('unavailable')
        ->and(sjUnavailableHits())->toBe(1);
});

it('rules outside the transaction: no lock is held while the model thinks', function () {
    $baseline = DB::transactionLevel();
    $levels = [];
    $day = sjOpenDay($this, static function (SlotJudgeRequest $r) use (&$levels): array {
        $levels[] = DB::transactionLevel();

        return ['accepted' => true, 'slot_value' => 'knee', 'reason_native' => null];
    });
    $card = sjDeal($day, CardKind::SpeakAnswer, sjSpeakAnswer(), UnitKind::Exchange, 'x1');

    sjJudge($this, $day, $card, 'It hurts in his knee')->assertOk();

    // The ruling was asked at the test's own level (RefreshDatabase's transaction), not inside the handler's.
    expect($levels)->toBe([$baseline]);
});

it('builds the call on the judge model with one attempt, its own timeout, the strict schema, the file as the system side, and logs its price', function () {
    $port = sjJudgePort();
    $catalog = sjJudgeCatalog($port);
    Log::spy();

    $builder = new ContentModelPlanBuilder(
        catalog: $catalog,
        prompts: new PlanPromptFiles,
        provider: ProviderId::OpenAi,
        planModel: 'gpt-5.4',
        lessonModel: 'gpt-5.4',
        planTimeout: 90,
        lessonTimeout: 90,
        repairModel: 'gpt-5.4',
        judgeModel: 'gpt-5.4-mini',
        slotJudgeTimeout: 8,
    );
    $request = new SlotJudgeRequest('English', 'Russian', 'intermediate', 'Where?', 'Где?', 'It hurts in his ___.', 'У него болит ___.', 'где болит', 'neck; shoulder', 'It hurts in his knee');
    $reply = $builder->judgeSlot($request);

    $system = (new PlanPromptFiles)->slotJudgeSystem();
    expect($catalog->asked)->toBe([['provider' => ProviderId::OpenAi, 'model' => 'gpt-5.4-mini', 'purpose' => 'plan', 'timeout' => 8, 'retries' => 1, 'journal' => 'judge']])
        ->and($port->calls)->toHaveCount(1)
        ->and($port->calls[0]['prompt']->text)->toBe($system)
        ->and($port->calls[0]['prompt']->version)->toBe('slot_judge.v2')
        ->and($port->calls[0]['prompt']->sha256)->toBe(hash('sha256', $system))
        ->and($port->calls[0]['prompt']->shape)->toBe(PromptShape::Full)
        ->and($port->calls[0]['schema'])->toBe(PlanSchemas::slotJudge())
        ->and($port->calls[0]['user'])->toBe((new PlanPromptFiles)->slotJudgeUser($request))
        ->and($reply->payload)->toBe(['accepted' => true, 'slot_value' => 'knee', 'reason_native' => null])
        ->and($reply->promptVersion)->toBe('slot_judge.v2')
        ->and($reply->model)->toBe('gpt-5.4-mini-2026')
        ->and($reply->costUsd)->toBe('0.000150')
        ->and($reply->latencyMs)->toBe(812)
        ->and($builder->slotJudgePromptVersion())->toBe('slot_judge.v2');

    Log::shouldHaveReceived('info')->withArgs(static fn (string $message, array $context): bool => $message === 'plan.slot_judge'
        && $context === ['prompt_version' => 'slot_judge.v2', 'model' => 'gpt-5.4-mini-2026', 'tokens_in' => 420, 'tokens_out' => 18, 'cost_usd' => '0.000150', 'latency_ms' => 812])->once();
});

it('threads plan.slot_judge.timeout from config into the builder the container makes', function () {
    config(['plan.model.driver' => 'openai', 'plan.slot_judge.timeout' => 5]);
    $port = sjJudgePort();
    $catalog = sjJudgeCatalog($port);
    app()->instance(ContentModelCatalog::class, $catalog);
    app()->forgetInstance(PlanModelPort::class);

    $builder = app(PlanModelPort::class);
    $builder->judgeSlot(new SlotJudgeRequest('answer', 'English', 'Russian', 'intermediate', 'Where?', 'Где?', 'It hurts in his ___.', 'У него болит ___.', 'где болит', 'neck', 'It hurts in his knee'));

    expect($builder)->toBeInstanceOf(ContentModelPlanBuilder::class)
        ->and($catalog->asked)->toHaveCount(1)
        ->and($catalog->asked[0]['timeout'])->toBe(5)
        ->and($catalog->asked[0]['retries'])->toBe(1);
});

it('lets a caller of the content catalogue ask for one attempt, and keeps the escalating four for everyone else (D-28)', function () {
    allowLiveAdapters();
    config(['services.openai.api_key' => 'test-key']);
    Sleep::fake();
    Http::fake(['api.openai.com/*' => Http::response('busy', 503)]);
    $prompt = new RenderedPrompt('rules', 'slot_judge.v2', PromptShape::Full, hash('sha256', 'rules'));
    $schema = PlanSchemas::slotJudge();

    $once = app(ContentModelCatalog::class)->get(ProviderId::OpenAi, 'gpt-5.4-mini', 'plan', 8, 1);
    expect(fn () => $once?->complete($prompt, 'HEARD: x', $schema))->toThrow(RuntimeException::class);
    Http::assertSentCount(1);

    $default = app(ContentModelCatalog::class)->get(ProviderId::OpenAi, 'gpt-5.4-mini', 'plan', 8);
    expect(fn () => $default?->complete($prompt, 'HEARD: x', $schema))->toThrow(RuntimeException::class);
    Http::assertSentCount(5);
});

it('counts the quota per learner per LOCAL day, and a refused take counts nothing', function () {
    $quota = new ArraySlotJudgeQuota;
    $ann = UserId::fromString(Ulid::generate());
    $bob = UserId::fromString(Ulid::generate());
    // 23:30 UTC is already the next day in Kyiv.
    $late = new DateTimeImmutable('2026-09-16T23:30:00Z');
    $kyiv = new DateTimeZone('Europe/Kyiv');
    $utc = new DateTimeZone('UTC');

    expect($quota->take($ann, $late, $utc, 2))->toBeTrue()
        ->and($quota->take($ann, $late, $utc, 2))->toBeTrue()
        ->and($quota->take($ann, $late, $utc, 2))->toBeFalse()
        ->and($quota->take($bob, $late, $utc, 2))->toBeTrue()
        // The same instant in Kyiv is the 17th — a day of its own.
        ->and($quota->take($ann, $late, $kyiv, 2))->toBeTrue()
        ->and($quota->take($ann, new DateTimeImmutable('2026-09-17T00:10:00Z'), $utc, 2))->toBeTrue()
        ->and($quota->take($ann, $late, $utc, 0))->toBeFalse();
});

it('keeps the Redis quota under the learner\'s local date, expiring at their midnight, and gives a refused take back', function () {
    try {
        Redis::connection('cache')->command('ping');
    } catch (Throwable $e) {
        $this->markTestSkipped('Redis is not reachable here: '.$e->getMessage());
    }
    $user = UserId::fromString(Ulid::generate());
    // A zone far from UTC, so a key dated by the server's day instead of the learner's would be caught most hours.
    $zone = new DateTimeZone('Pacific/Kiritimati');
    // The real clock: EXPIREAT takes a real timestamp, and a key dated in the past would be gone before it is read.
    $now = new DateTimeImmutable('now');
    $local = $now->setTimezone($zone);
    $key = "plan:slot_judge:{$user->value}:{$local->format('Y-m-d')}";
    $redis = Redis::connection('cache');

    try {
        $quota = new RedisSlotJudgeQuota;
        expect($quota->take($user, $now, $zone, 2))->toBeTrue()
            ->and($quota->take($user, $now, $zone, 2))->toBeTrue()
            ->and($quota->take($user, $now, $zone, 2))->toBeFalse()
            ->and((int) $redis->command('get', [$key]))->toBe(2);

        $expiresAt = $local->setTime(0, 0)->modify('+1 day')->getTimestamp();
        $ttl = (int) $redis->command('ttl', [$key]);
        $expected = $expiresAt - time();
        expect($ttl)->toBeGreaterThan($expected - 5)->toBeLessThanOrEqual($expected + 1);
    } finally {
        $redis->command('del', [$key]);
    }
});

<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelPort;
use Tests\Doubles\RecordingPlanDefectReporter;
use App\Modules\Generation\Application\Port\RecordsPlanSpend;
use App\Modules\Generation\Application\Service\PlanDayComposer;
use App\Modules\Generation\Application\Service\PlanOutlineService;
use App\Modules\Generation\Domain\Service\PlanDayValidator;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Generation\Infrastructure\Prompt\PlanPromptLibrary;
use App\Modules\Learning\Application\Port\PlanOutlinePort;
use App\Modules\Learning\Domain\Entity\PlanDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * TWO ATTEMPTS MEANS TWO PAID CALLS, and the second one is told WHERE the first broke.
 *
 * `PlanDayComposer` used to make a second call INSIDE one claim, on top of the second claim
 * `FinishPlanDayHandler` schedules. One day was four paid calls, all four inside 55 seconds,
 * against a наряд budget written for two (`docs/research/plan-v0.2.1-run.md`). That is fixed and
 * measured here: one claim, one call, at most two claims.
 *
 * What the second call is TOLD changed again in v0.3.1, and against the previous наряд's own
 * conclusion. The retry used to carry every attempt's violations, each quoting the card it was
 * about — and the third live call answered with those quoted cards, defects and all
 * (`docs/research/plan-v0.3-run.md`, второй заход). A worked example of a wrong answer is an
 * example first. So the retry now carries the LAST attempt's violations as ADDRESSES, and this
 * test asserts the message contains nothing the model wrote.
 */
/**
 * Every string of substance the model wrote in a day answer — the three card arrays, flattened.
 *
 * Short values are dropped on purpose: `learner`, `phrase`, `word` are the schema's own vocabulary
 * and appear in any prompt about a day, so asserting their absence would assert nothing and fail
 * for the wrong reason.
 *
 * @param  array<string, mixed>  $answer
 * @return list<string>
 */
function answerStrings(array $answer): array
{
    $out = [];
    foreach (['phrases', 'words', 'chunks'] as $array) {
        foreach ($answer[$array] ?? [] as $card) {
            foreach ($card as $value) {
                if (is_string($value) && mb_strlen($value) >= 12) {
                    $out[] = $value;
                }
            }
        }
    }

    return $out;
}

beforeEach(function (): void {
    /** @var array<string, mixed> $day */
    $day = json_decode(
        (string) file_get_contents(__DIR__ . '/../../Fixtures/plan/s1-day1.v0.3.json'),
        true,
    );

    // TWO DIFFERENT DEFECTS, one per answer, each breaking exactly one gate. Different on purpose:
    // «the second attempt was told about A» is only observable when the second attempt fails on B.
    $withoutPicture = $day;
    $withoutPicture['words'][0]['image_api_prompt'] = '';
    // …and, on the same answer, a SHAPE defect that is only ever a warning: the day's one question
    // and its one repair move are the same line, and this replaces it with a statement. A refused
    // answer is thrown away, so the log is the only place that fact can survive.
    $withoutPicture['phrases'][3]['frame'] = 'I would like to check in, please.';

    $withStrayFiller = $day;
    $withStrayFiller['phrases'][1]['filler'] = 'ten past nine';   // no card of this day says that

    $this->firstAnswer = $withoutPicture;

    $this->model = new class([$withoutPicture, $withStrayFiller]) implements ContentModelPort
    {
        /** @var list<string> */
        public array $dayMessages = [];

        /** @param list<array<string, mixed>> $days */
        public function __construct(private array $days) {}

        public function provider(): ProviderId
        {
            return ProviderId::OpenAi;
        }

        public function model(): string
        {
            return 'scripted-plan';
        }

        public function complete(RenderedPrompt $prompt, string $userMessage, array $schema): ModelAnswer
        {
            $properties = $schema['properties'] ?? [];
            $isDay = is_array($properties) && isset($properties['phrases']);

            if ($isDay) {
                $this->dayMessages[] = $userMessage;
                $payload = array_shift($this->days) ?? [];
            } else {
                /** @var array<string, mixed> $payload */
                $payload = json_decode(
                    (string) file_get_contents(__DIR__ . '/../../Fixtures/plan/s1-outline.v0.2.json'),
                    true,
                );
            }

            return new ModelAnswer(
                payload: $payload,
                model: 'scripted-plan',
                latencyMs: 0,
                tokensIn: 100,
                tokensOut: 200,
                costUsd: '0.047000',
                raw: '{}',
            );
        }
    };

    $this->defects = new RecordingPlanDefectReporter();

    $prompts = new PlanPromptLibrary();
    $ledger = app(RecordsPlanSpend::class);

    app()->instance(PlanOutlinePort::class, new PlanOutlineService($this->model, $prompts, $ledger, $this->defects));
    app()->instance(PlanDayComposer::class, new PlanDayComposer(
        $this->model,
        $prompts,
        $ledger,
        $this->defects,
    ));
});

it('spends exactly two calls on a day that fails twice, and tells the second one about the first', function () {
    [$user, $token] = learner();
    profileFor($user, ['native_language' => 'ru', 'target_language' => 'en']);

    $plan = $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/v1/plans', [
            'goal_text' => 'Иду к врачу, болит спина, надо объяснить и понять назначение',
            'target_lang' => 'en',
            'level' => 'basic',
            'event_date' => now()->addDays(2)->format('Y-m-d'),
            'minutes_per_day' => 20,
        ])->assertCreated()->json('data');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/outline")->assertOk();
    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson("/api/v1/plans/{$plan['id']}/start")->assertOk();

    // TWO CALLS FOR THE DAY. Not four: the composer's own inner retry is gone, so the claim
    // counter on the row is the money.
    expect($this->model->dayMessages)->toHaveCount(2);

    $row = DB::table('learning_plan_days')
        ->where('plan_id', $plan['id'])->where('day_index', 1)->first();

    expect($row->status)->toBe('failed')
        ->and($row->generation_attempts)->toBe(PlanDay::MAX_ATTEMPTS)
        ->and($row->collection_id)->toBeNull();

    // The first call carries the day and nothing else; the second carries the first answer's
    // verdict — as ADDRESSES.
    expect($this->model->dayMessages[0])->not->toContain('PREVIOUS ANSWER')
        ->and($this->model->dayMessages[1])->toContain('THE PREVIOUS ANSWER TO THIS DAY FAILED')
        ->and($this->model->dayMessages[1])->toContain(PlanDayValidator::IMAGE_PROMPT_MISSING)
        // The address, in full: which array, which card, which field.
        ->and($this->model->dayMessages[1])->toContain('words[0].image_api_prompt')
        // Still the same request with one more block, not a different request.
        ->and($this->model->dayMessages[1])->toContain('"index": 1');

    // NOT ONE LINE OF THE PREVIOUS ANSWER. This is the whole point of the address form, and the
    // one thing a reading of the message cannot be trusted to check by eye: every string the first
    // answer wrote, long enough not to collide with ordinary English, must be absent from the
    // block that reports on it.
    $block = mb_substr(
        $this->model->dayMessages[1],
        (int) mb_strpos($this->model->dayMessages[1], 'THE PREVIOUS ANSWER TO THIS DAY FAILED'),
    );

    foreach (answerStrings($this->firstAnswer) as $written) {
        expect($block)->not->toContain($written);
    }

    // ONLY THE LAST VERDICT survives on the row. It accumulated for one наряд; the live run showed
    // that a growing list of quoted defects is a growing example to copy (п. 199, вторая половина,
    // отменена).
    /** @var list<string> $violations */
    $violations = json_decode((string) $row->generation_violations, true);
    $joined = implode(' ', $violations);

    expect($joined)->toContain(PlanDayValidator::FILLER_NOT_A_CARD)
        ->and($joined)->not->toContain(PlanDayValidator::IMAGE_PROMPT_MISSING);

    // TWO ROWS IN THE LEDGER for the day, both refused, each carrying its own reason. A day that
    // cost twice reads as two rows rather than as one that mysteriously cost double.
    $spend = DB::table('generation_requests')
        ->where('plan_id', $plan['id'])
        ->where('prompt', 'like', 'day:%')
        ->orderBy('created_at')
        ->get();

    expect($spend)->toHaveCount(2)
        ->and($spend[0]->error)->toContain(PlanDayValidator::IMAGE_PROMPT_MISSING)
        ->and($spend[1]->error)->toContain(PlanDayValidator::FILLER_NOT_A_CARD)
        ->and($spend->pluck('purpose')->unique()->all())->toBe(['plan']);

    // THE SHAPE OF A REFUSED ANSWER IS STILL VISIBLE. The first answer had no question and no
    // repair move; that day was thrown away, so this report is the only record it ever leaves —
    // and it is NOT counted, because the counters measure weak days the learner actually got.
    $reported = array_column($this->defects->reported, 'counter');

    expect($reported)->toContain(PlanDayValidator::NO_QUESTION)
        ->and($reported)->toContain(PlanDayValidator::NO_REPAIR)
        ->and(array_column($this->defects->reported, 'counted'))->not->toContain(true)
        ->and($this->defects->warnings(PlanDayValidator::NO_QUESTION))->toBe(0);
});

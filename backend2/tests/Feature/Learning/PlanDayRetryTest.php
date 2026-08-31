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
 * TWO ATTEMPTS MEANS TWO PAID CALLS, and the second one knows everything the first got wrong.
 *
 * Both halves of this were false until v0.3, and both were measured rather than reasoned about
 * (`docs/research/plan-v0.2.1-run.md`):
 *
 * 1. `PlanDayComposer` made a second call INSIDE one claim, on top of the second claim
 *    `FinishPlanDayHandler` schedules. One day was four paid calls, all four inside 55 seconds,
 *    against a наряд budget written for two.
 * 2. Each retry was told only what the LAST answer failed. The second answer fixed those four
 *    defects and introduced five new ones; the fourth answer did the same thing again. A model
 *    told what is wrong fixes it — and re-breaks what it fixed the run before, unless it is told
 *    that too.
 *
 * So: one claim, one call, at most two claims, and the violations ACCUMULATE on the day row.
 */
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
    // verdict, as data.
    expect($this->model->dayMessages[0])->not->toContain('PREVIOUS ATTEMPT')
        ->and($this->model->dayMessages[1])->toContain('EVERY PREVIOUS ATTEMPT AT THIS DAY FAILED')
        ->and($this->model->dayMessages[1])->toContain(PlanDayValidator::IMAGE_PROMPT_MISSING)
        // Still the same request with one more block, not a different request.
        ->and($this->model->dayMessages[1])->toContain('"index": 1');

    // BOTH verdicts survive on the row, accumulated rather than replaced — that is what a THIRD
    // attempt would have been told, and what a person reading the row can see now.
    /** @var list<string> $violations */
    $violations = json_decode((string) $row->generation_violations, true);
    $joined = implode(' ', $violations);

    expect($joined)->toContain(PlanDayValidator::IMAGE_PROMPT_MISSING)
        ->and($joined)->toContain(PlanDayValidator::FILLER_NOT_A_CARD);

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

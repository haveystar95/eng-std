<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\RecordsPlanSpend;
use App\Modules\Generation\Application\Service\PlanDayComposer;
use App\Modules\Generation\Application\Service\PlanDayRepairer;
use App\Modules\Generation\Application\Service\PlanOutlineService;
use App\Modules\Generation\Domain\Service\PlanDayValidator;
use App\Modules\Generation\Infrastructure\Prompt\PlanPromptLibrary;
use Tests\Doubles\RecordingPlanDefectReporter;
use Tests\Doubles\ScriptedPlanModel;
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
        (string) file_get_contents(__DIR__ . '/../../Fixtures/plan/s1-day1.v0.4.json'),
        true,
    );

    // ELEVEN BROKEN CARDS OF EIGHTEEN, on purpose: past half the day P2R is not asked at all
    // ({@see \App\Modules\Generation\Application\Service\PlanDayRepairer}) and this is the
    // whole-day path, which is what this file is about. One broken card takes the other road and is
    // measured in `PlanDayRepairTest`.
    //
    // TWO DIFFERENT DEFECTS, one per answer. Different on purpose: «the second attempt was told
    // about A» is only observable when the second attempt fails on B.
    $withoutKeys = $day;
    foreach ([0, 1, 3] as $i) {
        $withoutKeys['say'][$i]['translation'] = 'У меня всё в порядке.';
    }
    foreach ([0, 1] as $i) {
        $withoutKeys['ask'][$i]['translation'] = 'У меня всё в порядке.';
    }
    foreach ([0, 1, 2, 3] as $i) {
        $withoutKeys['words'][$i]['example'] = $day['words'][$i]['text'];
    }
    foreach ([0, 1] as $i) {
        $withoutKeys['chunks'][$i]['example'] = $day['chunks'][$i]['text'];
    }
    // …and, on the same answer, a SHAPE defect that is only ever a warning: the day's one repair
    // move becomes an ordinary statement. A refused answer is thrown away whole, so the log is the
    // only place that fact can survive.
    $withoutKeys['say'][2]['frame'] = 'I came here on my own.';

    $withGaps = $day;
    foreach ([0, 1, 2, 3] as $i) {
        $withGaps['say'][$i]['translation'] = 'Болит в ___.';
    }
    foreach ([0, 1] as $i) {
        $withGaps['ask'][$i]['translation'] = 'Болит в ___.';
    }
    foreach ([0, 1, 2, 3] as $i) {
        $withGaps['words'][$i]['example'] = 'The pain in my ___ gets worse at night.';
    }
    foreach ([0, 1] as $i) {
        $withGaps['chunks'][$i]['example'] = 'You ___ downstairs and then come up here.';
    }

    $this->firstAnswer = $withoutKeys;
    $this->model = new ScriptedPlanModel([$withoutKeys, $withGaps]);
    $this->defects = new RecordingPlanDefectReporter();

    $prompts = new PlanPromptLibrary();
    $ledger = app(RecordsPlanSpend::class);

    app()->instance(PlanOutlinePort::class, new PlanOutlineService($this->model, $prompts, $ledger, $this->defects));
    // Wired exactly as production is, repairer and all — a composer built without one would make
    // «P2R was not called» true for the wrong reason.
    app()->instance(PlanDayComposer::class, new PlanDayComposer(
        $this->model,
        $prompts,
        $ledger,
        $this->defects,
        new PlanDayRepairer($this->model, $prompts, $ledger),
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
    expect($this->model->dayCalls())->toBe(2)
        // AND NO REPAIR CALL. Past half the day, P2R is not asked — repairing eight of fourteen
        // cards would be paying to keep the six that happened to pass.
        ->and($this->model->repairCalls())->toBe(0);

    $row = DB::table('learning_plan_days')
        ->where('plan_id', $plan['id'])->where('day_index', 1)->first();

    expect($row->status)->toBe('failed')
        ->and($row->generation_attempts)->toBe(PlanDay::MAX_ATTEMPTS)
        ->and($row->repair_calls)->toBe(0)
        ->and($row->collection_id)->toBeNull()
        // THE CODE, not the prose. The screen has to be able to say what actually broke; before
        // this it had one hard-coded sentence and used it for every failure there is (Д-19). It is
        // the FIRST fatal violation of the last attempt — read off the stored list rather than
        // named here, because that IS the contract.
        ->and($row->fail_code)->toBe(
            explode(': ', explode(' — ', (string) json_decode((string) $row->generation_violations, true)[0])[1])[0],
        );

    // …and it reaches the client, beside the status and never instead of it.
    $day = $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson("/api/v1/plans/{$plan['id']}")
        ->assertOk()
        ->json('data.days.0');

    expect($day['status'])->toBe('failed')
        ->and($day['fail_code'])->toBe($row->fail_code)
        // A code, never the Russian prose — that stays on the server (31.08).
        // A CARD code, and that is the shape of v0.4: almost every fatal verdict names a card now,
        // because a verdict a repair call can be pointed at is worth more than one about the answer.
        ->and($day['fail_code'])->toStartWith('card.');

    // The first call carries the day and nothing else; the second carries the first answer's
    // verdict — as ADDRESSES.
    expect($this->model->dayMessages[0])->not->toContain('PREVIOUS ANSWER')
        ->and($this->model->dayMessages[1])->toContain('THE PREVIOUS ANSWER TO THIS DAY FAILED')
        ->and($this->model->dayMessages[1])->toContain(PlanDayValidator::EXAMPLE_IS_A_TERM)
        // The address, in full: which SHELF, which card, which field.
        ->and($this->model->dayMessages[1])->toContain('words[0].example')
        ->and($this->model->dayMessages[1])->toContain('say[0].translation')
        // Still the same request with one more block, not a different request: the scene the first
        // call was given is in it.
        ->and($this->model->dayMessages[1])->toContain('"intro"');

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

    // The SECOND answer's verdict, and only it: gaps where the learner reads, not the missing keys
    // the first answer was refused for. A stored list that mixed the two would send the third call
    // (there is none) after a defect somebody had already fixed.
    expect($joined)->toContain(PlanDayValidator::TRANSLATION_HAS_GAP)
        // …and NOT the defect only the FIRST answer had. A stored list that accumulated would send
        // the next call (there is none here) after something somebody already fixed — the exact
        // failure п. 199's second half was cancelled for.
        ->and($joined)->not->toContain(PlanDayValidator::EXAMPLE_IS_A_TERM);

    // TWO ROWS IN THE LEDGER for the day, both refused, each carrying its own reason. A day that
    // cost twice reads as two rows rather than as one that mysteriously cost double.
    $spend = DB::table('generation_requests')
        ->where('plan_id', $plan['id'])
        ->where('prompt', 'like', 'day:%')
        ->orderBy('created_at')
        ->get();

    expect($spend)->toHaveCount(2)
        ->and($spend[0]->error)->toContain(PlanDayValidator::EXAMPLE_IS_A_TERM)
        ->and($spend[1]->error)->toContain(PlanDayValidator::TRANSLATION_HAS_GAP)
        ->and($spend->pluck('purpose')->unique()->all())->toBe(['plan']);

    // THE SHAPE OF A REFUSED ANSWER IS STILL VISIBLE. The first answer had no question and no
    // repair move — the day keeps its questions and loses its way out of a misheard sentence. That
    // day was thrown away, so this report is the only record it ever leaves —
    // and it is NOT counted, because the counters measure weak days the learner actually got.
    $reported = array_column($this->defects->reported, 'counter');

    expect($reported)->toContain(PlanDayValidator::NO_REPAIR)
        ->and(array_column($this->defects->reported, 'counted'))->not->toContain(true)
        ->and($this->defects->warnings(PlanDayValidator::NO_REPAIR))->toBe(0);
});

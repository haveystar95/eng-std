<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\PlanSpend;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Application\Port\RecordsPlanSpend;
use App\Modules\Generation\Application\Service\PlanOutlineService;
use App\Modules\Generation\Domain\Service\PlanOutlineValidator;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Generation\Infrastructure\Prompt\PlanPromptLibrary;
use App\Modules\Learning\Application\Dto\PlanOutlineBrief;
use App\Modules\Learning\Application\Exception\PlanOutlineRefused;
use Tests\Doubles\RecordingPlanDefectReporter;

/**
 * THE SKELETON GETS A SECOND ATTEMPT — and both of them are paid for out loud.
 *
 * Until v0.2.1 it got one. The live run measured what that costs: `outline.outcome_two_actions`
 * refused an otherwise ordinary skeleton for $0.032, and the learner's only recourse was a button
 * that bought a third call knowing no more than the first. What this test pins is the difference
 * between a retry and a re-roll: the second call is told, in the user message, exactly which checks
 * the first answer failed.
 */

/** The hand-written S1 skeleton — the one the whole fixture path already runs on. */
function outlinePayload(): array
{
    /** @var array<string, mixed> $raw */
    $raw = json_decode(
        (string) file_get_contents(__DIR__ . '/../../Fixtures/plan/s1-outline.v0.4.json'),
        true,
    );

    return $raw;
}

/** The same skeleton with one ability packing two actions into one promise — a live failure. */
function outlinePayloadWithTwoActions(): array
{
    $payload = outlinePayload();
    $payload['scenes'][0]['skills'][0]['outcome'] = 'отметиться на приёме и записаться к врачу';

    return $payload;
}

/** A model that answers from a list, and remembers what it was asked. */
function scriptedModel(array $payloads): ContentModelPort
{
    return new class($payloads) implements ContentModelPort
    {
        /** @var list<string> */
        public array $userMessages = [];

        /** @param list<array<string, mixed>> $payloads */
        public function __construct(private array $payloads) {}

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
            $this->userMessages[] = $userMessage;

            return new ModelAnswer(
                payload: array_shift($this->payloads) ?? [],
                model: 'scripted-plan',
                latencyMs: 0,
                tokensIn: 100,
                tokensOut: 200,
                costUsd: '0.032000',
                raw: '{}',
            );
        }
    };
}

/** A ledger that keeps every row, so «оплачено дважды» can be asserted rather than assumed. */
function collectingLedger(): RecordsPlanSpend
{
    return new class implements RecordsPlanSpend
    {
        /** @var list<PlanSpend> */
        public array $rows = [];

        public function record(PlanSpend $spend): void
        {
            $this->rows[] = $spend;
        }
    };
}

function outlineBrief(): PlanOutlineBrief
{
    return new PlanOutlineBrief(
        planId: '01M1BVKQ1AF375ER40H96D4P6Z',
        userId: '01M1BVKQ1AF375ER40H96D4P70',
        goalText: 'Иду к врачу, болит спина, надо объяснить и понять назначение',
        supportLang: 'ru',
        targetLang: 'en',
        level: 'basic',
    );
}

it('re-runs a refused skeleton once, with the violations named, and pays for both calls', function () {
    $model = scriptedModel([outlinePayloadWithTwoActions(), outlinePayload()]);
    $ledger = collectingLedger();

    $answer = (new PlanOutlineService($model, new PlanPromptLibrary(), $ledger, new RecordingPlanDefectReporter()))->outlineFor(outlineBrief());

    // ONE skeleton comes back, and it is the second answer.
    expect($answer->payload['goal_summary'])->toStartWith('Иду к врачу с болью в спине')
        ->and($answer->payload['scenes'][0]['skills'][0]['outcome'])->toBe('отметиться на приёме у стойки')
        ->and($model->userMessages)->toHaveCount(2);

    // The first message is the goal alone; the second carries the verdict on the first, as DATA.
    expect($model->userMessages[0])->not->toContain('PREVIOUS ATTEMPT')
        ->and($model->userMessages[1])->toContain('PREVIOUS ATTEMPT FAILED THESE CHECKS')
        ->and($model->userMessages[1])->toContain(PlanOutlineValidator::OUTCOME_TWO_ACTIONS)
        ->and($model->userMessages[1])->toContain('два разных действия')
        // The goal is still there: the retry is the same request with one more block, not a
        // different request.
        ->and($model->userMessages[1])->toContain('Иду к врачу');

    // TWO rows in the ledger, and the refused one says so. A skeleton that cost twice reads as two
    // rows, not as one that mysteriously cost double.
    expect($ledger->rows)->toHaveCount(2)
        ->and($ledger->rows[0]->succeeded)->toBeFalse()
        ->and($ledger->rows[0]->error)->toContain(PlanOutlineValidator::OUTCOME_TWO_ACTIONS)
        ->and($ledger->rows[0]->costUsd)->toBe('0.032000')
        ->and($ledger->rows[1]->succeeded)->toBeTrue()
        ->and($ledger->rows[1]->error)->toBeNull()
        ->and(array_map(static fn (PlanSpend $s): string => $s->call, $ledger->rows))
        ->toBe([PlanSpend::CALL_OUTLINE, PlanSpend::CALL_OUTLINE]);
});

it('stops after the second refusal and hands the learner the second verdict', function () {
    $model = scriptedModel([outlinePayloadWithTwoActions(), outlinePayloadWithTwoActions()]);
    $ledger = collectingLedger();

    $service = new PlanOutlineService($model, new PlanPromptLibrary(), $ledger, new RecordingPlanDefectReporter());

    expect(fn () => $service->outlineFor(outlineBrief()))
        ->toThrow(PlanOutlineRefused::class, 'два разных действия');

    // Two calls, never three: the gate stays fatal, it just costs one more attempt to become so.
    expect($model->userMessages)->toHaveCount(2)
        ->and($ledger->rows)->toHaveCount(2)
        ->and($ledger->rows[1]->succeeded)->toBeFalse();
});

it('reports an off-guide skeleton on every attempt and counts only the one it keeps', function () {
    // Thirteen abilities on both answers, and `outcome_two_actions` on the first. The first answer
    // is refused and thrown away — the report is the only place its shape survives — and the
    // second is kept, so exactly one of the two reports is counted.
    $thirteen = static function (array $payload): array {
        $skill = $payload['scenes'][0]['skills'][0];
        $payload['scenes'][0]['skills'] = array_fill(0, 4, $skill);
        $payload['scenes'][1]['skills'] = array_fill(0, 9, $skill);

        return $payload;
    };

    $model = scriptedModel([$thirteen(outlinePayloadWithTwoActions()), $thirteen(outlinePayload())]);
    $defects = new RecordingPlanDefectReporter();

    (new PlanOutlineService($model, new PlanPromptLibrary(), collectingLedger(), $defects))
        ->outlineFor(outlineBrief());

    $skillWarnings = array_values(array_filter(
        $defects->reported,
        static fn (array $w): bool => $w['counter'] === PlanOutlineValidator::SKILL_COUNT_WARNING,
    ));

    expect($skillWarnings)->toHaveCount(2)
        ->and($skillWarnings[0]['counted'])->toBeFalse()
        ->and($skillWarnings[1]['counted'])->toBeTrue()
        // A skeleton has no day, and «day 0» would read as a real one.
        ->and($skillWarnings[0]['day_index'])->toBeNull()
        ->and($defects->warnings(PlanOutlineValidator::SKILL_COUNT_WARNING))->toBe(1);
});

it('never re-runs a vendor failure — that one does not improve on a second try', function () {
    $model = new class implements ContentModelPort
    {
        public int $calls = 0;

        public function provider(): ProviderId
        {
            return ProviderId::OpenAi;
        }

        public function model(): string
        {
            return 'exploding-plan';
        }

        public function complete(RenderedPrompt $prompt, string $userMessage, array $schema): ModelAnswer
        {
            $this->calls++;

            throw new RuntimeException('402 insufficient credits');
        }
    };
    $ledger = collectingLedger();

    expect(fn () => (new PlanOutlineService($model, new PlanPromptLibrary(), $ledger, new RecordingPlanDefectReporter()))->outlineFor(outlineBrief()))
        ->toThrow(PlanOutlineRefused::class, 'insufficient credits');

    // One call, and no ledger row: nothing was answered, so nothing was priced.
    expect($model->calls)->toBe(1)
        ->and($ledger->rows)->toBe([]);
});

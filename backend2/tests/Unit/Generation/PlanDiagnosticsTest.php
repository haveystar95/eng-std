<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\PlanSpend;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Application\Port\RecordsPlanSpend;
use App\Modules\Generation\Application\Service\PlanOutlineService;
use App\Modules\Generation\Infrastructure\Prompt\PlanPromptLibrary;
use App\Modules\Learning\Application\Dto\PlanOutlineBrief;
use App\Modules\Learning\Domain\ValueObject\ListeningDiagnostics;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use Tests\Doubles\RecordingPlanDefectReporter;

/**
 * THE LISTENING STEP REACHES THE TWO PROMPTS — and both paths through it are real.
 *
 * `{{diagnostics}}` of P1 and `{{balance}}` of P2 have an EMPTY case that is not a stub: the step
 * is optional, most plans will skip it, and both prompts say in their own text what to do with
 * nothing. So «пусто» is tested beside «есть» rather than treated as the untested default — the
 * live run of P2-v0.4 already paid once for a placeholder whose empty case nobody had exercised.
 */

/** The hand-written S1 skeleton — the same fixture the rest of the plan path runs on. */
function diagnosticsOutlinePayload(): array
{
    /** @var array<string, mixed> $raw */
    $raw = json_decode(
        (string) file_get_contents(__DIR__ . '/../../Fixtures/plan/s1-outline.v0.4.json'),
        true,
    );

    return $raw;
}

/** A model that records the SYSTEM prompt it was handed, which is where the placeholders live. */
function promptRecordingModel(array $payload): ContentModelPort
{
    return new class($payload) implements ContentModelPort
    {
        /** @var list<string> */
        public array $prompts = [];

        public function __construct(private array $payload) {}

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
            $this->prompts[] = $prompt->text;

            return new ModelAnswer(
                payload: $this->payload,
                model: 'scripted-plan',
                latencyMs: 0,
                tokensIn: 100,
                tokensOut: 200,
                costUsd: '0.021000',
                raw: '{}',
            );
        }
    };
}

function silentLedger(): RecordsPlanSpend
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

/** @param list<bool> $verdicts one per line, in the order the learner heard them */
function diagnosticsOf(array $verdicts): ListeningDiagnostics
{
    $lines = [];
    foreach ($verdicts as $i => $understood) {
        $n = $i + 1;
        $lines[] = [
            'text' => "Line {$n} of the situation?",
            'translation' => "Реплика {$n}?",
            'place' => 'на стойке',
            'understood' => $understood,
        ];
    }

    /** @var ListeningDiagnostics $d */
    $d = ListeningDiagnostics::fromLines($lines);

    return $d;
}

function briefWith(?ListeningDiagnostics $diagnostics): PlanOutlineBrief
{
    return new PlanOutlineBrief(
        planId: '01M1BVKQ1AF375ER40H96D4P6Z',
        userId: '01M1BVKQ1AF375ER40H96D4P70',
        goalText: 'Иду к врачу, болит спина, надо объяснить и понять назначение',
        supportLang: 'ru',
        targetLang: 'en',
        level: 'basic',
        diagnostics: $diagnostics?->toArray(),
        balance: $diagnostics?->emphasis() ?? '',
    );
}

// ── the verdict itself ───────────────────────────────────────────────────────────────────────

it('calls it speaking only when every line was understood', function () {
    expect(diagnosticsOf([true, true, true])->emphasis())
        ->toBe(ListeningDiagnostics::EMPHASIS_SPEAKING);
});

it('calls a MIXED answer understanding — two out of three is not a pass', function () {
    expect(diagnosticsOf([true, true, false])->emphasis())
        ->toBe(ListeningDiagnostics::EMPHASIS_UNDERSTANDING)
        ->and(diagnosticsOf([false, false, false])->emphasis())
        ->toBe(ListeningDiagnostics::EMPHASIS_UNDERSTANDING);
});

it('has no record at all for a step that did not happen', function () {
    // «Пропущен» is not «прошёл и ничего не понял» — the second is a real diagnostics with three
    // false rows, and it changes what the plan is made of.
    expect(ListeningDiagnostics::fromLines([]))->toBeNull();
});

// ── P1 ───────────────────────────────────────────────────────────────────────────────────────

it('writes the heard lines and the verdict into the skeleton prompt', function () {
    $model = promptRecordingModel(diagnosticsOutlinePayload());

    (new PlanOutlineService($model, new PlanPromptLibrary(), silentLedger(), new RecordingPlanDefectReporter()))
        ->outlineFor(briefWith(diagnosticsOf([true, false, false])));

    expect($model->prompts[0])->toContain('Listening check')
        ->and($model->prompts[0])->toContain('Line 1 of the situation?')
        ->and($model->prompts[0])->toContain('understood')
        ->and($model->prompts[0])->toContain('not understood')
        ->and($model->prompts[0])->toContain('LISTENING COMPREHENSION')
        ->and($model->prompts[0])->not->toContain('{{diagnostics}}');
});

it('writes the other verdict when every line landed', function () {
    $model = promptRecordingModel(diagnosticsOutlinePayload());

    (new PlanOutlineService($model, new PlanPromptLibrary(), silentLedger(), new RecordingPlanDefectReporter()))
        ->outlineFor(briefWith(diagnosticsOf([true, true, true])));

    expect($model->prompts[0])->toContain('put the weight on SPEAKING')
        ->and($model->prompts[0])->not->toContain('LISTENING COMPREHENSION');
});

it('tells the skeleton prompt the step was skipped, and does not invent a verdict', function () {
    $model = promptRecordingModel(diagnosticsOutlinePayload());

    (new PlanOutlineService($model, new PlanPromptLibrary(), silentLedger(), new RecordingPlanDefectReporter()))
        ->outlineFor(briefWith(null));

    expect($model->prompts[0])->toContain('empty — the user skipped the listening step')
        ->and($model->prompts[0])->not->toContain('Listening check')
        ->and($model->prompts[0])->not->toContain('put the weight on')
        ->and($model->prompts[0])->not->toContain('{{diagnostics}}');
});

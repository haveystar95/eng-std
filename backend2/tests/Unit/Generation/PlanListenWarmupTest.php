<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\PlanSpend;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Application\Port\ListenWarmupReporter;
use App\Modules\Generation\Application\Port\RecordsPlanSpend;
use App\Modules\Generation\Application\Service\PlanListenService;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Generation\Infrastructure\Prompt\PlanPromptLibrary;
use App\Modules\Learning\Application\Dto\ListenLineView;
use App\Modules\Learning\Application\Dto\ListenWarmupBrief;

/**
 * P-Listen — AND THE SILENCE, which is the half this file mostly exists for.
 *
 * The listening step is optional, so its failures are invisible by design: no error screen, no
 * retry, the entry walks on to the date. That makes «тихий фолбэк» a behaviour to pin rather than
 * an absence of behaviour — a service that started throwing would put a 500 in front of a person
 * who was about to pick a date, and nothing on the screen would ever have shown it was coming.
 */
function listenBrief(): ListenWarmupBrief
{
    return new ListenWarmupBrief(
        userId: '01M1BVKQ1AF375ER40H96D4P70',
        goalText: 'Иду к врачу с ребёнком в частную клинику, надо объяснить симптомы',
        supportLang: 'ru',
        targetLang: 'en',
        level: 'basic',
    );
}

/** A model that answers with one payload, or throws instead of answering at all. */
function listenModel(?array $payload, ?Throwable $failWith = null): ContentModelPort
{
    return new class($payload, $failWith) implements ContentModelPort
    {
        public int $calls = 0;

        public string $lastPrompt = '';

        public function __construct(private ?array $payload, private ?Throwable $failWith) {}

        public function provider(): ProviderId
        {
            return ProviderId::OpenAi;
        }

        public function model(): string
        {
            return 'scripted-listen';
        }

        public function complete(RenderedPrompt $prompt, string $userMessage, array $schema): ModelAnswer
        {
            $this->calls++;
            $this->lastPrompt = $prompt->text;

            if ($this->failWith !== null) {
                throw $this->failWith;
            }

            return new ModelAnswer(
                payload: $this->payload ?? [],
                model: 'scripted-listen',
                latencyMs: 0,
                tokensIn: 60,
                tokensOut: 120,
                costUsd: '0.004000',
                raw: '{}',
            );
        }
    };
}

function listenLedger(): RecordsPlanSpend
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

function listenReporter(): ListenWarmupReporter
{
    return new class implements ListenWarmupReporter
    {
        /** @var list<string> */
        public array $reasons = [];

        public function notOffered(string $userId, string $targetLang, string $reason): void
        {
            $this->reasons[] = $reason;
        }
    };
}

/** @param list<array{text: string, translation: string, place: string}> $lines */
function listenPayload(array $lines): array
{
    return ['lines' => $lines];
}

it('returns three lines and pays for them with no plan id', function () {
    $model = listenModel(listenPayload([
        ['text' => 'Do you have an appointment?', 'translation' => 'У вас есть запись?', 'place' => 'на стойке'],
        ['text' => 'What seems to be the problem?', 'translation' => 'Что случилось?', 'place' => 'в кабинете'],
        ['text' => 'Please come in and take a seat.', 'translation' => 'Проходите и присаживайтесь.', 'place' => 'в кабинете'],
    ]));
    $ledger = listenLedger();

    $lines = (new PlanListenService($model, new PlanPromptLibrary(), $ledger, listenReporter()))
        ->linesFor(listenBrief());

    expect($lines)->toHaveCount(3)
        ->and($lines[1])->toBeInstanceOf(ListenLineView::class)
        ->and($lines[1]->text)->toBe('What seems to be the problem?')
        ->and($lines[1]->place)->toBe('в кабинете');

    // The ledger row exists, says which prompt was used, and carries NO plan — because there is no
    // plan yet, and inventing one would be worse than the honest hole.
    expect($ledger->rows)->toHaveCount(1)
        ->and($ledger->rows[0]->planId)->toBeNull()
        ->and($ledger->rows[0]->call)->toBe(PlanSpend::CALL_LISTEN)
        ->and($ledger->rows[0]->promptVersion)->toBe(PlanPromptLibrary::LISTEN_VERSION)
        ->and($ledger->rows[0]->succeeded)->toBeTrue()
        ->and($ledger->rows[0]->size)->toBe(3);
});

it('goes silent when the model call fails — no exception, no retry, no ledger row', function () {
    $model = listenModel(null, new RuntimeException('502 Bad Gateway'));
    $ledger = listenLedger();
    $reporter = listenReporter();

    $lines = (new PlanListenService($model, new PlanPromptLibrary(), $ledger, $reporter))
        ->linesFor(listenBrief());

    // THE WHOLE CONTRACT: an empty list, which the entry reads as «шаг не предлагается».
    expect($lines)->toBe([])
        // One call. A retry would double the wait before a screen nobody asked for.
        ->and($model->calls)->toBe(1)
        // Nothing was bought — a vendor error before an answer is not a purchase.
        ->and($ledger->rows)->toBe([])
        // …and it is not silent to US: «нам не предлагали послушать» must be findable.
        ->and($reporter->reasons)->toHaveCount(1)
        ->and($reporter->reasons[0])->toContain('502 Bad Gateway');
});

it('goes silent on an answer with no usable line, and still records the call it paid for', function () {
    $model = listenModel(listenPayload([
        // A line with no translation is dropped rather than repaired: «Показать текст» would show
        // nothing, and the self-tap would be about a sound the learner cannot check.
        ['text' => 'Do you have an appointment?', 'translation' => '', 'place' => 'на стойке'],
    ]));
    $ledger = listenLedger();
    $reporter = listenReporter();

    $lines = (new PlanListenService($model, new PlanPromptLibrary(), $ledger, $reporter))
        ->linesFor(listenBrief());

    expect($lines)->toBe([])
        ->and($reporter->reasons)->toHaveCount(1)
        // The call happened, so the row happens: a refused answer cost what an accepted one does.
        ->and($ledger->rows)->toHaveCount(1)
        ->and($ledger->rows[0]->succeeded)->toBeFalse()
        ->and($ledger->rows[0]->size)->toBe(0);
});

it('goes silent on an answer that is not the shape at all', function () {
    $model = listenModel(['lines' => 'три реплики']);

    $lines = (new PlanListenService($model, new PlanPromptLibrary(), listenLedger(), listenReporter()))
        ->linesFor(listenBrief());

    expect($lines)->toBe([]);
});

it('never shows more than three lines, whatever the answer holds', function () {
    $rows = [];
    foreach (range(1, 7) as $i) {
        $rows[] = ['text' => "Line {$i}?", 'translation' => "Реплика {$i}?", 'place' => 'на стойке'];
    }

    $lines = (new PlanListenService(listenModel(listenPayload($rows)), new PlanPromptLibrary(), listenLedger(), listenReporter()))
        ->linesFor(listenBrief());

    expect($lines)->toHaveCount(3)
        ->and($lines[2]->text)->toBe('Line 3?');
});

it('sends the goal and the languages by name, and no plan facts at all', function () {
    $model = listenModel(listenPayload([
        ['text' => 'Do you have an appointment?', 'translation' => 'У вас есть запись?', 'place' => 'на стойке'],
    ]));

    (new PlanListenService($model, new PlanPromptLibrary(), listenLedger(), listenReporter()))
        ->linesFor(listenBrief());

    expect($model->lastPrompt)->toContain('Иду к врачу с ребёнком')
        // Names, not codes — the prompt is English prose about «in {{target_lang}}».
        ->and($model->lastPrompt)->toContain('English')
        ->and($model->lastPrompt)->not->toContain('{{target_lang}}')
        ->and($model->lastPrompt)->not->toContain('{{goal}}')
        // The header above the first `---` is for readers and must not reach the model.
        ->and($model->lastPrompt)->not->toContain('Боевой промпт');
});

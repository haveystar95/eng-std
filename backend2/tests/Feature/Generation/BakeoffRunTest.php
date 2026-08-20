<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\BakeoffTask;
use App\Modules\Generation\Application\Port\BakeoffJournal;
use App\Modules\Generation\Application\Service\BakeoffReport;
use App\Modules\Generation\Application\Service\BakeoffRunner;
use App\Modules\Generation\Application\Service\BakeoffSample;
use App\Modules\Generation\Domain\ValueObject\BakeoffTrack;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use App\Modules\Vocabulary\Application\Command\ImportTerm;
use App\Modules\Vocabulary\Application\Command\ImportTermHandler;
use App\Modules\Vocabulary\Application\Dto\ExampleInput;
use App\Modules\Vocabulary\Application\Dto\TranslationInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/** @param list<array<string, mixed>> $items */
function fakeProviderAnswer(array $items, string $model = 'gpt-4o'): void
{
    Http::fake(['*' => Http::response([
        'model' => $model,
        'choices' => [['message' => ['content' => json_encode([
            'title' => 'Bank', 'description' => 'd', 'collection_image_prompt' => 'bank',
            'items' => $items,
        ], JSON_UNESCAPED_UNICODE)]]],
        'usage' => ['prompt_tokens' => 2000, 'completion_tokens' => 1000],
    ], 200)]);
}

/** @return array<string, mixed> */
function bakeoffGoodItem(string $text = 'withdraw cash', string $translation = 'снять наличные'): array
{
    return [
        'text' => $text,
        'type' => 'phrase',
        'transcription' => 'wɪðˈdrɔː kæʃ',
        'translation' => $translation,
        'example' => 'I need to ' . $text . ' before the trip.',
        'example_translation' => 'Мне нужно ' . $translation . ' перед поездкой.',
        'cefr' => 'A2',
        'image_api_prompt' => 'atm',
    ];
}

function bakeoffProvider(): App\Modules\Generation\Application\Port\ContentModelPort
{
    return new App\Modules\Generation\Infrastructure\Adapter\OpenAiCompatibleContentModel(
        app(App\Modules\Observability\Application\Support\OutboundCallContext::class),
        ProviderId::OpenAi,
        'key',
        'gpt-4o',
        'https://api.openai.com/v1',
    );
}

it('runs a task end to end: same prompt in, judged candidates out, spend measured', function () {
    fakeProviderAnswer([
        bakeoffGoodItem(),
        // …and one broken row: the example repeats the term and the key loses its addressee.
        [
            'text' => 'Tell us about your experience',
            'type' => 'phrase',
            'transcription' => 'tel ʌs',
            'translation' => 'Расскажите о своём опыте',
            'example' => 'Tell us about your experience',
            'example_translation' => 'Расскажите о своём опыте',
            'cefr' => 'B1',
            'image_api_prompt' => '',
        ],
    ]);

    $result = app(BakeoffRunner::class)->run(
        bakeoffProvider(),
        new BakeoffTask(BakeoffTrack::Collections, 'в банке', 'TOPIC: "в банке"', expectedSize: 2),
        'v10',
        new LanguageCode('ru'),
        new LanguageCode('en'),
    );

    expect($result->ok)->toBeTrue()
        ->and($result->batch?->total())->toBe(2)
        ->and($result->batch?->clean())->toBe(1)
        ->and($result->costUsd)->toBe('0.015000')
        ->and($result->latencyMs)->toBeGreaterThanOrEqual(0)
        ->and($result->promptSha)->toHaveLength(64);

    // The whole answer came from the v10 rules, not from anything the runner improvised.
    Http::assertSent(fn (Request $r): bool => str_contains($r->data()['messages'][0]['content'], 'a key must be isomorphic'));
});

it('records a dead provider as a failed call instead of ending the run', function () {
    Http::fake(['*' => Http::response(['error' => 'overloaded'], 503)]);

    $result = app(BakeoffRunner::class)->run(
        bakeoffProvider(),
        new BakeoffTask(BakeoffTrack::Collections, 'в банке', 'TOPIC', expectedSize: 2),
        'v10',
        new LanguageCode('ru'),
        new LanguageCode('en'),
    );

    expect($result->ok)->toBeFalse()
        ->and($result->error)->toContain('503')
        ->and($result->batch)->toBeNull();
});

it('asks the enrichment shape for options and matches items to the given terms positionally', function () {
    Http::fake(['*' => Http::response([
        'model' => 'gpt-4o',
        'choices' => [['message' => ['content' => json_encode([
            'items' => [[
                ...bakeoffGoodItem('take out a loan', 'взять кредит'),
                'options' => ['погасить кредит', 'открыть вклад', 'закрыть счёт'],
            ]],
        ], JSON_UNESCAPED_UNICODE)]]],
        'usage' => ['prompt_tokens' => 500, 'completion_tokens' => 200],
    ], 200)]);

    $result = app(BakeoffRunner::class)->run(
        bakeoffProvider(),
        new BakeoffTask(
            BakeoffTrack::Enrichment,
            'take out a loan',
            'GIVEN TERMS: take out a loan',
            expectedSize: 1,
            terms: [['id' => '01J0000000000000000000TERM', 'text' => 'take out a loan']],
        ),
        'v10',
        new LanguageCode('ru'),
        new LanguageCode('en'),
    );

    expect($result->batch?->clean())->toBe(1)
        ->and($result->batch?->verdicts[0]->item->sourceTermId)->toBe('01J0000000000000000000TERM')
        ->and($result->batch?->verdicts[0]->item->options)->toHaveCount(3);

    Http::assertSent(function (Request $r): bool {
        $item = $r->data()['response_format']['json_schema']['schema']['properties']['items']['items'];

        return in_array('options', $item['required'], true)
            && str_contains($r->data()['messages'][0]['content'], 'copied verbatim');
    });
});

it('writes candidates to the sandbox and nothing to the content tables', function () {
    fakeProviderAnswer([bakeoffGoodItem(), bakeoffGoodItem('open an account', 'открыть счёт')]);

    $termsBefore = DB::table('terms')->count();
    $translationsBefore = DB::table('term_translations')->count();

    $journal = app(BakeoffJournal::class);
    $runId = $journal->openRun('test', 'v10', 'ru', 'en', ['providers' => []]);
    $journal->recordCall($runId, app(BakeoffRunner::class)->run(
        bakeoffProvider(),
        new BakeoffTask(BakeoffTrack::Collections, 'в банке', 'TOPIC', expectedSize: 2),
        'v10',
        new LanguageCode('ru'),
        new LanguageCode('en'),
    ));

    expect(DB::table('bakeoff_candidates')->where('run_id', $runId)->count())->toBe(2)
        ->and(DB::table('bakeoff_calls')->where('run_id', $runId)->where('ok', true)->count())->toBe(1)
        // The moratorium, asserted rather than trusted: a bake-off writes to its sandbox only.
        ->and(DB::table('terms')->count())->toBe($termsBefore)
        ->and(DB::table('term_translations')->count())->toBe($translationsBefore);
});

/**
 * The sandbox earns its place only if a finished run can be re-read. A report gets argued with and
 * improved; re-rendering it must not mean paying for the model answers a second time.
 */
it('reads a finished run back out of the sandbox, verdicts and spend intact', function () {
    fakeProviderAnswer([
        bakeoffGoodItem(),
        [
            'text' => 'Tell us about your experience',
            'type' => 'phrase',
            'transcription' => 'tel ʌs',
            'translation' => 'Расскажите о своём опыте',   // «нам» dropped — a lost addressee
            'example' => 'Tell us about your experience with the team.',
            'example_translation' => 'Расскажите о своём опыте работы с командой.',
            'cefr' => 'B1',
            'image_api_prompt' => '',
        ],
    ]);

    $journal = app(BakeoffJournal::class);
    $runId = $journal->openRun('test', 'v10', 'ru', 'en', ['size' => 2, 'providers' => []]);
    $journal->recordCall($runId, app(BakeoffRunner::class)->run(
        bakeoffProvider(),
        new BakeoffTask(BakeoffTrack::Collections, 'в банке', 'TOPIC', expectedSize: 2),
        'v10',
        new LanguageCode('ru'),
        new LanguageCode('en'),
    ));

    $stored = $journal->readRun($runId);

    expect($stored)->not->toBeNull()
        ->and($stored['results'])->toHaveCount(1);

    $result = $stored['results'][0];
    expect($result->ok)->toBeTrue()
        ->and($result->costUsd)->toBe('0.015000')
        ->and($result->batch?->total())->toBe(2)
        // The verdict survives the round trip, defect and all.
        ->and($result->batch?->clean())->toBe(1)
        ->and($result->batch?->failures(App\Modules\Generation\Domain\ValueObject\CheckId::Isomorphism))->toBe(1)
        ->and($result->batch?->verdicts[1]->reason())->toContain('потеряно');
});

/**
 * The two-stage config only means anything if the second stage runs over the cores the first stage
 * actually produced — otherwise two halves that never met are being priced as one collection.
 */
it('hands the machinery stage the cores the collection stage produced', function () {
    fakeProviderAnswer([
        bakeoffGoodItem(),
        bakeoffGoodItem('open an account', 'открыть счёт'),
        // A core missing its example is not a core: handing it on would score the machinery stage
        // for a blank it was never given.
        [...bakeoffGoodItem('half a card', 'половина карточки'), 'example' => ''],
    ]);

    $journal = app(BakeoffJournal::class);
    $runId = $journal->openRun('two-stage', 'v11', 'ru', 'en', ['size' => 3, 'providers' => []]);
    $journal->recordCall($runId, app(BakeoffRunner::class)->run(
        bakeoffProvider(),
        new BakeoffTask(BakeoffTrack::Collections, 'в банке', 'TOPIC', expectedSize: 3),
        'v11',
        new LanguageCode('ru'),
        new LanguageCode('en'),
    ));

    $cores = $journal->readCores($runId, null, BakeoffTrack::Collections->value);

    expect($cores)->toHaveCount(2)
        ->and(array_column($cores, 'text'))->toBe(['withdraw cash', 'open an account'])
        ->and($cores[0]['translation'])->toBe('снять наличные')
        ->and($cores[0]['example'])->toContain('withdraw cash');
});

it('asks the mechanics shape for machinery only, with no core fields in the schema', function () {
    Http::fake(['*' => Http::response([
        'model' => 'gpt-4o-mini',
        'choices' => [['message' => ['content' => json_encode([
            'items' => [[
                'text' => 'withdraw cash',
                'options' => ['положить деньги', 'закрыть счёт', 'проверить баланс'],
                'forms' => ['take out cash'],
            ]],
        ], JSON_UNESCAPED_UNICODE)]]],
        'usage' => ['prompt_tokens' => 800, 'completion_tokens' => 120],
    ], 200)]);

    $result = app(BakeoffRunner::class)->run(
        bakeoffProvider(),
        new BakeoffTask(
            BakeoffTrack::Mechanics,
            'механика ×1',
            "GIVEN CARDS\n1. TERM: withdraw cash\n   TRANSLATION: снять наличные\n   EXAMPLE: I need to withdraw cash.",
            expectedSize: 1,
            terms: [['id' => '01J0000000000000000000TERM', 'text' => 'withdraw cash']],
        ),
        'v11',
        new LanguageCode('ru'),
        new LanguageCode('en'),
    );

    // A compliant machinery answer is CLEAN even though it carries no translation and no example.
    expect($result->ok)->toBeTrue()
        ->and($result->batch?->clean())->toBe(1)
        ->and($result->batch?->verdicts[0]->item->forms)->toBe(['take out cash']);

    Http::assertSent(function (Request $r): bool {
        $item = $r->data()['response_format']['json_schema']['schema']['properties']['items']['items'];
        $system = $r->data()['messages'][0]['content'];

        return $item['required'] === ['text', 'options', 'forms']
            // The core fields are absent from the SCHEMA, which is what makes "do not touch the
            // core" impossible to disobey rather than merely discouraged.
            && ! array_key_exists('translation', $item['properties'])
            && ! array_key_exists('example', $item['properties'])
            && str_contains($system, 'Do not rewrite the core.');
    });
});

it('returns null for a run that is not in the sandbox', function () {
    expect(app(BakeoffJournal::class)->readRun('01J000000000000000000MISS'))->toBeNull();
});

it('picks a sample that leads with the terms carrying an addressee, and repeats it exactly', function () {
    $import = app(ImportTermHandler::class);
    foreach ([
        ['Tell us about it', 'Расскажите нам об этом'],
        ['call me back', 'перезвоните мне'],
        ['open an account', 'открыть счёт'],
        ['invoice', 'счёт-фактура'],
    ] as [$text, $translation]) {
        $import(new ImportTerm(
            lang: new LanguageCode('en'),
            text: $text,
            type: str_contains($text, ' ') ? 'phrase' : 'word',
            pos: null,
            source: 'ai',
            translations: [new TranslationInput(new LanguageCode('ru'), $translation, isPrimary: true)],
            examples: [new ExampleInput($text . ' now.', $translation . ' сейчас.')],
        ));
    }

    $sample = app(BakeoffSample::class);
    $first = $sample->pick('en', 'ru', 4);
    $second = $sample->pick('en', 'ru', 4);

    expect($first)->toHaveCount(4)
        // Deterministic — two runs of the bake-off are otherwise not comparable.
        ->and($second)->toBe($first);

    $buckets = array_column($first, 'bucket', 'text');
    expect($buckets['call me back'])->toBe('addressee')
        ->and($buckets['invoice'])->toBe('word')
        ->and($buckets['open an account'])->toBe('phrase');
});

it('renders a report with a table per track and names the provider that could not run', function () {
    fakeProviderAnswer([bakeoffGoodItem()]);

    $results = [];
    foreach ([BakeoffTrack::Collections, BakeoffTrack::OneShot] as $track) {
        $results[] = app(BakeoffRunner::class)->run(
            bakeoffProvider(),
            new BakeoffTask($track, 'в банке', 'TOPIC', expectedSize: 1),
            'v10',
            new LanguageCode('ru'),
            new LanguageCode('en'),
        );
    }

    $markdown = app(BakeoffReport::class)->render(
        $results,
        [
            App\Modules\Generation\Application\Dto\ProviderAvailability::ready(ProviderId::OpenAi, 'gpt-4o'),
            App\Modules\Generation\Application\Dto\ProviderAvailability::missingKey(ProviderId::Anthropic, 'claude-opus-5', 'ANTHROPIC_API_KEY'),
        ],
        ['label' => 'v10', 'prompt_version' => 'v10', 'run_id' => 'r1', 'collection_size' => 12],
    );

    expect($markdown)
        ->toContain('Трек А — генерация коллекций')
        ->toContain('Трек В — one-shot (эксперимент)')
        // A provider that did not run is stated, with the reason — never a missing column.
        ->toContain('ANTHROPIC_API_KEY')
        ->toContain('Деградация по позиции в списке')
        // The pipeline-shape comparison needs BOTH shapes. Track Б did not run here, so the
        // section is absent rather than rendered as a heading over one row — which would read
        // like a comparison that had been made.
        ->not->toContain('Два этапа (А + Б) против one-shot (В)');
});

it('compares the two pipeline shapes only once both of them have answered', function () {
    fakeProviderAnswer([bakeoffGoodItem()]);

    // The three PIPELINE tracks, named explicitly. Sweeping the enum would drag in the mechanics
    // track, which v10 has no shape for — the version, not the enum, decides what can be asked.
    $results = [];
    foreach ([BakeoffTrack::Collections, BakeoffTrack::Enrichment, BakeoffTrack::OneShot] as $track) {
        $results[] = app(BakeoffRunner::class)->run(
            bakeoffProvider(),
            new BakeoffTask($track, 'в банке', 'TOPIC', expectedSize: 1),
            'v10',
            new LanguageCode('ru'),
            new LanguageCode('en'),
        );
    }

    $markdown = app(BakeoffReport::class)->render(
        $results,
        [App\Modules\Generation\Application\Dto\ProviderAvailability::ready(ProviderId::OpenAi, 'gpt-4o')],
        ['label' => 'v10', 'prompt_version' => 'v10', 'run_id' => 'r1', 'collection_size' => 12],
    );

    expect($markdown)->toContain('Два этапа (А + Б) против one-shot (В)')
        ->toContain('А + Б — текущая схема, итого');
});

<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;
use App\Modules\Generation\Application\Dto\EnrichmentBrief;
use App\Modules\Generation\Application\Dto\GenerationStackConfig;
use App\Modules\Generation\Application\Port\EnrichmentPackerPort;
use App\Modules\Generation\Infrastructure\Adapter\MachineryEnrichmentPacker;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiEnrichmentPacker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// The Observability listener logs the outbound call; wrap so those rows roll back.
uses(RefreshDatabase::class);

/**
 * The станок on the v2 stack: prompt v12, shape `machinery`, cheap model, and — the point of the
 * whole cut-over — it buys ONLY what this app can store. `enrich_pack.v2` bought four products per
 * term and two of them were a QA diagnostic for a human (audit A5); v11's own `mechanics` shape
 * buys wrong TRANSLATIONS, which nothing here has a table for.
 */
function livePacker(array $config = []): EnrichmentPackerPort
{
    config(['services.generation.driver' => 'openai', 'services.openai.api_key' => 'key', ...$config]);
    app()->forgetInstance(GenerationStackConfig::class);

    return app(EnrichmentPackerPort::class);
}

function packLive(array $config = []): void
{
    Http::fake(['*' => Http::response([
        'model' => 'gpt-4o-mini-2024-07-18',
        'choices' => [['message' => ['content' => json_encode(['items' => [[
            'text' => 'bank account',
            'forms' => ['bank-account'],
            'distractors' => [[
                'sentence' => 'I need bank account to receive my salary.',
                'error_type' => 'article',
                'error_span' => 'need bank account',
                'correction' => 'need a bank account',
            ]],
        ]]])]]],
        'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 7],
    ], 200)]);

    livePacker($config)->pack(new EnrichmentBrief(
        '01J0TERM', 'bank account', ['bank account'], 'банковский счёт',
        'I need a bank account to receive my salary.', 'Мне нужен банковский счёт.', 'en', 'ru',
    ));
}

it('asks v12 machinery for forms and example distractors — and for nothing else', function () {
    packLive();

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];
        $props = $request->data()['response_format']['json_schema']['schema']['properties']['items']['items']['properties'];

        return $request->data()['model'] === 'gpt-4o-mini'
            // v12's own sections: the distractor taxonomy, and the ban on touching the core.
            && str_contains($system, 'wrong versions of the card')
            && str_contains($system, 'Do not rewrite the core')
            && str_contains($system, 'modal_to')
            // Nothing about translating anything: the shape produces no core at all…
            && ! array_key_exists('translation', $props)
            && ! array_key_exists('example', $props)
            // …and no wrong translations either, which is what v11 `mechanics` would have charged for.
            && ! array_key_exists('options', $props)
            && array_keys($props) === ['text', 'forms', 'distractors'];
    });
});

it('never asks for the per-term QA diagnostic the станок used to buy on every card', function () {
    packLive();

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];
        $schema = json_encode($request->data()['response_format']['json_schema']['schema']);

        return ! str_contains($system, 'back_translation')
            && ! str_contains($system, 'language_notes')
            && ! str_contains((string) $schema, 'back_translation')
            && ! str_contains((string) $schema, 'language_notes');
    });
});

it('reads the answer back as the two products the database has tables for', function () {
    Http::fake(['*' => Http::response([
        'model' => 'gpt-4o-mini-2024-07-18',
        'choices' => [['message' => ['content' => json_encode(['items' => [[
            'text' => 'bank account',
            'forms' => ['bank-account'],
            'distractors' => [[
                'sentence' => 'I need bank account to receive my salary.',
                'error_type' => 'article',
                'error_span' => 'need bank account',
                'correction' => 'need a bank account',
            ]],
        ]]])]]],
        'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 7],
    ], 200)]);

    $pack = livePacker()->pack(new EnrichmentBrief(
        '01J0TERM', 'bank account', ['bank account'], 'банковский счёт',
        'I need a bank account to receive my salary.', 'Мне нужен банковский счёт.', 'en', 'ru',
    ));

    expect($pack->variants)->toHaveCount(1)
        ->and($pack->variants[0]->text)->toBe('bank-account')
        // No note: no model claimed a reason, and inventing one puts words in front of a proofreader
        // that nobody wrote.
        ->and($pack->variants[0]->note)->toBeNull()
        ->and($pack->distractors)->toHaveCount(1)
        ->and($pack->distractors[0]->errorSpan)->toBe('need bank account')
        ->and($pack->distractors[0]->correction)->toBe('need a bank account')
        // The QA fields are absent, not empty-with-meaning: this call was never asked the question,
        // and the validator reads a missing back-translation as "no evidence", not as "no problem".
        ->and($pack->backTranslation)->toBeNull()
        ->and($pack->languageNotes)->toBe([])
        ->and($pack->model)->toBe('gpt-4o-mini-2024-07-18');
});

it('survives a model that skips the card instead of taking the chunk down with it', function () {
    Http::fake(['*' => Http::response([
        'choices' => [['message' => ['content' => json_encode(['items' => []])]]],
        'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 1],
    ], 200)]);

    $pack = livePacker()->pack(new EnrichmentBrief(
        '01J0TERM', 'bank account', ['bank account'], 'банковский счёт', null, null, 'en', 'ru',
    ));

    expect($pack->variants)->toBe([])->and($pack->distractors)->toBe([]);
});

it('puts the old four-product packer back on GENERATION_STACK=v1', function () {
    expect(livePacker(['services.generation.stack' => 'v1']))->toBeInstanceOf(OpenAiEnrichmentPacker::class);
    expect(livePacker(['services.generation.stack' => 'v2']))->toBeInstanceOf(MachineryEnrichmentPacker::class);
});

it('bumps the станок version, so every already-marked term is pending at the new one', function () {
    // The pin is the point: a prompt change that does NOT move this constant is invisible to the
    // journal, and every term already marked done is skipped by the very run meant to fix it.
    expect(BuildTermEnrichmentsHandler::VERSION)->toBe('mech-v13.1');
});

it('shows the worked example as JSON, so the model cannot copy quotes into a field', function () {
    packLive();

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];

        return str_contains($system, '{"sentence": "The post office is next the museum.",')
            && str_contains($system, 'All three are PLAIN TEXT')
            // The pseudo-table that taught `"error_span": "\"place to\""` must be gone.
            && ! str_contains($system, 'error_span:  "next the"');
    });
});

it('runs the machinery on the measured prompt, not on the one it replaced', function () {
    packLive();

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];

        return
            // v13 leads with the mechanical contract — the third of all rows that died there.
            str_contains($system, 'copied character for character out of **your own `sentence`**')
            && str_contains($system, 'Worked example')
            // The five counter-rules that a real run paid for, kept from v12.1's essays.
            && str_contains($system, 'A different tense is not an error')
            && str_contains($system, 'A typo is not a grammar mistake')
            // And the section that bought nothing across 81 terms is gone.
            && ! str_contains($system, 'other {{target_lang}} spellings of THIS term')
            && ! str_contains($system, 'Return an empty list rather than padding');
    });
});

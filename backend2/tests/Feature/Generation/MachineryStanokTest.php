<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\BuildTermEnrichmentsHandler;
use App\Modules\Generation\Application\Dto\EnrichmentBrief;
use App\Modules\Generation\Application\Dto\GenerationStackConfig;
use App\Modules\Generation\Application\Port\EnrichmentPackerPort;
use App\Modules\Generation\Application\Service\ContentContract;
use App\Modules\Generation\Domain\ValueObject\PromptShape;
use App\Modules\Generation\Infrastructure\Adapter\MachineryEnrichmentPacker;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiEnrichmentPacker;
use App\Modules\Generation\Infrastructure\Prompt\PromptLibrary;
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
    // The Feature suite binds FakeEnrichmentPacker over this port for every test (tests/Pest.php), so
    // that a test which merely walks through a door does not buy a model call. THIS file is the one
    // that wants the real adapter — with `Http::fake()` underneath it, so still nothing on the wire —
    // and forgetting the instance is how it asks for it.
    app()->forgetInstance(EnrichmentPackerPort::class);

    return app(EnrichmentPackerPort::class);
}

/**
 * The rendered machinery prompt for a version, without going near a model — for the assertions about
 * a FROZEN version, which has no live call to watch.
 *
 * Its own helper rather than the one in DistractorOverOrderTest: a plain function declared in another
 * test file is only visible here by the accident of serial load order, and this suite runs parallel.
 */
function machineryPrompt(string $version): string
{
    return (new PromptLibrary())->render($version, PromptShape::Machinery, [
        'source_lang' => 'Russian',
        'target_lang' => 'English',
    ])->text;
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

it('asks the machinery shape for forms and example distractors — and for nothing else', function () {
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
    expect(BuildTermEnrichmentsHandler::VERSION)->toBe('mech-v14.3');
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


it('does not buy the synonym question any more — not in the prompt, not in the schema', function () {
    packLive();

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];
        $schema = (string) json_encode($request->data()['response_format']['json_schema']['schema']);

        return
            // The section itself, and the two worked tests it was made of.
            ! str_contains($system, 'other English words for the same thing')
            && ! str_contains($system, 'Test 2 — answer the translation with it')
            && ! str_contains($system, 'savings account')
            // The clauses in the OTHER sections that named the field, which would otherwise describe
            // a product the schema cannot carry.
            && ! str_contains($system, 'near-synonyms')
            && ! str_contains($system, '`synonyms`')
            // And the schema: strict Structured Outputs makes a declared property REQUIRED, so a
            // leftover field here is the model being forced to invent one.
            && ! str_contains($schema, 'synonyms')
            // What the section left behind still works: `forms` keeps its own definition, and it is
            // still stated against synonyms rather than by pointing at a field that is now gone.
            && str_contains($system, 'Not other WORDS that mean the same thing');
    });
});

it('keeps asking v14.2 for synonyms — a frozen version renders the schema its own prompt was written against', function () {
    // Not nostalgia: `mech-v14`…`mech-v14.2` are recorded on live rows, and replaying one has to
    // send the model the same two halves it was measured with.
    $schema = app(ContentContract::class)->schema(PromptShape::Machinery, 'v14.2');
    $props = $schema['properties']['items']['items']['properties'];

    expect(array_keys($props))->toBe(['text', 'forms', 'synonyms', 'distractors'])
        ->and(machineryPrompt('v14.2'))->toContain('other English words for the same thing');

    // …and the versions BELOW the window never mentioned synonyms in their text either, so they stop
    // being forced to emit the field.
    expect(array_keys(app(ContentContract::class)
        ->schema(PromptShape::Machinery, 'v13.1')['properties']['items']['items']['properties']))
        ->toBe(['text', 'forms', 'distractors']);
});

it('shows the model what the term already has, so it does not buy them twice', function () {
    packLive();

    Http::assertSent(function (Request $request): bool {
        $user = $request->data()['messages'][1]['content'];

        return str_contains($user, 'ALREADY ACCEPTED: bank account')
            && str_contains($user, 'ALREADY SYNONYMS: —');
    });
});

// The READING side stays tolerant on purpose, and v14.3 is why it is worth a test of its own: the
// current prompt does not ask for synonyms, so nothing in production produces this answer — but a
// replay of `mech-v14.2` does, and a parser that had quietly stopped reading the field would turn a
// perfectly good frozen version into a version that returns nothing.
it('still reads synonyms back off an answer that carries them', function () {
    Http::fake(['*' => Http::response([
        'model' => 'gpt-4o-mini-2024-07-18',
        'choices' => [['message' => ['content' => json_encode(['items' => [[
            'text' => 'purpose',
            'forms' => [],
            'synonyms' => ['goal', 'aim'],
            'distractors' => [],
        ]]])]]],
        'usage' => ['prompt_tokens' => 5, 'completion_tokens' => 7],
    ], 200)]);

    $pack = livePacker()->pack(new EnrichmentBrief(
        '01J0TERM', 'purpose', ['purpose'], 'цель',
        'The purpose of this meeting is to agree a date.', 'Цель этой встречи — согласовать дату.',
        'en', 'ru', ['objective'],
    ));

    expect($pack->synonyms)->toBe(['goal', 'aim'])
        // Untouched by the addition: the shape still produces no core and no wrong translations.
        ->and($pack->variants)->toBe([])
        ->and($pack->backTranslation)->toBeNull();
});

<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\RequestCollectionGenerationHandler;
use App\Modules\Generation\Application\Dto\GenerationBrief;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiCollectionGenerator;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// The Observability listener logs each outbound OpenAI call to api_request_logs; wrap in a
// transaction so those rows roll back instead of leaking into other tests' queries.
uses(RefreshDatabase::class);

function fakeOpenAi(): void
{
    Http::fake(['*' => Http::response([
        'choices' => [['message' => ['content' => json_encode(['title' => 't', 'description' => 'd', 'items' => []])]]],
        'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
    ], 200)]);
}

function generateWith(string $version): void
{
    (new OpenAiCollectionGenerator(app(OutboundCallContext::class), 'key', 'gpt-4o', $version))->generate(
        new GenerationBrief('в банке', new LanguageCode('ru'), new LanguageCode('en'), ['A2', 'B1'], 15),
    );
}

it('loads the v3 prompt with the four-type taxonomy and a first-class AVOID block', function () {
    fakeOpenAi();
    generateWith('v3');

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];

        return str_contains($system, 'phrasal_verb') && str_contains($system, 'ALREADY SELECTED');
    });
});

it('keeps the frozen v2 prompt free of the v3 additions', function () {
    fakeOpenAi();
    generateWith('v2');

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];

        return ! str_contains($system, 'phrasal_verb') && ! str_contains($system, 'ALREADY SELECTED');
    });
});

it('adds the image fields to the schema on v4 and parses them back', function () {
    Http::fake(['*' => Http::response([
        'choices' => [['message' => ['content' => json_encode([
            'title' => 't', 'description' => 'd', 'collection_image_prompt' => 'bank branch interior',
            'items' => [[
                'text' => 'withdraw cash', 'type' => 'phrase', 'transcription' => 'x', 'translation' => 'снять',
                'example' => 'e', 'example_translation' => 'э', 'cefr' => 'A2',
                'image_api_prompt' => 'atm cash withdrawal',
            ]],
        ])]]],
        'usage' => ['prompt_tokens' => 1, 'completion_tokens' => 1],
    ], 200)]);

    $draft = (new OpenAiCollectionGenerator(app(OutboundCallContext::class), 'key', 'gpt-4o', 'v4'))->generate(
        new GenerationBrief('в банке', new LanguageCode('ru'), new LanguageCode('en'), ['A2', 'B1'], 15),
    );

    expect($draft->imageApiPrompt)->toBe('bank branch interior')
        ->and($draft->items[0]->imageApiPrompt)->toBe('atm cash withdrawal');

    Http::assertSent(function (Request $request): bool {
        $schema = $request->data()['response_format']['json_schema']['schema'];
        $itemSchema = $schema['properties']['items']['items'];

        return in_array('collection_image_prompt', $schema['required'], true)
            && in_array('image_api_prompt', $itemSchema['required'], true)
            && str_contains($request->data()['messages'][0]['content'], 'image_api_prompt');
    });
});

it('adds the v5 rules: the translation must determine its answer, and no UA lexis in RU fields', function () {
    fakeOpenAi();
    generateWith('v5');

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];

        // The back-translation rule (an ambiguous prompt is unanswerable) and the purity rule,
        // named concretely enough that the model can't read it as generic style advice.
        return str_contains($system, 'determine its own answer')
            && str_contains($system, 'Language purity')
            && str_contains($system, 'здаватися')
            // v4's content is inherited, not replaced.
            && str_contains($system, 'image_api_prompt');
    });
});

/**
 * v6 is the answer to a Ukrainian translation reaching a card (docs/ua-audit.md). v5 already
 * forbade Ukrainian; what it lacked was a language stated as a SETTING rather than something to be
 * inferred from the topic, and a step where the model reads its own items back.
 */
it('adds the v6 rules: the languages are pinned settings and every item is read back', function () {
    fakeOpenAi();
    generateWith('v6');

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];

        return str_contains($system, 'The two languages, fixed before you start')
            && str_contains($system, 'does not depend on the topic')
            && str_contains($system, 'Final self-check')
            // The letters are named, not just the language: this is the class the deterministic
            // barrier also keys on, and the prompt and the barrier must be talking about the same thing.
            && str_contains($system, 'і')
            && str_contains($system, 'на одній хвилі')
            // v5's content is inherited, not replaced.
            && str_contains($system, 'determine its own answer')
            && str_contains($system, 'Language purity')
            && str_contains($system, 'image_api_prompt');
    });
});

it('keeps the v6 rules out of the frozen v5 prompt', function () {
    fakeOpenAi();
    generateWith('v5');

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];

        return ! str_contains($system, 'Final self-check')
            && ! str_contains($system, 'The two languages, fixed before you start');
    });
});

/**
 * v7 is the answer to an example that repeats its own term (QA-7, приёмка 17.08). Three of ten
 * items came back with `example` identical to `text`, so the intro card showed the same sentence
 * twice. The validator now refuses those; the prompt is where we ask for them not to happen.
 */
it('adds the v7 rule: the example must expand the term, never repeat it', function () {
    fakeOpenAi();
    generateWith('v7');

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];

        return str_contains($system, 'must EXPAND the term, never repeat it')
            // Worked through on the very item that failed, and stated for the case that tempts it:
            // a term that is already a whole sentence.
            && str_contains($system, 'Where can I find dog food?')
            && str_contains($system, 'The example teaches')
            // …and the licence NOT to over-correct: an example containing the term is the point.
            && str_contains($system, 'CONTAINING `text` is correct and expected')
            // v6's content is inherited, not replaced.
            && str_contains($system, 'The two languages, fixed before you start')
            && str_contains($system, 'Final self-check')
            && str_contains($system, 'determine its own answer')
            && str_contains($system, 'image_api_prompt');
    });
});

it('keeps the v7 rule out of the frozen v6 prompt', function () {
    fakeOpenAi();
    generateWith('v6');

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];

        return ! str_contains($system, 'must EXPAND the term, never repeat it');
    });
});

it('generates on v7 by default', function () {
    expect(RequestCollectionGenerationHandler::PROMPT_VERSION)->toBe('v7');
});

it('keeps the v5 rules out of the frozen v4 prompt', function () {
    fakeOpenAi();
    generateWith('v4');

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];

        return ! str_contains($system, 'Language purity')
            && ! str_contains($system, 'determine its own answer');
    });
});

it('keeps the image fields out of the v3 schema (frozen taxonomy eval)', function () {
    fakeOpenAi();
    generateWith('v3');

    Http::assertSent(function (Request $request): bool {
        $schema = $request->data()['response_format']['json_schema']['schema'];
        $itemSchema = $schema['properties']['items']['items'];

        return ! in_array('collection_image_prompt', $schema['required'], true)
            && ! array_key_exists('image_api_prompt', $itemSchema['properties']);
    });
});

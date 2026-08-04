<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Dto\GenerationBrief;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiCollectionGenerator;
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
    (new OpenAiCollectionGenerator('key', 'gpt-4o', $version))->generate(
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

    $draft = (new OpenAiCollectionGenerator('key', 'gpt-4o', 'v4'))->generate(
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

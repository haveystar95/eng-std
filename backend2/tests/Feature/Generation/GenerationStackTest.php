<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Command\RequestCollectionGenerationHandler;
use App\Modules\Generation\Application\Dto\GenerationBrief;
use App\Modules\Generation\Application\Dto\GenerationStackConfig;
use App\Modules\Generation\Application\Port\CollectionGeneratorPort;
use App\Modules\Generation\Infrastructure\Adapter\ContentModelCollectionGenerator;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiCollectionGenerator;
use App\Modules\Shared\Domain\ValueObject\LanguageCode;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * The cut-over of production generation onto the multi-vendor stack, and the switch that undoes it.
 *
 * What matters here is not that a new class exists but that the three things that used to be
 * private to the OpenAI adapter — the prompt, the schema and the vendor call — are now the SHARED
 * ones: the prompt version that was compared in the bake-off is the prompt version that ships, and
 * a rollback is a flag rather than a deploy.
 */
function liveGenerator(array $config = []): CollectionGeneratorPort
{
    config(['services.generation.driver' => 'openai', 'services.openai.api_key' => 'key', ...$config]);
    app()->forgetInstance(GenerationStackConfig::class);

    return app(CollectionGeneratorPort::class);
}

function generateLive(array $config = []): void
{
    Http::fake(['*' => Http::response([
        'model' => 'gpt-5.4-2026-03-05',
        'choices' => [['message' => ['content' => json_encode([
            'title' => 'Поход с ночёвкой', 'description' => 'd', 'collection_image_prompt' => 'campsite tent',
            'items' => [[
                'text' => 'set up the tent', 'type' => 'phrase', 'transcription' => 'x',
                'translation' => 'поставить палатку', 'example' => "Let's set up the tent before dark.",
                'example_translation' => 'Давай поставим палатку до темноты.', 'cefr' => 'A2',
                'image_api_prompt' => 'camping tent setup',
            ]],
        ])]]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20],
    ], 200)]);

    liveGenerator($config)->generate(
        new GenerationBrief('поход с ночёвкой', new LanguageCode('ru'), new LanguageCode('en'), ['A2', 'B1'], 10),
    );
}

it('generates on the v2 stack by default: the v11 catalogue prompt, terms shape', function () {
    generateLive();

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];
        $schema = $request->data()['response_format']['json_schema']['schema'];
        $itemProps = array_keys($schema['properties']['items']['items']['properties']);

        return $request->data()['model'] === 'gpt-5.4'
            // v11's own wording, and the section list of the `terms` shape…
            && str_contains($system, 'The two languages are settings, not judgement calls')
            && str_contains($system, 'image_api_prompt')
            // …which is a CORE and nothing else: no options, no forms — that is the cheap model's
            // job afterwards, and paying the strong model for it is what К2 stopped doing.
            && ! in_array('options', $itemProps, true)
            && ! in_array('forms', $itemProps, true);
    });
});

it('reads the answer back into a draft, crediting the model that actually answered', function () {
    Http::fake(['*' => Http::response([
        'model' => 'gpt-5.4-2026-03-05',
        'choices' => [['message' => ['content' => json_encode([
            'title' => 'Поход с ночёвкой', 'description' => 'Сборы и лагерь',
            'collection_image_prompt' => 'campsite tent',
            'items' => [[
                'text' => 'set up the tent', 'type' => 'phrase', 'transcription' => 'set ʌp ðə tent',
                'translation' => 'поставить палатку', 'example' => "Let's set up the tent before dark.",
                'example_translation' => 'Давай поставим палатку до темноты.', 'cefr' => 'A2',
                'image_api_prompt' => '',
            ]],
        ])]]],
        'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 20],
    ], 200)]);

    $draft = liveGenerator()->generate(
        new GenerationBrief('поход с ночёвкой', new LanguageCode('ru'), new LanguageCode('en'), ['A2', 'B1'], 10),
    );

    expect($draft->title)->toBe('Поход с ночёвкой')
        ->and($draft->imageApiPrompt)->toBe('campsite tent')
        ->and($draft->items[0]->text)->toBe('set up the tent')
        ->and($draft->items[0]->transcription)->toBe('set ʌp ðə tent')
        ->and($draft->items[0]->exampleTranslation)->toBe('Давай поставим палатку до темноты.')
        // "" means «un-illustratable», and it must not become a search for an empty string.
        ->and($draft->items[0]->imageApiPrompt)->toBeNull()
        // The dated snapshot the vendor served, not the alias we asked for: that string is what
        // every term of this collection is stamped with.
        ->and($draft->model)->toBe('gpt-5.4-2026-03-05')
        ->and($draft->tokensIn)->toBe(10);
});

it('puts the old generator back on GENERATION_STACK=v1, prompt v9 and all', function () {
    $generator = liveGenerator(['services.generation.stack' => 'v1']);

    expect($generator)->toBeInstanceOf(OpenAiCollectionGenerator::class);

    generateLive(['services.generation.stack' => 'v1']);

    Http::assertSent(function (Request $request): bool {
        $system = $request->data()['messages'][0]['content'];

        return $request->data()['model'] === 'gpt-4o'
            // v9's wording, which v11 rewrote — the frozen adapter loads the frozen file.
            && str_contains($system, 'The two languages, fixed before you start');
    });
});

it('uses the ContentModel adapter on v2', function () {
    expect(liveGenerator())->toBeInstanceOf(ContentModelCollectionGenerator::class);
});

it('records on the request the version that will actually be rendered', function () {
    liveGenerator();

    expect(app(GenerationStackConfig::class)->corePromptVersion)->toBe('v11.1');

    app()->forgetInstance(GenerationStackConfig::class);
    config(['services.generation.stack' => 'v1']);

    expect(app(GenerationStackConfig::class)->corePromptVersion)
        ->toBe(RequestCollectionGenerationHandler::PROMPT_VERSION)
        ->toBe('v9');
});

it('refuses to generate rather than silently falling back when the provider has no key', function () {
    expect(fn () => liveGenerator(['services.openai.api_key' => '']))
        ->toThrow(RuntimeException::class, 'GENERATION_STACK=v1');
});

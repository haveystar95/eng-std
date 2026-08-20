<?php

declare(strict_types=1);

use App\Modules\Generation\Application\Port\ContentModelCatalog;
use App\Modules\Generation\Domain\ValueObject\PromptShape;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Generation\Infrastructure\Adapter\AnthropicContentModel;
use App\Modules\Generation\Infrastructure\Adapter\ConfiguredContentModelCatalog;
use App\Modules\Generation\Infrastructure\Adapter\GeminiContentModel;
use App\Modules\Generation\Infrastructure\Adapter\OpenAiCompatibleContentModel;
use App\Modules\Generation\Infrastructure\Prompt\PromptLibrary;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

// The Observability listener logs each outbound call to api_request_logs — wrap so those roll back.
uses(RefreshDatabase::class);

function renderedPrompt(PromptShape $shape = PromptShape::Terms): App\Modules\Generation\Application\Dto\RenderedPrompt
{
    return (new PromptLibrary())->render('v10', $shape, [
        'source_lang' => 'Russian', 'target_lang' => 'English', 'levels' => 'A2, B1', 'size' => '3',
    ]);
}

/** @return array<string, mixed> */
function tinySchema(): array
{
    return [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => ['items' => ['type' => 'array', 'items' => ['type' => 'string']]],
        'required' => ['items'],
    ];
}

it('asks an OpenAI-compatible provider for strict json and reads back tokens, model and cost', function () {
    Http::fake(['*' => Http::response([
        'model' => 'gpt-4o-2024-11-20',
        'choices' => [['message' => ['content' => json_encode(['items' => ['a']])]]],
        'usage' => ['prompt_tokens' => 2000, 'completion_tokens' => 1000],
    ], 200)]);

    $answer = (new OpenAiCompatibleContentModel(
        app(OutboundCallContext::class), ProviderId::OpenAi, 'key', 'gpt-4o', 'https://api.openai.com/v1',
    ))->complete(renderedPrompt(), 'TOPIC: "в банке"', tinySchema());

    expect($answer->payload)->toBe(['items' => ['a']])
        ->and($answer->tokensIn)->toBe(2000)
        ->and($answer->tokensOut)->toBe(1000)
        // The model the API says it USED, not the one we asked for — that is what gets priced.
        ->and($answer->model)->toBe('gpt-4o-2024-11-20')
        // 2000/1000 × $0.0025 + 1000/1000 × $0.01 = $0.015
        ->and($answer->costUsd)->toBe('0.015000');

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return str_contains((string) $request->url(), 'api.openai.com')
            && $body['response_format']['json_schema']['strict'] === true
            && $body['messages'][0]['role'] === 'system'
            && str_contains($body['messages'][0]['content'], 'a key must be isomorphic');
    });
});

it('points the same adapter at xAI without changing the request shape', function () {
    Http::fake(['*' => Http::response([
        'model' => 'grok-4.6',
        'choices' => [['message' => ['content' => json_encode(['items' => []])]]],
        'usage' => ['prompt_tokens' => 1000, 'completion_tokens' => 1000],
    ], 200)]);

    $answer = (new OpenAiCompatibleContentModel(
        app(OutboundCallContext::class), ProviderId::Xai, 'key', 'grok-4.6', 'https://api.x.ai/v1',
    ))->complete(renderedPrompt(), 'TOPIC', tinySchema());

    // 1000/1000 × $0.002 + 1000/1000 × $0.006
    expect($answer->costUsd)->toBe('0.008000');

    Http::assertSent(fn (Request $r): bool => str_contains((string) $r->url(), 'api.x.ai/v1/chat/completions'));
});

/**
 * Anthropic's three differences from the shape above, asserted rather than assumed: this deployment
 * has no Anthropic key, so a fake is the only thing that will ever exercise this adapter until one
 * is added.
 */
it('sends the Anthropic shape: x-api-key, a top-level system field and output_config', function () {
    Http::fake(['*' => Http::response([
        'model' => 'claude-opus-5',
        'stop_reason' => 'end_turn',
        'content' => [['type' => 'text', 'text' => json_encode(['items' => ['a']])]],
        'usage' => ['input_tokens' => 1000, 'output_tokens' => 1000],
    ], 200)]);

    $answer = (new AnthropicContentModel(app(OutboundCallContext::class), 'key', 'claude-opus-5'))
        ->complete(renderedPrompt(), 'TOPIC', tinySchema());

    // 1000/1000 × $0.005 + 1000/1000 × $0.025
    expect($answer->costUsd)->toBe('0.030000')
        ->and($answer->payload)->toBe(['items' => ['a']]);

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->hasHeader('x-api-key', 'key')
            && $request->hasHeader('anthropic-version', '2023-06-01')
            // The rules go in `system`, NOT as the first message: passing them as a message answers
            // measurably worse, and in a bake-off that reads as "this vendor is worse at the task".
            && str_contains($body['system'], 'a key must be isomorphic')
            && count($body['messages']) === 1
            && $body['output_config']['format']['type'] === 'json_schema';
    });
});

it('reports an Anthropic policy refusal as a refusal, not as malformed json', function () {
    Http::fake(['*' => Http::response([
        'model' => 'claude-opus-5', 'stop_reason' => 'refusal', 'content' => [],
    ], 200)]);

    expect(fn () => (new AnthropicContentModel(app(OutboundCallContext::class), 'key', 'claude-opus-5'))
        ->complete(renderedPrompt(), 'TOPIC', tinySchema()))
        ->toThrow(RuntimeException::class, 'refused');
});

it('skips a text block that is not the answer when a model thinks out loud first', function () {
    Http::fake(['*' => Http::response([
        'model' => 'claude-opus-5',
        'stop_reason' => 'end_turn',
        'content' => [
            ['type' => 'thinking', 'thinking' => 'считаю'],
            ['type' => 'text', 'text' => json_encode(['items' => ['a']])],
        ],
        'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
    ], 200)]);

    $answer = (new AnthropicContentModel(app(OutboundCallContext::class), 'key', 'claude-opus-5'))
        ->complete(renderedPrompt(), 'TOPIC', tinySchema());

    expect($answer->payload)->toBe(['items' => ['a']]);
});

/**
 * Gemini's four differences, asserted rather than assumed — the key as a HEADER (so it never lands
 * in a logged URL), the system prompt in its own field, and JSON forced through generationConfig.
 */
it('sends the Gemini shape: x-goog-api-key, systemInstruction and a json responseSchema', function () {
    Http::fake(['*' => Http::response([
        'modelVersion' => 'gemini-3.7-flash',
        'candidates' => [[
            'finishReason' => 'STOP',
            'content' => ['parts' => [['text' => json_encode(['items' => ['a']])]]],
        ]],
        'usageMetadata' => ['promptTokenCount' => 4000, 'candidatesTokenCount' => 1000],
    ], 200)]);

    $answer = (new GeminiContentModel(app(OutboundCallContext::class), 'key', 'gemini-3.7-flash'))
        ->complete(renderedPrompt(), 'TOPIC', tinySchema());

    // 4000/1000 × $0.00075 + 1000/1000 × $0.00375
    expect($answer->costUsd)->toBe('0.006750')
        ->and($answer->payload)->toBe(['items' => ['a']])
        ->and($answer->model)->toBe('gemini-3.7-flash');

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->hasHeader('x-goog-api-key', 'key')
            // The key must NOT be in the URL: the outbound-call log records urls verbatim.
            && ! str_contains((string) $request->url(), 'key=')
            && str_contains((string) $request->url(), 'models/gemini-3.7-flash:generateContent')
            && str_contains($body['systemInstruction']['parts'][0]['text'], 'a key must be isomorphic')
            && $body['contents'][0]['parts'][0]['text'] === 'TOPIC'
            && $body['generationConfig']['responseMimeType'] === 'application/json';
    });
});

/**
 * The one place where "every provider gets the same schema" cannot be literally true: Gemini's
 * responseSchema is an OpenAPI-3.0 subset and REJECTS `additionalProperties`, which the other two
 * providers require for strict mode. The constraint is the same; the dialect is not.
 */
it('translates the shared schema into the dialect Gemini accepts', function () {
    Http::fake(['*' => Http::response([
        'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => '{"items":[]}']]]]],
        'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
    ], 200)]);

    $schema = [
        'type' => 'object',
        'additionalProperties' => false,
        'properties' => [
            'title' => ['type' => 'string'],
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'properties' => ['text' => ['type' => 'string'], 'cefr' => ['type' => 'string', 'enum' => ['A1']]],
                    'required' => ['text', 'cefr'],
                ],
            ],
        ],
        'required' => ['title', 'items'],
    ];

    (new GeminiContentModel(app(OutboundCallContext::class), 'key', 'gemini-3.7-flash'))
        ->complete(renderedPrompt(), 'TOPIC', $schema);

    Http::assertSent(function (Request $request) : bool {
        $sent = $request->data()['generationConfig']['responseSchema'];
        $item = $sent['properties']['items']['items'];

        return ! array_key_exists('additionalProperties', $sent)
            // …at every level, not just the top one.
            && ! array_key_exists('additionalProperties', $item)
            // Everything that carries meaning survives.
            && $sent['required'] === ['title', 'items']
            && $item['required'] === ['text', 'cefr']
            && $item['properties']['cefr']['enum'] === ['A1']
            // Field order is pinned, so two runs of the same schema are comparable.
            && $sent['propertyOrdering'] === ['title', 'items']
            && $item['propertyOrdering'] === ['text', 'cefr'];
    });
});

it('names a Gemini safety block as a block, not as malformed json', function () {
    Http::fake(['*' => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']], 200)]);

    expect(fn () => (new GeminiContentModel(app(OutboundCallContext::class), 'key', 'gemini-3.7-flash'))
        ->complete(renderedPrompt(), 'TOPIC', tinySchema()))
        ->toThrow(RuntimeException::class, 'blockReason=SAFETY');
});

it('joins a Gemini answer split across several parts', function () {
    Http::fake(['*' => Http::response([
        'candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [
            ['text' => '{"items":'],
            ['text' => '["a"]}'],
        ]]]],
        'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
    ], 200)]);

    $answer = (new GeminiContentModel(app(OutboundCallContext::class), 'key', 'gemini-3.7-flash'))
        ->complete(renderedPrompt(), 'TOPIC', tinySchema());

    expect($answer->payload)->toBe(['items' => ['a']]);
});

/**
 * A provider with no key is a normal state, not a failure. The whole bake-off depends on this: a
 * catalogue that threw on an unconfigured vendor would produce nothing on the night it mattered.
 */
it('reports a keyless provider as unavailable with the env var named, and still lists it', function () {
    // Models pinned as well as keys: without this the assertion reads whatever the developer
    // happens to have in .env, and the test passes or fails by accident.
    config([
        'services.openai.api_key' => 'sk-test',
        'services.openai.compare_model' => 'gpt-5.4',
        'services.anthropic.api_key' => '',
        'services.anthropic.generate_model' => 'claude-sonnet-5',
        'services.xai.api_key' => 'xai-test',
        'services.gemini.api_key' => 'AIza-test',
    ]);

    $catalog = new ConfiguredContentModelCatalog(app(OutboundCallContext::class));
    $byProvider = [];
    foreach ($catalog->availability() as $row) {
        $byProvider[$row->provider->value] = $row;
    }

    expect(array_keys($byProvider))->toBe(['openai', 'anthropic', 'xai', 'gemini'])
        ->and($byProvider['anthropic']->available)->toBeFalse()
        ->and($byProvider['anthropic']->reason)->toContain('ANTHROPIC_API_KEY')
        // …and the model it WOULD have used is still reported, so the report can name what was missed.
        ->and($byProvider['anthropic']->model)->toBe('claude-sonnet-5')
        ->and($byProvider['openai']->available)->toBeTrue();

    expect($catalog->get(ProviderId::Anthropic))->toBeNull()
        ->and(array_map(static fn ($p): string => $p->provider()->value, $catalog->available()))
        ->toBe(['openai', 'xai', 'gemini']);
});

it('is bound so the catalogue can be resolved from the container', function () {
    expect(app(ContentModelCatalog::class))->toBeInstanceOf(ConfiguredContentModelCatalog::class);
});

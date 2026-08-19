<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Shared\Domain\Service\ModelCost;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * `POST /chat/completions` with `response_format: json_schema` — OpenAI's shape, and xAI's, which
 * implements the same surface deliberately. ONE adapter with two bindings rather than two classes
 * that would have to be kept in step: a divergence between them would show up in a bake-off as a
 * quality difference between vendors, which is exactly the reading the whole exercise must not
 * produce.
 *
 * If xAI ever stops matching OpenAI's request shape, this splits — but it splits on evidence, not
 * on the assumption that two vendors must need two classes.
 */
final readonly class OpenAiCompatibleContentModel implements ContentModelPort
{
    public function __construct(
        private OutboundCallContext $context,
        private ProviderId $provider,
        private string $apiKey,
        private string $model,
        private string $baseUrl,
        private ModelCost $cost = new ModelCost(),
        private int $timeoutSeconds = 180,
        private int $retries = 2,
    ) {}

    public function provider(): ProviderId
    {
        return $this->provider;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function complete(RenderedPrompt $prompt, string $userMessage, array $schema): ModelAnswer
    {
        $startedAt = hrtime(true);

        // Labelled so the request log can say what this spend was FOR, like every other vendor call.
        $response = $this->context->run('generation', null, fn () => Http::withToken($this->apiKey)
            ->timeout($this->timeoutSeconds)
            // Transport only: a 4xx from the vendor is an answer, not a hiccup, and re-asking it
            // costs the same money for the same refusal.
            ->retry($this->retries, 1000, throw: false)
            ->post(rtrim($this->baseUrl, '/') . '/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $prompt->text],
                    ['role' => 'user', 'content' => $userMessage],
                ],
                'response_format' => [
                    'type' => 'json_schema',
                    'json_schema' => ['name' => 'content', 'strict' => true, 'schema' => $schema],
                ],
            ]));

        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if ($response->failed()) {
            throw new RuntimeException(
                $this->provider->label() . ' API error: ' . $response->status() . ' ' . mb_substr($response->body(), 0, 500)
            );
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new RuntimeException($this->provider->label() . ' returned empty content.');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException($this->provider->label() . ' returned malformed JSON: ' . mb_substr($content, 0, 500));
        }

        $tokensIn = is_int($response->json('usage.prompt_tokens')) ? $response->json('usage.prompt_tokens') : null;
        $tokensOut = is_int($response->json('usage.completion_tokens')) ? $response->json('usage.completion_tokens') : null;
        $model = is_string($response->json('model')) ? $response->json('model') : $this->model;

        /** @var array<string, mixed> $decoded */
        return new ModelAnswer(
            payload: $decoded,
            model: $model,
            latencyMs: $latencyMs,
            tokensIn: $tokensIn,
            tokensOut: $tokensOut,
            costUsd: $this->cost->estimate($model, $tokensIn, $tokensOut),
            raw: mb_substr($content, 0, 4000),
        );
    }
}

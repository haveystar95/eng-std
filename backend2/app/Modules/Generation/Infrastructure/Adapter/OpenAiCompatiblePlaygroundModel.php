<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\PlaygroundRawReply;
use App\Modules\Generation\Application\Port\PlaygroundModelPort;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * `POST /chat/completions` with ONE user message and nothing else.
 *
 * The differences from {@see OpenAiCompatibleContentModel} are the whole reason this class exists,
 * and each is a deliberate subtraction:
 *
 *  - **no system prompt** — the operator's text is the entire conversation;
 *  - **no `response_format`** — the sandbox must be able to observe a model failing to return JSON,
 *    which a schema-constrained call can never show;
 *  - **no retries** — a retry silently multiplies the bill for a call a person is watching, and
 *    «упало» is information here rather than something to paper over.
 *
 * Labelled `playground` through {@see OutboundCallContext}, so sandbox spend lands in the same
 * request log as everything else and is separable from production spend rather than hidden in it.
 */
final readonly class OpenAiCompatiblePlaygroundModel implements PlaygroundModelPort
{
    public function __construct(
        private OutboundCallContext $context,
        private ProviderId $provider,
        private string $apiKey,
        private string $model,
        private string $baseUrl,
        private int $timeoutSeconds = 60,
    ) {}

    public function provider(): ProviderId
    {
        return $this->provider;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function ask(string $prompt, ?float $temperature = null): PlaygroundRawReply
    {
        $payload = [
            'model' => $this->model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];
        // Only when asked for: the current reasoning models accept their default and refuse the
        // parameter outright, so sending 1.0 "just to be explicit" breaks them.
        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }

        $startedAt = hrtime(true);
        $response = $this->context->run('playground', null, fn () => Http::withToken($this->apiKey)
            ->timeout($this->timeoutSeconds)
            ->post(rtrim($this->baseUrl, '/') . '/chat/completions', $payload));
        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if ($response->failed()) {
            throw new RuntimeException(
                $this->provider->label() . ': ' . $response->status() . ' ' . mb_substr($response->body(), 0, 800)
            );
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content)) {
            // Not an empty-string check: an empty answer is a real (and interesting) result for a
            // prompt experiment, while a missing field means the reply was not the shape we parse.
            throw new RuntimeException($this->provider->label() . ': ответ без текстового содержимого.');
        }

        return new PlaygroundRawReply(
            rawText: $content,
            model: is_string($response->json('model')) ? $response->json('model') : $this->model,
            latencyMs: $latencyMs,
            tokensIn: is_int($response->json('usage.prompt_tokens')) ? $response->json('usage.prompt_tokens') : null,
            tokensOut: is_int($response->json('usage.completion_tokens')) ? $response->json('usage.completion_tokens') : null,
        );
    }
}

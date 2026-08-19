<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Shared\Domain\Service\ModelCost;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Anthropic's Messages API. Three things differ from the OpenAI-shaped adapter beside it, and each
 * one is why this is a separate class rather than another binding of that one:
 *
 *  - **auth and versioning** — `x-api-key` plus a required `anthropic-version` header, not a bearer
 *    token;
 *  - **the system prompt is its own top-level field**, not the first message. Passing it as a
 *    message would still answer, and would answer measurably worse, which in a bake-off would read
 *    as "Anthropic is worse at this task";
 *  - **structured output is `output_config.format`** (`{type: json_schema, schema: …}`), and the
 *    JSON comes back inside a normal text content block rather than in a field of its own.
 *
 * Raw HTTP through Laravel's client, like every other vendor in this module, and for a concrete
 * reason rather than habit: the Observability listener logs outbound calls made through this client,
 * so an official SDK would take this vendor's spend off the one ledger the app has.
 *
 * NOT verified against the live API — this deployment has no Anthropic key (the org has no credits),
 * so the adapter is exercised only against a faked HTTP client. The request shape follows the
 * current documented API; the first real call is the one that proves it.
 */
final readonly class AnthropicContentModel implements ContentModelPort
{
    /** Required by the API on every request. Pinned, not "latest" — a wire format must not move. */
    private const API_VERSION = '2023-06-01';

    public function __construct(
        private OutboundCallContext $context,
        private string $apiKey,
        private string $model,
        private string $baseUrl = 'https://api.anthropic.com/v1',
        private ModelCost $cost = new ModelCost(),
        private int $timeoutSeconds = 180,
        private int $retries = 4,
        private int $maxTokens = 16000,
    ) {}

    public function provider(): ProviderId
    {
        return ProviderId::Anthropic;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function complete(RenderedPrompt $prompt, string $userMessage, array $schema): ModelAnswer
    {
        $startedAt = hrtime(true);

        $response = $this->context->run('generation', null, fn () => Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
        ])
            ->timeout($this->timeoutSeconds)
            // Same policy as the OpenAI-shaped adapter — see the comment there for why the backoff
            // escalates and why a 403 is not retried.
            ->retry(
                $this->retries,
                static fn (int $attempt): int => $attempt * 4000,
                static fn (\Throwable $e): bool => ! $e instanceof RequestException
                    || in_array($e->response->status(), [408, 409, 429, 500, 502, 503, 504], true),
                throw: false,
            )
            ->post(rtrim($this->baseUrl, '/') . '/messages', [
                'model' => $this->model,
                'max_tokens' => $this->maxTokens,
                'system' => $prompt->text,
                'messages' => [['role' => 'user', 'content' => $userMessage]],
                'output_config' => ['format' => ['type' => 'json_schema', 'schema' => $schema]],
            ]));

        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic API error: ' . $response->status() . ' ' . mb_substr($response->body(), 0, 500));
        }

        // A policy decline arrives as a 200 with no usable content. Reading `content` first would
        // report it as "malformed JSON", which sends a reader looking for a parsing bug.
        $stopReason = $response->json('stop_reason');
        if ($stopReason === 'refusal') {
            throw new RuntimeException('Anthropic refused the request (stop_reason=refusal).');
        }

        $content = $this->firstText($response->json('content'));
        if ($content === null) {
            throw new RuntimeException('Anthropic returned no text content (stop_reason=' . (is_string($stopReason) ? $stopReason : '?') . ').');
        }

        $decoded = json_decode($content, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Anthropic returned malformed JSON: ' . mb_substr($content, 0, 500));
        }

        $tokensIn = is_int($response->json('usage.input_tokens')) ? $response->json('usage.input_tokens') : null;
        $tokensOut = is_int($response->json('usage.output_tokens')) ? $response->json('usage.output_tokens') : null;
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

    /**
     * The first `text` block's text. The answer may be preceded by thinking blocks, and iterating
     * rather than indexing `content.0` is what keeps this correct when it is.
     */
    private function firstText(mixed $content): ?string
    {
        if (! is_array($content)) {
            return null;
        }

        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null) && trim($block['text']) !== '') {
                return $block['text'];
            }
        }

        return null;
    }
}

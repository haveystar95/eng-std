<?php

declare(strict_types=1);

namespace App\Modules\Generation\Infrastructure\Adapter;

use App\Modules\Generation\Application\Dto\ModelAnswer;
use App\Modules\Generation\Application\Dto\RenderedPrompt;
use App\Modules\Generation\Application\Port\ContentModelPort;
use App\Modules\Generation\Domain\ValueObject\ProviderId;
use App\Modules\Observability\Application\Dto\ModelCallUsage;
use App\Modules\Observability\Application\Support\OutboundCallContext;
use App\Modules\Shared\Domain\Service\ModelCost;
use Illuminate\Http\Client\Response;
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
        private VendorCall $calls,
        private string $apiKey,
        private string $model,
        private string $baseUrl = 'https://api.anthropic.com/v1',
        private ModelCost $cost = new ModelCost(),
        private int $timeoutSeconds = 180,
        /**
         * WHAT this spend is FOR, as the request log records it.
         *
         * A parameter rather than a constant because the same adapter now serves two
         * products with different budgets: a collection and a learning plan. The label
         * is applied where the vendor call is made — the log row is written by an Http
         * event listener far from the code that decided to spend — so it has to travel
         * with the adapter, and the default keeps every existing caller unchanged.
         */
        private string $purpose = 'generation',
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
        $body = [
            'model' => $this->model,
            'max_tokens' => $this->maxTokens,
            'system' => $prompt->text,
            'messages' => [['role' => 'user', 'content' => $userMessage]],
            'output_config' => ['format' => ['type' => 'json_schema', 'schema' => $schema]],
        ];

        // The same call policy as every adapter of the module — journal, timeouts, what is retried: {@see VendorCall}.
        $response = $this->calls->send(
            ProviderId::Anthropic,
            $this->model,
            $this->purpose,
            $this->timeoutSeconds,
            $body,
            fn () => $this->context->run($this->purpose, null, fn () => Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => self::API_VERSION,
            ])
                ->connectTimeout(VendorCall::CONNECT_TIMEOUT)
                ->timeout($this->timeoutSeconds)
                ->retry($this->retries, VendorCall::backoff(...), VendorCall::retryable(...), throw: false)
                ->post(rtrim($this->baseUrl, '/') . '/messages', $body)),
            fn (Response $r): ModelCallUsage => $this->usage($r),
        );

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

        $usage = $this->usage($response);

        /** @var array<string, mixed> $decoded */
        return new ModelAnswer(
            payload: $decoded,
            model: $usage->answeredModel,
            latencyMs: $latencyMs,
            tokensIn: $usage->tokensIn,
            tokensOut: $usage->tokensOut,
            costUsd: $usage->costUsd,
            raw: mb_substr($content, 0, 4000),
        );
    }

    /** What the answer says it spent. No cache breakpoint is ever set on this vendor, so nothing of the input is cached. */
    private function usage(Response $response): ModelCallUsage
    {
        $tokensIn = is_int($response->json('usage.input_tokens')) ? $response->json('usage.input_tokens') : null;
        $tokensOut = is_int($response->json('usage.output_tokens')) ? $response->json('usage.output_tokens') : null;
        $model = is_string($response->json('model')) ? $response->json('model') : $this->model;

        return new ModelCallUsage($model, $tokensIn, null, $tokensOut, $this->cost->estimate($model, $tokensIn, $tokensOut));
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

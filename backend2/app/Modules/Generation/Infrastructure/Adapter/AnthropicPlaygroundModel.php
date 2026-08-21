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
 * Anthropic's Messages API, raw — the sandbox's own minimal client.
 *
 * A separate class from {@see AnthropicContentModel} rather than a flag on it, because the two want
 * opposite things from the same vendor. That one exists to get a schema-shaped object out of Claude
 * and treats anything else as a failure; this one exists to show whatever Claude said, including the
 * prose it wrote instead of JSON. Bolting a "no schema" mode onto the production adapter would put
 * the branch that skips validation inside the class the станок calls, which is exactly where it must
 * not be — nothing on the production path can reach this file.
 *
 * The wire particulars are Anthropic's and are the same in both: `x-api-key`, a pinned
 * `anthropic-version`, `max_tokens` is required, and the answer arrives as content blocks.
 *
 * Raw HTTP through Laravel's client for the same reason as every other vendor here: the Observability
 * listener logs calls made through it, so sandbox spend stays on the one ledger the app has.
 */
final readonly class AnthropicPlaygroundModel implements PlaygroundModelPort
{
    /** Required on every request. Pinned, not "latest" — a wire format must not move under us. */
    private const API_VERSION = '2023-06-01';

    /**
     * Required by the API, so it is a decision rather than a default we can decline to make. Half
     * the production adapter's budget: a sandbox reply nobody will read past the first screen does
     * not need sixteen thousand tokens of room, and the cap is the only bound on what one careless
     * prompt can spend.
     */
    private const MAX_TOKENS = 8000;

    public function __construct(
        private OutboundCallContext $context,
        private string $apiKey,
        private string $model,
        private string $baseUrl = 'https://api.anthropic.com/v1',
        private int $timeoutSeconds = 60,
    ) {}

    public function provider(): ProviderId
    {
        return ProviderId::Anthropic;
    }

    public function model(): string
    {
        return $this->model;
    }

    public function ask(string $prompt, ?float $temperature = null): PlaygroundRawReply
    {
        $payload = [
            'model' => $this->model,
            'max_tokens' => self::MAX_TOKENS,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];
        if ($temperature !== null) {
            $payload['temperature'] = $temperature;
        }

        $startedAt = hrtime(true);
        $response = $this->context->run('playground', null, fn () => Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
        ])
            ->timeout($this->timeoutSeconds)
            ->post(rtrim($this->baseUrl, '/') . '/messages', $payload));
        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic: ' . $response->status() . ' ' . mb_substr($response->body(), 0, 800));
        }

        // A policy decline arrives as a 200 with no usable content. Naming it beats printing an
        // empty answer box.
        if ($response->json('stop_reason') === 'refusal') {
            throw new RuntimeException('Anthropic отказался отвечать (stop_reason=refusal).');
        }

        return new PlaygroundRawReply(
            // Every text block joined, not just the first: a sandbox must show the whole answer,
            // and a long reply legitimately arrives in several blocks.
            rawText: $this->text($response->json('content')),
            model: is_string($response->json('model')) ? $response->json('model') : $this->model,
            latencyMs: $latencyMs,
            tokensIn: is_int($response->json('usage.input_tokens')) ? $response->json('usage.input_tokens') : null,
            tokensOut: is_int($response->json('usage.output_tokens')) ? $response->json('usage.output_tokens') : null,
        );
    }

    private function text(mixed $content): string
    {
        if (! is_array($content)) {
            return '';
        }

        $parts = [];
        foreach ($content as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'text' && is_string($block['text'] ?? null)) {
                $parts[] = $block['text'];
            }
        }

        return implode('', $parts);
    }
}
